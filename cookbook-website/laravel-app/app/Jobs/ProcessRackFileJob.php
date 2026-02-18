<?php

namespace App\Jobs;

use App\Models\Rack;
use App\Models\JobExecution;
use App\Services\RackProcessingService;
use App\Services\JobNotificationService;
use App\Enums\RackProcessingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Throwable;

/**
 * Comprehensive rack processing job with robust error handling and recovery
 * 
 * This job implements a complete failure recovery architecture with:
 * - Detailed progress tracking
 * - Intelligent retry strategies  
 * - User-friendly error messaging
 * - Automatic escalation for critical failures
 * - Performance monitoring and optimization
 */
class ProcessRackFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes max
    public $tries = 1; // We handle retries manually for better control
    public $maxExceptions = 1;
    public $failOnTimeout = true;

    private string $jobId;
    private ?JobExecution $jobExecution = null;

    public function __construct(public Rack $rack)
    {
        // Generate unique job ID for tracking
        $this->jobId = (string) Str::uuid();
    }

    /**
     * Handle the job execution with comprehensive error handling
     */
    public function handle(): void
    {
        try {
            // Resolve services manually to avoid dependency issues during initial setup
            $processingService = app(RackProcessingService::class);
            $notificationService = app()->bound(JobNotificationService::class) ?
                app(JobNotificationService::class) : null;

            // Initialize or retrieve job execution record
            $this->jobExecution = $this->getOrCreateJobExecution();

            Log::info('Starting comprehensive rack processing', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'user_id' => $this->rack->user_id,
                'filename' => $this->rack->original_filename,
                'attempt' => $this->attempts() + 1
            ]);

            // Update rack status to analyzing
            $this->updateRackStatus(RackProcessingStatus::ANALYZING);

            // Get the file path from storage
            $filePath = Storage::disk('private')->path($this->rack->file_path);

            if (!file_exists($filePath)) {
                throw new Exception('Rack file not found: ' . $this->rack->file_path);
            }

            // Analyze the rack file
            $result = $this->analyzeExistingRack($filePath, $processingService);

            if ($result['success']) {
                $this->handleJobSuccess($result, $notificationService);
            } else {
                $this->handleJobFailure($result, $processingService, $notificationService);
            }
        } catch (Throwable $exception) {
            // Handle any unexpected exceptions
            $this->handleUnexpectedFailure($exception, $notificationService);
        }
    }

    /**
     * Analyze an existing rack file
     */
    private function analyzeExistingRack(string $filePath, RackProcessingService $processingService): array
    {
        $startTime = microtime(true);

        try {
            // Check if it's a drum rack
            $drumAnalyzer = app(\App\Services\DrumRackAnalyzerService::class);
            $isDrumRack = $drumAnalyzer->isDrumRack($filePath);

            if ($isDrumRack) {
                // Use specialized drum rack analyzer
                $analysisResult = $drumAnalyzer->analyzeDrumRack($filePath, [
                    'include_performance' => true,
                    'verbose' => false
                ]);

                if ($analysisResult['success']) {
                    $data = $analysisResult['data'];

                    // Update rack with drum rack analysis results
                    $this->rack->update([
                        'rack_type' => 'drum_rack',
                        'category' => 'drums',
                        'ableton_version' => $data['ableton_version'] ?? null,
                        'macro_controls' => $data['macro_controls'] ?? [],
                        'version_details' => $data['version_details'] ?? [],
                        'parsing_errors' => $data['parsing_errors'] ?? [],
                        'parsing_warnings' => $data['parsing_warnings'] ?? [],
                        'analysis_complete' => true,
                        'status' => empty($data['parsing_errors']) ? 'approved' : 'pending',
                        'published_at' => empty($data['parsing_errors']) ? now() : null,
                    ]);
                }
            } else {
                // Use general rack analyzer
                $analyzer = new \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer();
                $xml = $analyzer::decompressAndParseAbletonFile($filePath);

                if (!$xml) {
                    throw new Exception('Failed to decompress or parse the .adg file');
                }

                $analysisResult = $analyzer::parseChainsAndDevices($xml, $filePath);

                if ($analysisResult) {
                    // Update rack with analysis results  
                    $this->rack->update([
                        'rack_type' => $analysisResult['rack_type'] ?? 'Unknown',
                        'device_count' => count($analysisResult['chains'][0]['devices'] ?? []),
                        'chain_count' => count($analysisResult['chains'] ?? []),
                        'ableton_version' => $analysisResult['ableton_version'] ?? null,
                        'macro_controls' => $analysisResult['macro_controls'] ?? [],
                        'devices' => $analysisResult['chains'] ?? [],
                        'chains' => $analysisResult['chains'] ?? [],
                        'version_details' => $analysisResult['version_details'] ?? [],
                        'parsing_errors' => $analysisResult['parsing_errors'] ?? [],
                        'parsing_warnings' => $analysisResult['parsing_warnings'] ?? [],
                        'analysis_complete' => true,
                        'status' => empty($analysisResult['parsing_errors']) ? 'approved' : 'pending',
                        'published_at' => empty($analysisResult['parsing_errors']) ? now() : null,
                    ]);
                }
            }

            $processingTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'processing_time' => $processingTime,
                'is_drum_rack' => $isDrumRack ?? false,
            ];
        } catch (Exception $e) {
            $this->rack->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful job completion
     */
    private function handleJobSuccess(array $result, ?JobNotificationService $notificationService): void
    {
        Log::info('Rack processing completed successfully', [
            'rack_id' => $this->rack->id,
            'job_id' => $this->jobId,
            'processing_time' => $result['processing_time'] ?? null
        ]);

        // Send success notification to user if service is available
        if ($notificationService) {
            try {
                $notificationService->sendCompletionNotification(
                    $this->rack->user_id,
                    $this->jobId,
                    'Rack Processing Complete!',
                    'Your rack "' . $this->rack->title . '" has been successfully processed and is ready for metadata entry.'
                );
            } catch (Throwable $e) {
                Log::warning('Failed to send completion notification', [
                    'rack_id' => $this->rack->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle job failure (not retry-scheduled)
     */
    private function handleJobFailure(array $result, RackProcessingService $processingService, ?JobNotificationService $notificationService): void
    {
        if ($result['permanently_failed'] ?? false) {
            Log::error('Rack processing permanently failed', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'failure_category' => $result['failure_category'] ?? 'unknown'
            ]);

            // Update rack to permanently failed status
            $this->updateRackStatus(RackProcessingStatus::PERMANENTLY_FAILED);

            // Send failure notification if service is available
            if ($notificationService) {
                try {
                    $notificationService->sendFailureNotification(
                        $this->rack->user_id,
                        $this->jobId,
                        'Rack Processing Failed',
                        $result['user_message'] ?? 'We encountered an issue processing your rack. Please try uploading again or contact support.'
                    );
                } catch (Throwable $e) {
                    Log::warning('Failed to send failure notification', [
                        'rack_id' => $this->rack->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Retry is scheduled - this is handled by RackProcessingService
            Log::info('Rack processing retry scheduled', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'next_retry_at' => $result['next_retry_at'] ?? null
            ]);
        }
    }

    /**
     * Handle unexpected failure that bypassed normal error handling
     */
    private function handleUnexpectedFailure(Throwable $exception, ?JobNotificationService $notificationService): void
    {
        Log::error('Unexpected failure in ProcessRackFileJob', [
            'rack_id' => $this->rack->id,
            'job_id' => $this->jobId,
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString()
        ]);

        try {
            // Try to handle through the processing service
            if ($this->jobExecution) {
                $processingService = app(RackProcessingService::class);
                $result = $processingService->handleProcessingFailure($this->jobExecution, $exception);
                $this->handleJobFailure($result, $processingService, $notificationService);
            } else {
                // Fall back to basic error handling
                $this->updateRackStatus(RackProcessingStatus::PERMANENTLY_FAILED);
            }
        } catch (Throwable $secondaryException) {
            Log::critical('Secondary failure in error handling', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'original_exception' => $exception->getMessage(),
                'secondary_exception' => $secondaryException->getMessage()
            ]);

            // Fall back to basic error handling
            $this->updateRackStatus(RackProcessingStatus::PERMANENTLY_FAILED);

            // Try to send a basic failure notification
            if ($notificationService) {
                try {
                    $notificationService->sendFailureNotification(
                        $this->rack->user_id,
                        $this->jobId,
                        'Rack Processing Failed',
                        'We encountered a critical error processing your rack. Please contact support for assistance.'
                    );
                } catch (Throwable $notificationException) {
                    Log::critical('Failed to send failure notification', [
                        'rack_id' => $this->rack->id,
                        'job_id' => $this->jobId,
                        'notification_error' => $notificationException->getMessage()
                    ]);
                }
            }
        }

        // Always throw the original exception to trigger Laravel's failure handling
        throw $exception;
    }

    /**
     * Get or create job execution record for tracking
     */
    private function getOrCreateJobExecution(): JobExecution
    {
        // Try to find existing job execution by ID
        $jobExecution = JobExecution::where('job_id', $this->jobId)->first();

        if (!$jobExecution) {
            // Check if there's an existing job for this rack
            $existingJob = JobExecution::where('model_id', $this->rack->id)
                ->where('model_type', Rack::class)
                ->where('job_class', static::class)
                ->whereIn('status', ['processing', 'retry_scheduled', 'queued'])
                ->first();

            if ($existingJob) {
                // Use existing job execution
                $this->jobId = $existingJob->job_id;
                return $existingJob;
            }

            // Create new job execution record
            $jobExecution = JobExecution::create([
                'job_id' => $this->jobId,
                'job_class' => static::class,
                'queue' => $this->queue ?? 'default',
                'model_id' => $this->rack->id,
                'model_type' => Rack::class,
                'status' => 'queued',
                'queued_at' => now(),
                'max_attempts' => 3, // Will be updated by retry strategy
                'payload' => ['rack_id' => $this->rack->id],
                'tags' => ['rack_processing', 'user_' . $this->rack->user_id],
                'metadata' => [
                    'user_id' => $this->rack->user_id,
                    'filename' => $this->rack->original_filename,
                    'file_size' => $this->getFileSize(),
                    'priority' => 'normal'
                ]
            ]);
        }

        return $jobExecution;
    }

    /**
     * Update rack status with proper state transition validation
     */
    private function updateRackStatus(RackProcessingStatus $newStatus): void
    {
        try {
            $currentStatus = RackProcessingStatus::from($this->rack->processing_status ?? 'uploaded');

            if (!$currentStatus->canTransitionTo($newStatus)) {
                Log::warning('Invalid rack status transition attempted', [
                    'rack_id' => $this->rack->id,
                    'job_id' => $this->jobId,
                    'from' => $currentStatus->value,
                    'to' => $newStatus->value
                ]);
                return;
            }

            $this->rack->update([
                'processing_status' => $newStatus->value,
                'current_job_id' => $this->jobId,
                'processing_progress' => $newStatus->progressPercentage(),
                'processing_stage' => $newStatus->label(),
                'processing_message' => $newStatus->description()
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to update rack status', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'status' => $newStatus->value,
                'error' => $e->getMessage()
            ]);

            // Fall back to updating basic status
            try {
                $basicStatus = match ($newStatus) {
                    RackProcessingStatus::ANALYZING => 'processing',
                    RackProcessingStatus::ANALYSIS_COMPLETE => 'pending',
                    RackProcessingStatus::PERMANENTLY_FAILED => 'failed',
                    default => 'processing'
                };

                $this->rack->update(['status' => $basicStatus]);
            } catch (Throwable $fallbackError) {
                Log::critical('Failed to update basic rack status', [
                    'rack_id' => $this->rack->id,
                    'error' => $fallbackError->getMessage()
                ]);
            }
        }
    }

    /**
     * Get rack file size for metadata
     */
    private function getFileSize(): ?int
    {
        try {
            return Storage::disk('private')->size($this->rack->file_path);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Handle job failure at the Laravel level
     * 
     * This method is called by Laravel when the job fails after all retries are exhausted.
     * Since we handle retries manually, this should only be called for truly unrecoverable failures.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessRackFileJob failed at Laravel level', [
            'rack_id' => $this->rack->id,
            'job_id' => $this->jobId,
            'exception_type' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        try {
            // Update job execution record
            if ($this->jobExecution) {
                $this->jobExecution->update([
                    'status' => 'permanently_failed',
                    'failed_at' => now(),
                    'failure_reason' => $exception->getMessage(),
                    'stack_trace' => $exception->getTraceAsString()
                ]);
            }

            // Update rack status
            $this->updateRackStatus(RackProcessingStatus::PERMANENTLY_FAILED);

            // Update basic rack error info for backward compatibility
            $this->rack->update([
                'status' => 'failed',
                'processing_error' => $exception->getMessage()
            ]);
        } catch (Throwable $e) {
            Log::critical('Failed to handle job failure cleanup', [
                'rack_id' => $this->rack->id,
                'job_id' => $this->jobId,
                'cleanup_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the unique job ID for this execution
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Get the number of times this job has been attempted
     */
    public function attempts(): int
    {
        if ($this->jobExecution && $this->jobExecution->attempts !== null) {
            return (int) $this->jobExecution->attempts;
        }

        // Use the job property from InteractsWithQueue trait
        if (isset($this->job) && $this->job !== null) {
            $attempts = $this->job->attempts();
            return $attempts !== null ? (int) $attempts : 0;
        }

        return 0;
    }

    /**
     * Determine if the job should fail on timeout
     */
    public function shouldFailOnTimeout(): bool
    {
        return true;
    }

    /**
     * Get job tags for identification and filtering
     */
    public function tags(): array
    {
        return [
            'rack_processing',
            'user_' . $this->rack->user_id,
            'rack_' . $this->rack->id,
            'job_' . $this->jobId
        ];
    }

    /**
     * Get middleware that should be applied to the job
     */
    public function middleware(): array
    {
        return [
            // Could add rate limiting, authentication checks, etc.
        ];
    }
}
