<?php

namespace App\Jobs;

use App\Services\VirusScanningService;
use App\Enums\ScanStatus;
use App\Enums\ThreatLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Throwable;

/**
 * Virus Scanning Job for Asynchronous File Processing
 * 
 * This job performs comprehensive virus scanning on uploaded files including:
 * - Multi-engine scanning (ClamAV + heuristics)
 * - Threat quarantine and management
 * - Real-time status updates
 * - Security team notifications
 * - Detailed logging and metrics
 */
class VirusScanFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 300; // 5 minutes max
    public $tries = 2; // Limited retries for virus scanning
    public $maxExceptions = 1;
    public $failOnTimeout = true;
    
    private string $jobId;
    private VirusScanningService $scannerService;
    
    /**
     * Create a new job instance
     */
    public function __construct(
        private string $filePath,
        private string $context = 'upload',
        private ?int $userId = null,
        private array $metadata = []
    ) {
        $this->jobId = (string) Str::uuid();
        
        // Set queue priority based on user status or context
        if ($userId && $this->isVipUser($userId)) {
            $this->onQueue('high-priority');
        } elseif ($context === 'critical') {
            $this->onQueue('critical');
        } else {
            $this->onQueue('virus-scanning');
        }
    }
    
    /**
     * Execute the job
     */
    public function handle(VirusScanningService $scannerService): void
    {
        $this->scannerService = $scannerService;
        
        Log::info('Starting virus scan job', [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'context' => $this->context,
            'user_id' => $this->userId,
            'attempt' => $this->attempts()
        ]);
        
        try {
            // Update scan status to scanning
            $this->updateScanStatus(ScanStatus::SCANNING);
            
            // Validate file exists and is accessible
            if (!$this->validateFile()) {
                $this->handleScanFailure('File validation failed');
                return;
            }
            
            // Perform comprehensive virus scan
            $scanResult = $this->scannerService->scanFile($this->filePath, $this->context);
            
            // Process scan results
            $this->processScanResult($scanResult);
            
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
    
    /**
     * Validate file before scanning
     */
    private function validateFile(): bool
    {
        if (!file_exists($this->filePath)) {
            Log::error('File not found for virus scan', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath
            ]);
            return false;
        }
        
        if (!is_readable($this->filePath)) {
            Log::error('File not readable for virus scan', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath
            ]);
            return false;
        }
        
        $fileSize = filesize($this->filePath);
        if ($fileSize === false || $fileSize === 0) {
            Log::error('Invalid file size for virus scan', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath,
                'file_size' => $fileSize
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Process the scan result and take appropriate actions
     */
    private function processScanResult(array $scanResult): void
    {
        $status = $scanResult['status'];
        $isClean = $scanResult['is_clean'];
        $threatsFound = $scanResult['threats_found'];
        $threatLevel = $scanResult['threat_level'];
        
        Log::info('Virus scan completed', [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'status' => $status->value,
            'is_clean' => $isClean,
            'threat_count' => count($threatsFound),
            'threat_level' => $threatLevel->value,
            'scan_duration' => $scanResult['scan_duration']
        ]);
        
        // Store detailed scan result
        $this->storeScanResult($scanResult);
        
        if ($isClean) {
            $this->handleCleanFile($scanResult);
        } else {
            $this->handleInfectedFile($scanResult);
        }
        
        // Update metrics
        $this->updateScanMetrics($scanResult);
        
        // Send notifications if required
        $this->sendNotifications($scanResult);
    }
    
    /**
     * Handle clean file result
     */
    private function handleCleanFile(array $scanResult): void
    {
        $this->updateScanStatus(ScanStatus::CLEAN, [
            'scan_result' => $scanResult,
            'message' => 'File is clean and safe to process'
        ]);
        
        // Trigger any post-scan processing
        $this->triggerPostScanProcessing($scanResult);
        
        Log::info('File cleared virus scan', [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'scan_engines' => array_keys($scanResult['scan_engines']),
            'scan_duration' => $scanResult['scan_duration']
        ]);
    }
    
    /**
     * Handle infected file result
     */
    private function handleInfectedFile(array $scanResult): void
    {
        $threatLevel = $scanResult['threat_level'];
        $threatsFound = $scanResult['threats_found'];
        
        // Determine status based on threat level
        $status = $scanResult['quarantined'] ? ScanStatus::QUARANTINED : ScanStatus::INFECTED;
        
        $this->updateScanStatus($status, [
            'scan_result' => $scanResult,
            'message' => $this->generateThreatMessage($threatsFound),
            'threat_level' => $threatLevel->value,
            'threat_count' => count($threatsFound)
        ]);
        
        // Log security incident
        Log::error('SECURITY THREAT DETECTED', [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'threat_level' => $threatLevel->value,
            'threats' => $threatsFound,
            'quarantined' => $scanResult['quarantined'],
            'user_id' => $this->userId,
            'context' => $this->context,
            'immediate_action_required' => $threatLevel->requiresImmediateAction()
        ]);
        
        // Block file processing
        $this->blockFileProcessing($scanResult);
        
        // Increment security violations counter for user
        if ($this->userId) {
            $this->incrementUserViolations($this->userId, $threatLevel);
        }
    }
    
    /**
     * Handle scan failure
     */
    private function handleScanFailure(string $reason, ?Throwable $exception = null): void
    {
        $errorData = [
            'reason' => $reason,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries
        ];
        
        if ($exception) {
            $errorData['exception'] = $exception->getMessage();
            $errorData['trace'] = $exception->getTraceAsString();
        }
        
        Log::error('Virus scan failed', array_merge([
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'user_id' => $this->userId
        ], $errorData));
        
        // If we haven't exhausted retries, schedule another attempt
        if ($this->attempts() < $this->tries) {
            $this->updateScanStatus(ScanStatus::PENDING, [
                'message' => 'Scan failed, retrying...',
                'retry_attempt' => $this->attempts() + 1
            ]);
            
            // Retry with exponential backoff
            $this->release(pow(2, $this->attempts()) * 60); // 60s, 120s, etc.
        } else {
            // Mark as failed and require manual review
            $this->updateScanStatus(ScanStatus::FAILED, [
                'message' => 'Scan failed after maximum attempts',
                'error' => $reason,
                'requires_manual_review' => true
            ]);
            
            // For security, treat failed scans as potential threats
            $this->blockFileProcessing([
                'status' => ScanStatus::FAILED,
                'reason' => 'Scan failure - manual review required'
            ]);
        }
    }
    
    /**
     * Handle unexpected exceptions
     */
    private function handleException(Throwable $exception): void
    {
        $this->handleScanFailure('Unexpected error during scan', $exception);
        
        // For critical exceptions, alert immediately
        if ($this->isCriticalException($exception)) {
            Log::critical('Critical virus scan exception', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);
        }
        
        throw $exception;
    }
    
    /**
     * Update scan status with progress information
     */
    private function updateScanStatus(ScanStatus $status, array $additionalData = []): void
    {
        $statusData = array_merge([
            'job_id' => $this->jobId,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_description' => $status->description(),
            'updated_at' => now()->toISOString(),
            'file_path' => basename($this->filePath), // Only store filename for privacy
            'context' => $this->context,
            'user_id' => $this->userId
        ], $additionalData);
        
        // Store in cache for real-time access
        $cacheKey = "virus_scan_status_{$this->jobId}";
        Cache::put($cacheKey, $statusData, 3600); // 1 hour
        
        // Also store by file path for lookup
        if ($this->filePath) {
            $fileKey = "virus_scan_file_" . md5($this->filePath);
            Cache::put($fileKey, $statusData, 3600);
        }
    }
    
    /**
     * Store comprehensive scan result for analysis
     */
    private function storeScanResult(array $scanResult): void
    {
        $resultKey = "virus_scan_result_{$this->jobId}";
        
        // Add job metadata
        $scanResult['job_metadata'] = [
            'job_id' => $this->jobId,
            'user_id' => $this->userId,
            'context' => $this->context,
            'attempts' => $this->attempts(),
            'queue' => $this->queue ?? 'default',
            'completed_at' => now()->toISOString()
        ];
        
        // Store for 24 hours for analysis and debugging
        Cache::put($resultKey, $scanResult, 86400);
        
        // Store summary in daily metrics
        $this->storeDailyScanSummary($scanResult);
    }
    
    /**
     * Generate user-friendly threat message
     */
    private function generateThreatMessage(array $threats): string
    {
        if (empty($threats)) {
            return 'No threats detected';
        }
        
        $threatCount = count($threats);
        $criticalThreats = array_filter($threats, fn($t) => $t['severity'] === 'critical');
        $highThreats = array_filter($threats, fn($t) => $t['severity'] === 'high');
        
        if (!empty($criticalThreats)) {
            return "Critical security threat detected! File has been quarantined for your protection.";
        } elseif (!empty($highThreats)) {
            return "High-risk malware detected. File cannot be processed for security reasons.";
        } elseif ($threatCount > 3) {
            return "Multiple security threats detected ({$threatCount} total). File has been blocked.";
        } else {
            $threatNames = array_column($threats, 'threat_name');
            return "Security threats detected: " . implode(', ', array_slice($threatNames, 0, 3));
        }
    }
    
    /**
     * Block file from further processing
     */
    private function blockFileProcessing(array $scanResult): void
    {
        // Create a block file marker
        $blockFile = $this->filePath . '.BLOCKED';
        file_put_contents($blockFile, json_encode([
            'blocked_at' => now()->toISOString(),
            'job_id' => $this->jobId,
            'reason' => 'Virus scan detected threats',
            'scan_result' => $scanResult
        ], JSON_PRETTY_PRINT));
        
        // Add to blocked files cache
        $blockedKey = "blocked_files_" . date('Y-m-d');
        $blockedFiles = Cache::get($blockedKey, []);
        $blockedFiles[] = [
            'file_path' => basename($this->filePath),
            'blocked_at' => now()->toISOString(),
            'threat_level' => $scanResult['threat_level']->value ?? 'unknown',
            'user_id' => $this->userId
        ];
        Cache::put($blockedKey, $blockedFiles, 86400);
    }
    
    /**
     * Trigger post-scan processing for clean files
     */
    private function triggerPostScanProcessing(array $scanResult): void
    {
        // This could trigger the next step in the upload pipeline
        // For example, if this is a rack file, trigger rack analysis
        
        if ($this->context === 'rack_upload') {
            // Trigger ProcessRackFileJob
            Log::info('Triggering rack processing after clean virus scan', [
                'job_id' => $this->jobId,
                'file_path' => $this->filePath
            ]);
            
            // You would dispatch the next job here
            // ProcessRackFileJob::dispatch($rackModel);
        }
    }
    
    /**
     * Send notifications based on scan results
     */
    private function sendNotifications(array $scanResult): void
    {
        $threatLevel = $scanResult['threat_level'];
        
        if ($threatLevel->requiresNotification()) {
            $this->sendSecurityAlert($scanResult);
        }
        
        if ($this->userId && !$scanResult['is_clean']) {
            $this->sendUserNotification($scanResult);
        }
    }
    
    /**
     * Send security alert for threats
     */
    private function sendSecurityAlert(array $scanResult): void
    {
        // This would integrate with your notification system
        Log::warning('Security alert sent', [
            'job_id' => $this->jobId,
            'threat_level' => $scanResult['threat_level']->value,
            'threat_count' => count($scanResult['threats_found'])
        ]);
    }
    
    /**
     * Send notification to user
     */
    private function sendUserNotification(array $scanResult): void
    {
        // This would send user notification about scan results
        Log::info('User notification sent', [
            'job_id' => $this->jobId,
            'user_id' => $this->userId,
            'is_clean' => $scanResult['is_clean']
        ]);
    }
    
    /**
     * Update scan metrics for monitoring
     */
    private function updateScanMetrics(array $scanResult): void
    {
        $today = date('Y-m-d');
        $metricsKey = "virus_scan_metrics_{$today}";
        
        $metrics = Cache::get($metricsKey, [
            'total_scans' => 0,
            'clean_files' => 0,
            'infected_files' => 0,
            'failed_scans' => 0,
            'avg_scan_duration' => 0,
            'threat_levels' => [],
            'engines_used' => []
        ]);
        
        $metrics['total_scans']++;
        
        if ($scanResult['is_clean']) {
            $metrics['clean_files']++;
        } else {
            $metrics['infected_files']++;
        }
        
        if (isset($scanResult['scan_duration'])) {
            $metrics['avg_scan_duration'] = (
                ($metrics['avg_scan_duration'] * ($metrics['total_scans'] - 1)) + 
                $scanResult['scan_duration']
            ) / $metrics['total_scans'];
        }
        
        $threatLevel = $scanResult['threat_level']->value;
        $metrics['threat_levels'][$threatLevel] = ($metrics['threat_levels'][$threatLevel] ?? 0) + 1;
        
        foreach (array_keys($scanResult['scan_engines']) as $engine) {
            $metrics['engines_used'][$engine] = ($metrics['engines_used'][$engine] ?? 0) + 1;
        }
        
        Cache::put($metricsKey, $metrics, 86400);
    }
    
    /**
     * Store daily scan summary
     */
    private function storeDailyScanSummary(array $scanResult): void
    {
        $summaryKey = "virus_scan_summary_" . date('Y-m-d');
        $summaries = Cache::get($summaryKey, []);
        
        $summaries[] = [
            'job_id' => $this->jobId,
            'timestamp' => now()->toISOString(),
            'is_clean' => $scanResult['is_clean'],
            'threat_level' => $scanResult['threat_level']->value,
            'threat_count' => count($scanResult['threats_found']),
            'scan_duration' => $scanResult['scan_duration'],
            'engines_used' => array_keys($scanResult['scan_engines']),
            'context' => $this->context,
            'user_id' => $this->userId
        ];
        
        Cache::put($summaryKey, $summaries, 86400);
    }
    
    /**
     * Increment user security violations counter
     */
    private function incrementUserViolations(int $userId, ThreatLevel $threatLevel): void
    {
        $violationsKey = "user_security_violations_{$userId}";
        $violations = Cache::get($violationsKey, [
            'total' => 0,
            'by_level' => [],
            'last_violation' => null
        ]);
        
        $violations['total']++;
        $violations['by_level'][$threatLevel->value] = ($violations['by_level'][$threatLevel->value] ?? 0) + 1;
        $violations['last_violation'] = now()->toISOString();
        
        // Store for 30 days
        Cache::put($violationsKey, $violations, 2592000);
        
        // If too many violations, flag user for review
        if ($violations['total'] >= 5) {
            Log::warning('User flagged for security review', [
                'user_id' => $userId,
                'total_violations' => $violations['total'],
                'violations_by_level' => $violations['by_level']
            ]);
        }
    }
    
    /**
     * Check if user is VIP for priority processing
     */
    private function isVipUser(?int $userId): bool
    {
        // This would check user's premium status or admin role
        return false; // Placeholder implementation
    }
    
    /**
     * Check if exception is critical
     */
    private function isCriticalException(Throwable $exception): bool
    {
        $criticalExceptions = [
            'OutOfMemoryError',
            'FatalError',
            'ParseError'
        ];
        
        return in_array(get_class($exception), $criticalExceptions);
    }
    
    /**
     * Handle job failure at the Laravel level
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Virus scan job failed permanently', [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'user_id' => $this->userId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
        
        $this->updateScanStatus(ScanStatus::FAILED, [
            'error' => $exception->getMessage(),
            'failed_permanently' => true,
            'requires_manual_review' => true
        ]);
        
        // Block file as a precaution
        $this->blockFileProcessing([
            'status' => ScanStatus::FAILED,
            'reason' => 'Job failed permanently: ' . $exception->getMessage()
        ]);
    }
    
    /**
     * Get unique tags for this job
     */
    public function tags(): array
    {
        return [
            'virus_scanning',
            'security',
            'job_' . $this->jobId,
            'context_' . $this->context,
            $this->userId ? 'user_' . $this->userId : 'anonymous'
        ];
    }
    
    /**
     * Get the job ID
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }
}