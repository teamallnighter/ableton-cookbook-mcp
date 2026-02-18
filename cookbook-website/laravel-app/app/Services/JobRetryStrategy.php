<?php

namespace App\Services;

use App\Enums\FailureCategory;
use App\Models\JobExecution;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Intelligent retry strategy service for failed background jobs
 * 
 * This service implements sophisticated retry logic with exponential backoff,
 * category-specific retry limits, and failure escalation mechanisms.
 */
class JobRetryStrategy
{
    /**
     * Base retry delays (in seconds) by failure category
     */
    private const BASE_DELAYS = [
        FailureCategory::NETWORK_ERROR->value => 30,
        FailureCategory::TIMEOUT->value => 60,
        FailureCategory::MEMORY_LIMIT->value => 300,
        FailureCategory::DISK_SPACE->value => 600,
        FailureCategory::SERVICE_UNAVAILABLE->value => 120,
        FailureCategory::TEMPORARY_FILE_ACCESS->value => 60,
        FailureCategory::ANALYSIS_ERROR->value => 30,
        FailureCategory::DATABASE_ERROR->value => 30,
        FailureCategory::SYSTEM_ERROR->value => 300,
        FailureCategory::DEPENDENCY_ERROR->value => 60,
        FailureCategory::UNKNOWN_ERROR->value => 60,
    ];
    
    /**
     * Maximum retry attempts by failure category
     */
    private const MAX_RETRIES = [
        FailureCategory::NETWORK_ERROR->value => 5,
        FailureCategory::TIMEOUT->value => 3,
        FailureCategory::MEMORY_LIMIT->value => 2,
        FailureCategory::DISK_SPACE->value => 10,
        FailureCategory::SERVICE_UNAVAILABLE->value => 8,
        FailureCategory::TEMPORARY_FILE_ACCESS->value => 5,
        FailureCategory::ANALYSIS_ERROR->value => 3,
        FailureCategory::DATABASE_ERROR->value => 5,
        FailureCategory::SYSTEM_ERROR->value => 2,
        FailureCategory::DEPENDENCY_ERROR->value => 3,
        FailureCategory::UNKNOWN_ERROR->value => 3,
    ];
    
    /**
     * Exponential backoff multipliers by failure category
     */
    private const BACKOFF_MULTIPLIERS = [
        FailureCategory::NETWORK_ERROR->value => 2.0,
        FailureCategory::TIMEOUT->value => 2.5,
        FailureCategory::MEMORY_LIMIT->value => 1.5,
        FailureCategory::DISK_SPACE->value => 1.2,
        FailureCategory::SERVICE_UNAVAILABLE->value => 2.0,
        FailureCategory::TEMPORARY_FILE_ACCESS->value => 1.8,
        FailureCategory::ANALYSIS_ERROR->value => 2.0,
        FailureCategory::DATABASE_ERROR->value => 2.0,
        FailureCategory::SYSTEM_ERROR->value => 3.0,
        FailureCategory::DEPENDENCY_ERROR->value => 2.0,
        FailureCategory::UNKNOWN_ERROR->value => 2.0,
    ];
    
    /**
     * Maximum retry delay caps (in seconds) to prevent extremely long delays
     */
    private const MAX_DELAY_CAPS = [
        FailureCategory::NETWORK_ERROR->value => 1800,   // 30 minutes
        FailureCategory::TIMEOUT->value => 3600,         // 1 hour
        FailureCategory::MEMORY_LIMIT->value => 7200,    // 2 hours
        FailureCategory::DISK_SPACE->value => 14400,     // 4 hours
        FailureCategory::SERVICE_UNAVAILABLE->value => 3600, // 1 hour
        FailureCategory::TEMPORARY_FILE_ACCESS->value => 1800, // 30 minutes
        FailureCategory::ANALYSIS_ERROR->value => 1800,  // 30 minutes
        FailureCategory::DATABASE_ERROR->value => 900,   // 15 minutes
        FailureCategory::SYSTEM_ERROR->value => 7200,    // 2 hours
        FailureCategory::DEPENDENCY_ERROR->value => 3600, // 1 hour
        FailureCategory::UNKNOWN_ERROR->value => 3600,   // 1 hour
    ];
    
