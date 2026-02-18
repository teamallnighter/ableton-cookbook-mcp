<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\JobExecution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class RealTimeStatusService
{
    protected const CACHE_PREFIX = 'realtime_status:';
    protected const CACHE_TTL = 300; // 5 minutes
    
    protected array $statusChannels = [];
    protected array $subscribers = [];
    
    /**
     * Get real-time status for a rack
     */
    public function getRackStatus(Rack $rack): array
    {
        $cacheKey = self::CACHE_PREFIX . "rack:{$rack->id}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($rack) {
            return $this->buildRackStatus($rack);
        });
    }
    
    /**
     * Update rack status in real-time
     */
    public function updateRackStatus(Rack $rack, array $statusData): void
    {
        $cacheKey = self::CACHE_PREFIX . "rack:{$rack->id}";
        
        $status = array_merge($this->buildRackStatus($rack), $statusData, [
            'last_updated' => now()->toISOString(),
            'update_source' => 'real_time'
        ]);
        
        Cache::put($cacheKey, $status, self::CACHE_TTL);
        
        // Broadcast to subscribers
        $this->broadcast("rack.{$rack->id}", $status);
        
        Log::debug('Real-time status updated', [
            'rack_id' => $rack->id,
            'status' => $status['processing_status'] ?? 'unknown',
            'progress' => $status['progress_percentage'] ?? 0
        ]);
    }
    
    /**
     * Build comprehensive rack status
     */
    protected function buildRackStatus(Rack $rack): array
    {
        // Refresh from database
        $rack->refresh();
        
        $processingStatus = $rack->processing_status ?? $rack->status;
        $isProcessing = in_array($processingStatus, ['processing', 'analyzing', 'queued']);
        $hasError = in_array($processingStatus, ['failed', 'permanently_failed']);
        
        $status = [
            'rack_id' => $rack->id,
            'status' => $rack->status,
            'processing_status' => $processingStatus,
            'is_processing' => $isProcessing,
            'is_complete' => $this->isAnalysisComplete($rack),
            'has_error' => $hasError,
            'progress_percentage' => $rack->processing_progress ?? 0,
            'stage' => $rack->processing_stage,
            'stage_message' => $this->getStageMessage($rack->processing_stage),
            'error_message' => $rack->processing_error,
            'can_retry' => $this->canRetryProcessing($rack),
            'retry_count' => $rack->retry_count ?? 0,
            'max_retries' => 5,
            'estimated_completion' => $this->estimateCompletion($rack),
            'last_updated' => $rack->updated_at->toISOString(),
            'update_source' => 'database'
        ];
        
        // Add job execution details if available
        if ($rack->current_job_id) {
            $jobStatus = $this->getJobStatus($rack->current_job_id);
            $status = array_merge($status, $jobStatus);
        }
        
        // Add queue information
        $status['queue_info'] = $this->getQueueInfo($rack);
        
        return $status;
    }
    
    /**
     * Get job execution status
     */
    protected function getJobStatus(string $jobId): array
    {
        try {
            $jobExecution = JobExecution::where('job_id', $jobId)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if (!$jobExecution) {
                return ['job_status' => 'unknown'];
            }
            
            return [
                'job_status' => $jobExecution->status,
                'job_attempts' => $jobExecution->attempts,
                'job_started_at' => $jobExecution->started_at?->toISOString(),
                'job_execution_time' => $jobExecution->getExecutionTime(),
                'job_memory_usage' => $jobExecution->memory_usage,
                'job_failure_reason' => $jobExecution->failure_reason,
                'next_retry_at' => $jobExecution->next_retry_at?->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get job status', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return ['job_status' => 'error'];
        }
    }
    
    /**
     * Get queue information
     */
    protected function getQueueInfo(Rack $rack): array
    {
        try {
            $queueConnection = config('queue.default');
            
            if ($queueConnection === 'database') {
                $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('queue', 'processing')
                    ->count();
                    
                $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                    ->whereDate('created_at', today())
                    ->count();
                    
                // Estimate queue position
                $queuePosition = null;
                if ($rack->processing_status === 'queued') {
                    $queuePosition = \Illuminate\Support\Facades\DB::table('jobs')
                        ->where('queue', 'processing')
                        ->where('created_at', '<', $rack->updated_at)
                        ->count() + 1;
                }
                
                return [
                    'connection' => $queueConnection,
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs_today' => $failedJobs,
                    'queue_position' => $queuePosition,
                    'estimated_wait' => $this->estimateQueueWait($pendingJobs)
                ];
            }
            
            return [
                'connection' => $queueConnection,
                'status' => 'available'
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get queue info', [
                'error' => $e->getMessage()
            ]);
            
            return ['status' => 'unknown'];
        }
    }
    
    /**
     * Estimate queue wait time
     */
    protected function estimateQueueWait(int $pendingJobs): ?string
    {
        if ($pendingJobs === 0) {
            return null;
        }
        
        // Assume average processing time of 2 minutes per job
        $estimatedMinutes = $pendingJobs * 2;
        
        if ($estimatedMinutes < 60) {
            return "{$estimatedMinutes} minutes";
        }
        
        $hours = intval($estimatedMinutes / 60);
        $minutes = $estimatedMinutes % 60;
        
        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours} hours";
    }
    
    /**
     * Get stage-specific message
     */
    protected function getStageMessage(?string $stage): ?string
    {
        return match($stage) {
            'queued' => 'Waiting in processing queue...',
            'initializing' => 'Initializing file processing...',
            'extracting' => 'Extracting device group data...',
            'analyzing' => 'Analyzing rack structure...',
            'parsing_devices' => 'Parsing individual devices...',
            'building_tree' => 'Building device hierarchy...',
            'validating' => 'Validating rack data...',
            'finalizing' => 'Finalizing analysis...',
            'complete' => 'Analysis complete',
            default => $stage ? ucfirst(str_replace('_', ' ', $stage)) . '...' : null
        };
    }
    
    /**
     * Estimate completion time
     */
    protected function estimateCompletion(Rack $rack): ?string
    {
        $processingStatus = $rack->processing_status ?? $rack->status;
        
        if (!in_array($processingStatus, ['processing', 'analyzing', 'queued'])) {
            return null;
        }
        
        $progress = $rack->processing_progress ?? 0;
        
        if ($progress === 0) {
            // Base estimate on file size
            $fileSize = $rack->file_size ?? 0;
            $estimatedMinutes = match(true) {
                $fileSize < 100 * 1024 => 1,
                $fileSize < 500 * 1024 => 2,
                $fileSize < 1024 * 1024 => 3,
                default => 5
            };
        } else {
            // Calculate based on current progress
            $elapsedTime = $rack->updated_at->diffInSeconds($rack->processing_started_at ?? $rack->updated_at);
            $estimatedTotal = $elapsedTime / ($progress / 100);
            $remainingSeconds = $estimatedTotal - $elapsedTime;
            $estimatedMinutes = max(1, ceil($remainingSeconds / 60));
        }
        
        return now()->addMinutes($estimatedMinutes)->toISOString();
    }
    
    /**
     * Check if analysis is complete
     */
    protected function isAnalysisComplete(Rack $rack): bool
    {
        $status = $rack->processing_status ?? $rack->status;
        return in_array($status, ['analysis_complete', 'ready_for_annotation', 'pending', 'approved']);
    }
    
    /**
     * Check if processing can be retried
     */
    protected function canRetryProcessing(Rack $rack): bool
    {
        $status = $rack->processing_status ?? $rack->status;
        $retryCount = $rack->retry_count ?? 0;
        
        if (in_array($status, ['failed', 'permanently_failed'])) {
            return $retryCount < 5;
        }
        
        if (in_array($status, ['processing', 'analyzing']) && $rack->updated_at) {
            $minutesSinceUpdate = $rack->updated_at->diffInMinutes(now());
            return $minutesSinceUpdate > 15;
        }
        
        return false;
    }
    
    /**
     * Get status for multiple racks
     */
    public function getBatchRackStatus(Collection $racks): array
    {
        $statuses = [];
        
        foreach ($racks as $rack) {
            $statuses[$rack->id] = $this->getRackStatus($rack);
        }
        
        return $statuses;
    }
    
    /**
     * Subscribe to status updates
     */
    public function subscribe(string $channel, callable $callback): string
    {
        $subscriptionId = uniqid('sub_', true);
        
        if (!isset($this->subscribers[$channel])) {
            $this->subscribers[$channel] = [];
        }
        
        $this->subscribers[$channel][$subscriptionId] = $callback;
        
        Log::debug('Status subscription created', [
            'channel' => $channel,
            'subscription_id' => $subscriptionId
        ]);
        
        return $subscriptionId;
    }
    
    /**
     * Unsubscribe from status updates
     */
    public function unsubscribe(string $channel, string $subscriptionId): void
    {
        if (isset($this->subscribers[$channel][$subscriptionId])) {
            unset($this->subscribers[$channel][$subscriptionId]);
            
            if (empty($this->subscribers[$channel])) {
                unset($this->subscribers[$channel]);
            }
        }
    }
    
    /**
     * Broadcast status update to subscribers
     */
    protected function broadcast(string $channel, array $data): void
    {
        if (!isset($this->subscribers[$channel])) {
            return;
        }
        
        foreach ($this->subscribers[$channel] as $subscriptionId => $callback) {
            try {
                $callback($data);
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast to subscriber', [
                    'channel' => $channel,
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage()
                ]);
                
                // Remove failed subscriber
                unset($this->subscribers[$channel][$subscriptionId]);
            }
        }
    }
    
    /**
     * Clear status cache for rack
     */
    public function clearRackStatusCache(Rack $rack): void
    {
        $cacheKey = self::CACHE_PREFIX . "rack:{$rack->id}";
        Cache::forget($cacheKey);
    }
    
    /**
     * Get system-wide processing statistics
     */
    public function getProcessingStatistics(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'processing_stats';
        
        return Cache::remember($cacheKey, 60, function () {
            try {
                $stats = [
                    'total_processing' => Rack::whereIn('processing_status', ['processing', 'analyzing', 'queued'])->count(),
                    'completed_today' => Rack::where('processing_status', 'analysis_complete')
                        ->whereDate('updated_at', today())
                        ->count(),
                    'failed_today' => Rack::whereIn('processing_status', ['failed', 'permanently_failed'])
                        ->whereDate('updated_at', today())
                        ->count(),
                    'average_processing_time' => $this->getAverageProcessingTime(),
                    'queue_health' => $this->getQueueHealth()
                ];
                
                return $stats;
                
            } catch (\Exception $e) {
                Log::error('Failed to get processing statistics', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'error' => 'Failed to retrieve statistics',
                    'timestamp' => now()->toISOString()
                ];
            }
        });
    }
    
    /**
     * Get average processing time
     */
    protected function getAverageProcessingTime(): ?int
    {
        try {
            $completedJobs = JobExecution::where('status', 'completed')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->whereNotNull('completed_at')
                ->get();
                
            if ($completedJobs->isEmpty()) {
                return null;
            }
            
            $totalTime = $completedJobs->sum(function ($job) {
                return $job->started_at->diffInSeconds($job->completed_at);
            });
            
            return intval($totalTime / $completedJobs->count());
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get queue health status
     */
    protected function getQueueHealth(): string
    {
        try {
            $queueConnection = config('queue.default');
            
            if ($queueConnection === 'database') {
                $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')
                    ->where('queue', 'processing')
                    ->count();
                    
                $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                    ->whereDate('created_at', today())
                    ->count();
                
                if ($pendingJobs > 100) {
                    return 'overloaded';
                } elseif ($pendingJobs > 50) {
                    return 'busy';
                } elseif ($failedJobs > 10) {
                    return 'degraded';
                } else {
                    return 'healthy';
                }
            }
            
            return 'healthy';
            
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
    
    /**
     * Health check for the service
     */
    public function healthCheck(): array
    {
        return [
            'service' => 'RealTimeStatusService',
            'status' => 'operational',
            'cache_connection' => Cache::getStore() instanceof \Illuminate\Cache\RedisStore ? 'redis' : 'file',
            'active_subscriptions' => array_sum(array_map('count', $this->subscribers)),
            'cached_items' => $this->getCacheItemCount(),
            'timestamp' => now()->toISOString()
        ];
    }
    
    /**
     * Get count of cached status items
     */
    protected function getCacheItemCount(): int
    {
        try {
            // This is implementation-specific and may not work with all cache drivers
            $keys = Cache::getRedis()->keys(self::CACHE_PREFIX . '*');
            return count($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
}