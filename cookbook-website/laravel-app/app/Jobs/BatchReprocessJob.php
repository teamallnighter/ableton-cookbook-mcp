<?php

namespace App\Jobs;

use App\Models\Rack;
use App\Models\User;
use App\Services\EnhancedRackAnalysisService;
use App\Services\BatchReprocessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class BatchReprocessJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $maxExceptions = 3;

    public array $rackUuids;
    public int $userId;
    public string $batchId;
    public string $priority;
    public bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(array $rackUuids, int $userId, string $batchId, string $priority = 'normal', bool $force = false)
    {
        $this->rackUuids = $rackUuids;
        $this->userId = $userId;
        $this->batchId = $batchId;
        $this->priority = $priority;
        $this->force = $force;

        // Set queue priority
        $this->onQueue("batch-reprocess-{$priority}");
    }

    /**
     * Execute the job.
     */
    public function handle(EnhancedRackAnalysisService $analysisService, BatchReprocessService $batchService): void
    {
        Log::info('Starting batch reprocess job', [
            'batch_id' => $this->batchId,
            'rack_count' => count($this->rackUuids),
            'priority' => $this->priority,
            'force' => $this->force
        ]);

        $user = User::find($this->userId);
        if (!$user) {
            Log::error('User not found for batch job', [
                'batch_id' => $this->batchId,
                'user_id' => $this->userId
            ]);
            return;
        }

        // Mark batch as started - using reflection to access private method
        try {
            $reflection = new \ReflectionClass($batchService);
            $getBatchMethod = $reflection->getMethod('getBatchRecord');
            $getBatchMethod->setAccessible(true);
            $updateBatchMethod = $reflection->getMethod('updateBatchRecord');
            $updateBatchMethod->setAccessible(true);

            $batch = $getBatchMethod->invoke($batchService, $this->batchId);
            if ($batch) {
                $batch['started_at'] = now()->toISOString();
                $batch['status'] = 'processing';
                $updateBatchMethod->invoke($batchService, $this->batchId, $batch);
            }
        } catch (\Exception $e) {
            Log::warning('Could not update batch status', ['error' => $e->getMessage()]);
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($this->rackUuids as $rackUuid) {
            try {
                $rack = Rack::where('uuid', $rackUuid)->first();

                if (!$rack) {
                    throw new Exception("Rack not found: {$rackUuid}");
                }

                // Verify user can process this rack
                if (!$user->hasRole('admin') && $rack->user_id !== $user->id) {
                    throw new Exception("Unauthorized to process rack: {$rackUuid}");
                }

                Log::info('Processing rack in batch', [
                    'batch_id' => $this->batchId,
                    'rack_uuid' => $rackUuid,
                    'force' => $this->force
                ]);

                // Perform enhanced analysis
                $result = $analysisService->analyzeRack($rack, $this->force);

                // Update batch progress
                $batchService->updateBatchProgress($this->batchId, $rackUuid, [
                    'success' => true,
                    'constitutional_compliant' => $result['constitutional_compliant'] ?? false,
                    'analysis_duration_ms' => $result['analysis_duration_ms'] ?? 0,
                    'nested_chains_detected' => $result['nested_chains_detected'] ?? 0
                ]);

                $successCount++;

                Log::info('Rack processed successfully in batch', [
                    'batch_id' => $this->batchId,
                    'rack_uuid' => $rackUuid,
                    'constitutional_compliant' => $result['constitutional_compliant'] ?? false,
                    'chains_detected' => $result['nested_chains_detected'] ?? 0
                ]);

            } catch (Exception $e) {
                // Update batch progress with failure
                $batchService->updateBatchProgress($this->batchId, $rackUuid, [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'constitutional_compliant' => false
                ]);

                $failureCount++;

                Log::error('Failed to process rack in batch', [
                    'batch_id' => $this->batchId,
                    'rack_uuid' => $rackUuid,
                    'error' => $e->getMessage()
                ]);
            }

            // Small delay to prevent overwhelming the system
            usleep(100000); // 100ms delay
        }

        // Complete the batch
        $batchService->completeBatch($this->batchId);

        Log::info('Batch reprocess job completed', [
            'batch_id' => $this->batchId,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total_processed' => count($this->rackUuids)
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('Batch reprocess job failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark all remaining racks as failed
        $batchService = app(BatchReprocessService::class);

        foreach ($this->rackUuids as $rackUuid) {
            $batchService->updateBatchProgress($this->batchId, $rackUuid, [
                'success' => false,
                'error' => 'Job failed: ' . $exception->getMessage(),
                'constitutional_compliant' => false
            ]);
        }

        // Mark batch as failed
        try {
            $reflection = new \ReflectionClass($batchService);
            $getBatchMethod = $reflection->getMethod('getBatchRecord');
            $getBatchMethod->setAccessible(true);
            $updateBatchMethod = $reflection->getMethod('updateBatchRecord');
            $updateBatchMethod->setAccessible(true);

            $batch = $getBatchMethod->invoke($batchService, $this->batchId);
            if ($batch) {
                $batch['status'] = 'failed';
                $batch['failed_at'] = now()->toISOString();
                $batch['failure_reason'] = $exception->getMessage();
                $updateBatchMethod->invoke($batchService, $this->batchId, $batch);
            }
        } catch (\Exception $e) {
            Log::warning('Could not update failed batch status', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get job display name
     */
    public function displayName(): string
    {
        return "Batch Reprocess ({$this->batchId}) - {$this->priority} priority";
    }

    /**
     * Get job tags for monitoring
     */
    public function tags(): array
    {
        return [
            'batch-reprocess',
            "priority:{$this->priority}",
            "batch:{$this->batchId}",
            "user:{$this->userId}"
        ];
    }
}