    /**
     * Determine if a job should be retried based on failure category and attempt count
     */
    public function shouldRetry(FailureCategory $category, int $currentAttempts): bool
    {
        // Non-retryable categories
        if (!$category->isRetryable()) {
            Log::info('Job not retried due to non-retryable failure category', [
                'category' => $category->value,
                'attempts' => $currentAttempts
            ]);
            return false;
        }
        
        $maxRetries = $this->getMaxRetries($category);
        
        if ($currentAttempts >= $maxRetries) {
            Log::info('Job not retried due to max attempts exceeded', [
                'category' => $category->value,
                'attempts' => $currentAttempts,
                'max_retries' => $maxRetries
            ]);
            return false;
        }
        
        // Check system-wide retry limits
        if ($this->isSystemOverloaded()) {
            Log::warning('Job not retried due to system overload', [
                'category' => $category->value,
                'attempts' => $currentAttempts
            ]);
            return false;
        }
        
        // Check category-specific retry conditions
        if ($this->isCategoryThrottled($category)) {
            Log::warning('Job not retried due to category throttling', [
                'category' => $category->value,
                'attempts' => $currentAttempts
            ]);
            return false;
        }
        
        Log::info('Job approved for retry', [
            'category' => $category->value,
            'attempts' => $currentAttempts,
            'max_retries' => $maxRetries
        ]);
        
        return true;
    }
    
    /**
     * Calculate retry delay with exponential backoff and jitter
     */
    public function getRetryDelay(FailureCategory $category, int $attemptNumber): int
    {
        $baseDelay = self::BASE_DELAYS[$category->value] ?? 60;
        $multiplier = self::BACKOFF_MULTIPLIERS[$category->value] ?? 2.0;
        $maxDelay = self::MAX_DELAY_CAPS[$category->value] ?? 3600;
        
        // Exponential backoff: base_delay * (multiplier ^ attempt_number)
        $exponentialDelay = $baseDelay * pow($multiplier, $attemptNumber - 1);
        
        // Apply maximum delay cap
        $cappedDelay = min($exponentialDelay, $maxDelay);
        
        // Add jitter (±20%) to prevent thundering herd
        $jitterRange = $cappedDelay * 0.2;
        $jitter = mt_rand(-$jitterRange, $jitterRange);
        $finalDelay = max(1, $cappedDelay + $jitter);
        
        // Apply system load factor
        $loadFactor = $this->getSystemLoadFactor();
        $finalDelay *= $loadFactor;
        
        Log::info('Calculated retry delay', [
            'category' => $category->value,
            'attempt' => $attemptNumber,
            'base_delay' => $baseDelay,
            'exponential_delay' => $exponentialDelay,
            'capped_delay' => $cappedDelay,
            'jitter' => $jitter,
            'load_factor' => $loadFactor,
            'final_delay' => $finalDelay
        ]);
        
        return (int) $finalDelay;
    }
    
    /**
     * Get maximum retry attempts for a failure category
     */
    public function getMaxRetries(FailureCategory $category): int
    {
        return self::MAX_RETRIES[$category->value] ?? 0;
    }
    
    /**
     * Determine if retry should be expedited for critical failures
     */
    public function shouldExpediteRetry(FailureCategory $category, int $attemptNumber): bool
    {
        // Expedite retries for certain critical categories on early attempts
        if ($attemptNumber <= 2) {
            return in_array($category, [
                FailureCategory::DATABASE_ERROR,
                FailureCategory::NETWORK_ERROR,
                FailureCategory::TEMPORARY_FILE_ACCESS,
            ]);
        }
        
        return false;
    }
    
    /**
     * Get optimized retry settings for a specific job execution
     */
    public function getOptimizedRetrySettings(JobExecution $jobExecution, FailureCategory $category): array
    {
        $baseSettings = [
            'should_retry' => $this->shouldRetry($category, $jobExecution->attempts),
            'retry_delay' => $this->getRetryDelay($category, $jobExecution->attempts + 1),
            'max_retries' => $this->getMaxRetries($category),
            'expedited' => $this->shouldExpediteRetry($category, $jobExecution->attempts + 1),
        ];
        
        // Apply job-specific optimizations
        $optimizedSettings = $this->applyJobSpecificOptimizations($jobExecution, $category, $baseSettings);
        
        return $optimizedSettings;
    }
    
