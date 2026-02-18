<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\User;
use App\Jobs\BatchReprocessJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

/**
 * BatchReprocessService
 *
 * Manages batch reprocessing operations for enhanced rack analysis.
 * Handles queuing, monitoring, and reporting of bulk reprocessing jobs.
 */
class BatchReprocessService
{
    private const MAX_BATCH_SIZE = 10;
    private const DEFAULT_PRIORITY = 'normal';
    private const BATCH_CACHE_TTL = 3600; // 1 hour
    private const MAX_CONCURRENT_BATCHES = 5;

    private EnhancedRackAnalysisService $analysisService;

    public function __construct(EnhancedRackAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Submit a batch reprocessing request
     */
    public function submitBatchReprocess(array $rackUuids, User $user, array $options = []): array
    {
        // Validate batch size
        if (count($rackUuids) > self::MAX_BATCH_SIZE) {
            throw new Exception('Batch size exceeds maximum limit of ' . self::MAX_BATCH_SIZE . ' racks');
        }

        if (empty($rackUuids)) {
            throw new Exception('Batch cannot be empty');
        }

        // Validate rack access
        $racks = $this->validateRackAccess($rackUuids, $user);

        // Check concurrent batch limits
        if (!$this->canSubmitNewBatch($user)) {
            throw new Exception('Maximum concurrent batches exceeded. Please wait for existing batches to complete.');
        }

        // Generate batch ID
        $batchId = $this->generateBatchId();

        // Extract options
        $priority = $options['priority'] ?? self::DEFAULT_PRIORITY;
        $force = $options['force'] ?? false;

        // Validate priority
        if (!in_array($priority, ['low', 'normal', 'high'])) {
            throw new Exception('Invalid priority. Must be: low, normal, or high');
        }

        // Create batch record
        $batch = $this->createBatchRecord($batchId, $user, $racks, $priority, $options);

        // Queue batch job
        $job = new BatchReprocessJob($rackUuids, $user->id, $batchId, $priority, $force);

        // Dispatch with priority queue
        $queueName = "batch-reprocess-{$priority}";
        Queue::pushOn($queueName, $job);

        Log::info('Batch reprocess submitted', [
            'batch_id' => $batchId,
            'user_id' => $user->id,
            'rack_count' => count($racks),
            'priority' => $priority,
            'queue' => $queueName
        ]);

        return [
            'batch_id' => $batchId,
            'queued_count' => count($racks),
            'priority' => $priority,
            'estimated_completion' => $this->estimateCompletionTime(count($racks), $priority),
            'submitted_at' => now()->toISOString()
        ];
    }

    /**
     * Get batch status
     */
    public function getBatchStatus(string $batchId): array
    {
        $batch = $this->getBatchRecord($batchId);

        if (!$batch) {
            throw new Exception('Batch not found');
        }

        // Calculate current status
        $status = $this->calculateBatchStatus($batch);

        return [
            'batch_id' => $batchId,
            'status' => $status['status'],
            'total_count' => $batch['total_count'],
            'completed_count' => $status['completed_count'],
            'successful_count' => $status['successful_count'],
            'failed_count' => $status['failed_count'],
            'progress_percentage' => $status['progress_percentage'],
            'started_at' => $batch['started_at'],
            'completed_at' => $status['completed_at'],
            'estimated_completion' => $batch['estimated_completion'],
            'priority' => $batch['priority'],
            'error_summary' => $status['error_summary'] ?? null
        ];
    }

    /**
     * Get batch results
     */
    public function getBatchResults(string $batchId): array
    {
        $batch = $this->getBatchRecord($batchId);

        if (!$batch) {
            throw new Exception('Batch not found');
        }

        $results = Cache::get("batch_results_{$batchId}", []);

        return [
            'batch_id' => $batchId,
            'total_racks' => $batch['total_count'],
            'results' => $results,
            'summary' => $this->summarizeBatchResults($results),
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Cancel a batch
     */
    public function cancelBatch(string $batchId, User $user): array
    {
        $batch = $this->getBatchRecord($batchId);

        if (!$batch) {
            throw new Exception('Batch not found');
        }

        // Check if user can cancel this batch
        if ($batch['user_id'] !== $user->id && !$user->hasRole('admin')) {
            throw new Exception('Unauthorized to cancel this batch');
        }

        // Update batch status
        $batch['status'] = 'cancelled';
        $batch['cancelled_at'] = now()->toISOString();
        $this->updateBatchRecord($batchId, $batch);

        Log::info('Batch cancelled by user', [
            'batch_id' => $batchId,
            'user_id' => $user->id
        ]);

        return [
            'batch_id' => $batchId,
            'status' => 'cancelled',
            'cancelled_at' => $batch['cancelled_at']
        ];
    }

    /**
     * Update batch progress
     */
    public function updateBatchProgress(string $batchId, string $rackUuid, array $result): void
    {
        $batch = $this->getBatchRecord($batchId);

        if (!$batch) {
            Log::warning('Attempted to update progress for non-existent batch', [
                'batch_id' => $batchId,
                'rack_uuid' => $rackUuid
            ]);
            return;
        }

        // Update results
        $results = Cache::get("batch_results_{$batchId}", []);
        $results[$rackUuid] = array_merge($result, [
            'completed_at' => now()->toISOString()
        ]);
        Cache::put("batch_results_{$batchId}", $results, self::BATCH_CACHE_TTL * 2);

        // Update batch progress
        $batch['last_update'] = now()->toISOString();
        $this->updateBatchRecord($batchId, $batch);

        Log::debug('Batch progress updated', [
            'batch_id' => $batchId,
            'rack_uuid' => $rackUuid,
            'success' => $result['success'] ?? false
        ]);
    }

    /**
     * Complete batch processing
     */
    public function completeBatch(string $batchId): void
    {
        $batch = $this->getBatchRecord($batchId);

        if (!$batch) {
            return;
        }

        $results = Cache::get("batch_results_{$batchId}", []);
        $summary = $this->summarizeBatchResults($results);

        $batch['status'] = $summary['failed_count'] > 0 ? 'partial_success' : 'completed';
        $batch['completed_at'] = now()->toISOString();
        $batch['final_summary'] = $summary;

        $this->updateBatchRecord($batchId, $batch);

        Log::info('Batch processing completed', [
            'batch_id' => $batchId,
            'status' => $batch['status'],
            'summary' => $summary
        ]);
    }

    /**
     * Get user's batch history
     */
    public function getUserBatchHistory(User $user, int $limit = 20): array
    {
        $batchKeys = Cache::get("user_batches_{$user->id}", []);
        $batches = [];

        foreach (array_slice($batchKeys, -$limit) as $batchId) {
            $batch = $this->getBatchRecord($batchId);
            if ($batch) {
                $batches[] = [
                    'batch_id' => $batchId,
                    'submitted_at' => $batch['submitted_at'],
                    'status' => $this->calculateBatchStatus($batch)['status'],
                    'total_count' => $batch['total_count'],
                    'priority' => $batch['priority']
                ];
            }
        }

        return array_reverse($batches); // Most recent first
    }

    /**
     * Get system batch statistics
     */
    public function getBatchStatistics(): array
    {
        $allBatchKeys = Cache::get('all_batch_ids', []);
        $statistics = [
            'total_batches' => 0,
            'active_batches' => 0,
            'completed_batches' => 0,
            'failed_batches' => 0,
            'average_batch_size' => 0,
            'total_racks_processed' => 0,
            'average_processing_time_minutes' => 0,
            'priority_distribution' => ['low' => 0, 'normal' => 0, 'high' => 0],
            'recent_activity' => []
        ];

        $totalSize = 0;
        $totalDurations = [];
        $recentBatches = [];

        foreach ($allBatchKeys as $batchId) {
            $batch = $this->getBatchRecord($batchId);
            if (!$batch) continue;

            $statistics['total_batches']++;
            $totalSize += $batch['total_count'];
            $statistics['total_racks_processed'] += $batch['total_count'];

            $status = $this->calculateBatchStatus($batch)['status'];

            switch ($status) {
                case 'processing':
                case 'queued':
                    $statistics['active_batches']++;
                    break;
                case 'completed':
                case 'partial_success':
                    $statistics['completed_batches']++;
                    break;
                case 'failed':
                    $statistics['failed_batches']++;
                    break;
            }

            $statistics['priority_distribution'][$batch['priority']]++;

            // Calculate duration for completed batches
            if (isset($batch['completed_at']) && isset($batch['started_at'])) {
                $start = Carbon::parse($batch['started_at']);
                $end = Carbon::parse($batch['completed_at']);
                $totalDurations[] = $end->diffInMinutes($start);
            }

            // Collect recent batches
            if (Carbon::parse($batch['submitted_at'])->isAfter(now()->subDays(7))) {
                $recentBatches[] = [
                    'batch_id' => $batchId,
                    'submitted_at' => $batch['submitted_at'],
                    'status' => $status,
                    'rack_count' => $batch['total_count']
                ];
            }
        }

        $statistics['average_batch_size'] = $statistics['total_batches'] > 0 ?
            round($totalSize / $statistics['total_batches'], 1) : 0;

        $statistics['average_processing_time_minutes'] = !empty($totalDurations) ?
            round(array_sum($totalDurations) / count($totalDurations), 1) : 0;

        // Sort recent activity by submission time
        usort($recentBatches, fn($a, $b) => $b['submitted_at'] <=> $a['submitted_at']);
        $statistics['recent_activity'] = array_slice($recentBatches, 0, 10);

        return $statistics;
    }

    /**
     * Validate rack access for user
     */
    private function validateRackAccess(array $rackUuids, User $user): array
    {
        $query = Rack::whereIn('uuid', $rackUuids);

        // Non-admin users can only reprocess their own racks
        if (!$user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        $racks = $query->get();

        if ($racks->count() !== count($rackUuids)) {
            throw new Exception('One or more rack UUIDs do not exist or are not accessible');
        }

        return $racks->toArray();
    }

    /**
     * Check if user can submit new batch
     */
    private function canSubmitNewBatch(User $user): bool
    {
        // Admin users have higher limits
        $maxBatches = $user->hasRole('admin') ? self::MAX_CONCURRENT_BATCHES * 2 : self::MAX_CONCURRENT_BATCHES;

        $userBatches = Cache::get("user_batches_{$user->id}", []);
        $activeBatches = 0;

        foreach ($userBatches as $batchId) {
            $batch = $this->getBatchRecord($batchId);
            if ($batch) {
                $status = $this->calculateBatchStatus($batch)['status'];
                if (in_array($status, ['queued', 'processing'])) {
                    $activeBatches++;
                }
            }
        }

        return $activeBatches < $maxBatches;
    }

    /**
     * Generate unique batch ID
     */
    private function generateBatchId(): string
    {
        return 'batch_' . now()->format('Ymd_His') . '_' . Str::random(8);
    }

    /**
     * Create batch record
     */
    private function createBatchRecord(string $batchId, User $user, array $racks, string $priority, array $options): array
    {
        $batch = [
            'batch_id' => $batchId,
            'user_id' => $user->id,
            'total_count' => count($racks),
            'priority' => $priority,
            'options' => $options,
            'rack_uuids' => array_column($racks, 'uuid'),
            'status' => 'queued',
            'submitted_at' => now()->toISOString(),
            'started_at' => null,
            'completed_at' => null,
            'estimated_completion' => $this->estimateCompletionTime(count($racks), $priority)->toISOString()
        ];

        Cache::put("batch_{$batchId}", $batch, self::BATCH_CACHE_TTL * 2);

        // Track user batches
        $userBatches = Cache::get("user_batches_{$user->id}", []);
        $userBatches[] = $batchId;
        Cache::put("user_batches_{$user->id}", $userBatches, self::BATCH_CACHE_TTL * 4);

        // Track all batches
        $allBatches = Cache::get('all_batch_ids', []);
        $allBatches[] = $batchId;
        Cache::put('all_batch_ids', $allBatches, self::BATCH_CACHE_TTL * 4);

        return $batch;
    }

    /**
     * Get batch record
     */
    private function getBatchRecord(string $batchId): ?array
    {
        return Cache::get("batch_{$batchId}");
    }

    /**
     * Update batch record
     */
    private function updateBatchRecord(string $batchId, array $batch): void
    {
        Cache::put("batch_{$batchId}", $batch, self::BATCH_CACHE_TTL * 2);
    }

    /**
     * Calculate current batch status
     */
    private function calculateBatchStatus(array $batch): array
    {
        $results = Cache::get("batch_results_{$batch['batch_id']}", []);
        $totalCount = $batch['total_count'];
        $completedCount = count($results);

        $successfulCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($results as $rackUuid => $result) {
            if ($result['success'] ?? false) {
                $successfulCount++;
            } else {
                $failedCount++;
                if (isset($result['error'])) {
                    $errors[] = $result['error'];
                }
            }
        }

        // Determine status
        if (isset($batch['cancelled_at'])) {
            $status = 'cancelled';
        } elseif ($completedCount === 0) {
            $status = isset($batch['started_at']) ? 'processing' : 'queued';
        } elseif ($completedCount < $totalCount) {
            $status = 'processing';
        } elseif ($failedCount === 0) {
            $status = 'completed';
        } elseif ($successfulCount > 0) {
            $status = 'partial_success';
        } else {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'completed_count' => $completedCount,
            'successful_count' => $successfulCount,
            'failed_count' => $failedCount,
            'progress_percentage' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0,
            'completed_at' => $completedCount === $totalCount ? now()->toISOString() : null,
            'error_summary' => !empty($errors) ? array_slice(array_unique($errors), 0, 5) : null
        ];
    }

    /**
     * Estimate completion time
     */
    private function estimateCompletionTime(int $rackCount, string $priority): Carbon
    {
        // Base time per rack (in minutes)
        $baseTimePerRack = 2; // 2 minutes per rack average

        // Priority multipliers
        $priorityMultipliers = [
            'high' => 0.5,   // Process faster
            'normal' => 1.0, // Standard time
            'low' => 2.0     // Process slower
        ];

        $multiplier = $priorityMultipliers[$priority] ?? 1.0;
        $estimatedMinutes = $rackCount * $baseTimePerRack * $multiplier;

        // Add queue wait time based on current load
        $queueWaitMinutes = $this->estimateQueueWaitTime($priority);

        return now()->addMinutes($estimatedMinutes + $queueWaitMinutes);
    }

    /**
     * Estimate queue wait time
     */
    private function estimateQueueWaitTime(string $priority): int
    {
        // Simple estimation based on priority
        // In a real implementation, this would check actual queue depths
        $baseWaitTimes = [
            'high' => 1,   // 1 minute
            'normal' => 5, // 5 minutes
            'low' => 15    // 15 minutes
        ];

        return $baseWaitTimes[$priority] ?? 5;
    }

    /**
     * Summarize batch results
     */
    private function summarizeBatchResults(array $results): array
    {
        $summary = [
            'total_processed' => count($results),
            'successful_count' => 0,
            'failed_count' => 0,
            'constitutional_compliant_count' => 0,
            'average_duration_ms' => 0,
            'common_errors' => []
        ];

        $durations = [];
        $errorCounts = [];

        foreach ($results as $result) {
            if ($result['success'] ?? false) {
                $summary['successful_count']++;

                if ($result['constitutional_compliant'] ?? false) {
                    $summary['constitutional_compliant_count']++;
                }

                if (isset($result['analysis_duration_ms'])) {
                    $durations[] = $result['analysis_duration_ms'];
                }
            } else {
                $summary['failed_count']++;

                if (isset($result['error'])) {
                    $errorCounts[$result['error']] = ($errorCounts[$result['error']] ?? 0) + 1;
                }
            }
        }

        $summary['average_duration_ms'] = !empty($durations) ?
            (int) (array_sum($durations) / count($durations)) : 0;

        // Get most common errors
        arsort($errorCounts);
        $summary['common_errors'] = array_slice($errorCounts, 0, 5);

        return $summary;
    }

    /**
     * Clean up old batch records
     */
    public function cleanupOldBatches(int $daysOld = 30): int
    {
        $allBatchIds = Cache::get('all_batch_ids', []);
        $cleanedCount = 0;
        $remainingBatchIds = [];

        foreach ($allBatchIds as $batchId) {
            $batch = $this->getBatchRecord($batchId);

            if (!$batch) {
                $cleanedCount++;
                continue;
            }

            $submittedAt = Carbon::parse($batch['submitted_at']);
            if ($submittedAt->isBefore(now()->subDays($daysOld))) {
                // Clean up batch record and results
                Cache::forget("batch_{$batchId}");
                Cache::forget("batch_results_{$batchId}");
                $cleanedCount++;
            } else {
                $remainingBatchIds[] = $batchId;
            }
        }

        // Update the all_batch_ids list
        Cache::put('all_batch_ids', $remainingBatchIds, self::BATCH_CACHE_TTL * 4);

        Log::info('Cleaned up old batch records', [
            'cleaned_count' => $cleanedCount,
            'remaining_count' => count($remainingBatchIds)
        ]);

        return $cleanedCount;
    }
}