    /**
     * Apply job-specific optimizations based on history and context
     */
    private function applyJobSpecificOptimizations(
        JobExecution $jobExecution,
        FailureCategory $category,
        array $baseSettings
    ): array {
        $optimized = $baseSettings;
        
        // Reduce delay for jobs that previously succeeded quickly
        if ($this->hasQuickSuccessHistory($jobExecution)) {
            $optimized['retry_delay'] = max(
                30, 
                $optimized['retry_delay'] * 0.5
            );
            $optimized['optimizations'][] = 'quick_success_history';
        }
        
        // Increase delay for jobs with consistent failures
        if ($this->hasConsistentFailurePattern($jobExecution)) {
            $optimized['retry_delay'] *= 2;
            $optimized['optimizations'][] = 'consistent_failure_pattern';
        }
        
        // Adjust based on file size (for rack processing)
        if ($this->isLargeFileJob($jobExecution)) {
            $optimized['retry_delay'] *= 1.5;
            $optimized['max_retries'] = max(1, $optimized['max_retries'] - 1);
            $optimized['optimizations'][] = 'large_file_adjustment';
        }
        
        // Priority adjustment for premium users
        if ($this->isPriorityUser($jobExecution)) {
            $optimized['retry_delay'] = max(
                10,
                $optimized['retry_delay'] * 0.7
            );
            $optimized['expedited'] = true;
            $optimized['optimizations'][] = 'priority_user';
        }
        
        return $optimized;
    }
    
    /**
     * Check if the system is overloaded and should throttle retries
     */
    private function isSystemOverloaded(): bool
    {
        // Get current retry queue depth
        $retryQueueDepth = $this->getRetryQueueDepth();
        
        // Check if retry queue is too deep
        if ($retryQueueDepth > 1000) {
            return true;
        }
        
        // Check system resource utilization
        $cpuUsage = $this->getCurrentCpuUsage();
        $memoryUsage = $this->getCurrentMemoryUsage();
        
        if ($cpuUsage > 85 || $memoryUsage > 90) {
            return true;
        }
        
        // Check error rate in last hour
        $recentErrorRate = $this->getRecentErrorRate();
        if ($recentErrorRate > 0.25) { // 25% error rate threshold
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a specific failure category is throttled
     */
    private function isCategoryThrottled(FailureCategory $category): bool
    {
        // Get recent failure count for this category
        $recentFailures = $this->getRecentCategoryFailures($category, 3600); // last hour
        
        // Category-specific throttling thresholds
        $throttleThresholds = [
            FailureCategory::DISK_SPACE->value => 50,
            FailureCategory::MEMORY_LIMIT->value => 30,
            FailureCategory::SYSTEM_ERROR->value => 20,
            FailureCategory::DATABASE_ERROR->value => 100,
        ];
        
        $threshold = $throttleThresholds[$category->value] ?? 200;
        
        return $recentFailures > $threshold;
    }
    
    /**
     * Get system load factor to adjust retry delays
     */
    private function getSystemLoadFactor(): float
    {
        $queueDepth = $this->getRetryQueueDepth();
        
        // Increase delays when queue is deep
        if ($queueDepth > 500) {
            return 2.0;
        } elseif ($queueDepth > 200) {
            return 1.5;
        } elseif ($queueDepth > 100) {
            return 1.2;
        }
        
        return 1.0;
    }
    
    /**
     * Check if job has a history of quick successes
     */
    private function hasQuickSuccessHistory(JobExecution $jobExecution): bool
    {
        // Check success rate for similar jobs from this user
        $successRate = $this->getUserJobSuccessRate($jobExecution->metadata['user_id'] ?? null);
        return $successRate > 0.9; // 90% success rate
    }
    
    /**
     * Check if job has consistent failure pattern
     */
    private function hasConsistentFailurePattern(JobExecution $jobExecution): bool
    {
        // Check if similar jobs from this user consistently fail
        $recentFailureRate = $this->getUserRecentFailureRate($jobExecution->metadata['user_id'] ?? null);
        return $recentFailureRate > 0.7; // 70% failure rate in recent jobs
    }
    
    /**
     * Check if this is a large file processing job
     */
    private function isLargeFileJob(JobExecution $jobExecution): bool
    {
        $fileSize = $jobExecution->metadata['file_size'] ?? 0;
        return $fileSize > 10 * 1024 * 1024; // 10MB threshold
    }
    
    /**
     * Check if this is a priority user
     */
    private function isPriorityUser(JobExecution $jobExecution): bool
    {
        // Implementation would check user's subscription level or priority status
        return $jobExecution->metadata['priority'] === 'high';
    }
    
    /**
     * Get current retry queue depth
     */
    private function getRetryQueueDepth(): int
    {
        return JobExecution::where('status', 'retry_scheduled')
            ->where('next_retry_at', '<=', Carbon::now()->addHours(1))
            ->count();
    }
    
    /**
     * Get current CPU usage percentage
     */
    private function getCurrentCpuUsage(): float
    {
        // Simplified implementation - in production, use actual system metrics
        return 0.0;
    }
    
    /**
     * Get current memory usage percentage
     */
    private function getCurrentMemoryUsage(): float
    {
        // Simplified implementation - in production, use actual system metrics
        return 0.0;
    }
    
    /**
     * Get recent error rate
     */
    private function getRecentErrorRate(): float
    {
        $totalJobs = JobExecution::where('created_at', '>=', Carbon::now()->subHour())->count();
        
        if ($totalJobs === 0) {
            return 0.0;
        }
        
        $failedJobs = JobExecution::where('created_at', '>=', Carbon::now()->subHour())
            ->where('status', 'failed')
            ->count();
            
        return $failedJobs / $totalJobs;
    }
    
    /**
     * Get recent failure count for a specific category
     */
    private function getRecentCategoryFailures(FailureCategory $category, int $seconds): int
    {
        return JobExecution::where('failure_category', $category->value)
            ->where('failed_at', '>=', Carbon::now()->subSeconds($seconds))
            ->count();
    }
    
    /**
     * Get success rate for a specific user
     */
    private function getUserJobSuccessRate(?int $userId): float
    {
        if (!$userId) {
            return 0.5; // Default neutral rate
        }
        
        $totalJobs = JobExecution::whereJsonContains('metadata->user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
            
        if ($totalJobs < 5) {
            return 0.5; // Not enough data
        }
        
        $successfulJobs = JobExecution::whereJsonContains('metadata->user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->where('status', 'completed')
            ->count();
            
        return $successfulJobs / $totalJobs;
    }
    
    /**
     * Get recent failure rate for a specific user
     */
    private function getUserRecentFailureRate(?int $userId): float
    {
        if (!$userId) {
            return 0.0;
        }
        
        $recentJobs = JobExecution::whereJsonContains('metadata->user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(1))
            ->count();
            
        if ($recentJobs < 3) {
            return 0.0; // Not enough recent data
        }
        
        $recentFailures = JobExecution::whereJsonContains('metadata->user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(1))
            ->where('status', 'failed')
            ->count();
            
        return $recentFailures / $recentJobs;
    }
    
    /**
     * Get retry statistics for monitoring and analysis
     */
    public function getRetryStatistics(): array
    {
        $stats = [];
        
        // Overall retry statistics
        $stats['overall'] = [
            'total_retries_scheduled' => JobExecution::where('status', 'retry_scheduled')->count(),
            'total_retries_in_progress' => JobExecution::where('status', 'processing')
                ->where('attempts', '>', 1)->count(),
            'average_retry_delay' => JobExecution::where('status', 'retry_scheduled')
                ->avg('retry_delay'),
        ];
        
        // Category-specific statistics
        foreach (FailureCategory::cases() as $category) {
            if ($category->isRetryable()) {
                $stats['by_category'][$category->value] = [
                    'pending_retries' => JobExecution::where('failure_category', $category->value)
                        ->where('status', 'retry_scheduled')->count(),
                    'success_rate_after_retry' => $this->getCategoryRetrySuccessRate($category),
                    'average_attempts_to_success' => $this->getCategoryAverageAttemptsToSuccess($category),
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Get retry success rate for a specific category
     */
    private function getCategoryRetrySuccessRate(FailureCategory $category): float
    {
        $retriedJobs = JobExecution::where('failure_category', $category->value)
            ->where('attempts', '>', 1)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
            
        if ($retriedJobs === 0) {
            return 0.0;
        }
        
        $successfulRetries = JobExecution::where('failure_category', $category->value)
            ->where('attempts', '>', 1)
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();
            
        return $successfulRetries / $retriedJobs;
    }
    
    /**
     * Get average attempts to success for a category
     */
    private function getCategoryAverageAttemptsToSuccess(FailureCategory $category): float
    {
        return JobExecution::where('failure_category', $category->value)
            ->where('status', 'completed')
            ->where('attempts', '>', 1)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->avg('attempts') ?? 0.0;
    }
}