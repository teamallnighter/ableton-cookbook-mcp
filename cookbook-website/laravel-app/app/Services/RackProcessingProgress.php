<?php

namespace App\Services;

use App\Models\JobExecution;
use App\Models\JobProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

/**
 * Real-time progress tracking service for rack processing jobs
 * 
 * This service manages progress updates, real-time notifications,
 * and provides detailed status information for background jobs.
 */
class RackProcessingProgress
{
    /**
     * Cache key prefix for progress data
     */
    private const CACHE_PREFIX = 'job_progress:';
    
    /**
     * Cache TTL for progress data (in seconds)
     */
    private const CACHE_TTL = 3600; // 1 hour
    
    /**
     * Progress stages with their typical duration estimates (in seconds)
     */
    private const STAGE_DURATIONS = [
        'queued' => 10,
        'validation' => 15,
        'preparation' => 20,
        'analyzing' => 120,
        'processing' => 60,
        'saving' => 30,
        'finalizing' => 15,
        'completed' => 0,
        'retry_scheduled' => 0,
        'permanently_failed' => 0,
    ];
    
    /**
     * Update job progress with detailed tracking
     */
    public function updateProgress(
        string $jobId,
        string $stage,
        int $percentage,
        string $message = '',
        array $details = []
    ): void {
        $timestamp = now();
        
        try {
            // Create progress record in database
            JobProgress::create([
                'job_id' => $jobId,
                'stage' => $stage,
                'percentage' => max(0, min(100, $percentage)),
                'message' => $message,
                'details' => $details,
                'timestamp' => $timestamp,
            ]);
            
            // Update cached progress data
            $this->updateCachedProgress($jobId, $stage, $percentage, $message, $details, $timestamp);
            
            // Update job execution record
            $this->updateJobExecutionProgress($jobId, $stage, $percentage, $message);
            
            // Broadcast real-time update
            $this->broadcastProgressUpdate($jobId, $stage, $percentage, $message, $details);
            
            // Log progress for monitoring
            Log::info('Job progress updated', [
                'job_id' => $jobId,
                'stage' => $stage,
                'percentage' => $percentage,
                'message' => $message,
                'details_count' => count($details)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update job progress', [
                'job_id' => $jobId,
                'stage' => $stage,
                'percentage' => $percentage,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get current progress for a job
     */
    public function getProgress(string $jobId): ?array
    {
        // Try cache first
        $cachedProgress = $this->getCachedProgress($jobId);
        if ($cachedProgress) {
            return $cachedProgress;
        }
        
        // Fall back to database
        return $this->getProgressFromDatabase($jobId);
    }
    
    /**
     * Get detailed progress history for a job
     */
    public function getProgressHistory(string $jobId): array
    {
        $progressRecords = JobProgress::where('job_id', $jobId)
            ->orderBy('timestamp')
            ->get();
            
        return $progressRecords->map(function ($record) {
            return [
                'stage' => $record->stage,
                'percentage' => $record->percentage,
                'message' => $record->message,
                'details' => $record->details,
                'timestamp' => $record->timestamp,
                'duration_from_start' => $this->calculateDurationFromStart($record),
            ];
        })->toArray();
    }
    
    /**
     * Calculate estimated completion time
     */
    public function estimateCompletionTime(string $jobId): ?Carbon
    {
        $progress = $this->getProgress($jobId);
        if (!$progress || $progress['percentage'] >= 100) {
            return null;
        }
        
        $currentStage = $progress['stage'];
        $currentPercentage = $progress['percentage'];
        $startTime = $this->getJobStartTime($jobId);
        
        if (!$startTime) {
            return $this->estimateByStages($currentStage, $currentPercentage);
        }
        
        // Calculate completion based on current progress rate
        $elapsedTime = now()->diffInSeconds($startTime);
        if ($currentPercentage > 0 && $elapsedTime > 0) {
            $progressRate = $currentPercentage / $elapsedTime; // percent per second
            $remainingPercentage = 100 - $currentPercentage;
            $estimatedRemainingTime = $remainingPercentage / $progressRate;
            
            return now()->addSeconds($estimatedRemainingTime);
        }
        
        return $this->estimateByStages($currentStage, $currentPercentage);
    }
    
    /**
     * Get progress summary for multiple jobs
     */
    public function getMultipleJobsProgress(array $jobIds): array
    {
        $progressData = [];
        
        foreach ($jobIds as $jobId) {
            $progress = $this->getProgress($jobId);
            if ($progress) {
                $progressData[$jobId] = [
                    'current' => $progress,
                    'estimated_completion' => $this->estimateCompletionTime($jobId),
                    'is_stalled' => $this->isProgressStalled($jobId),
                ];
            }
        }
        
        return $progressData;
    }
    
    /**
     * Check if job progress has stalled
     */
    public function isProgressStalled(string $jobId): bool
    {
        $progress = $this->getProgress($jobId);
        if (!$progress) {
            return false;
        }
        
        // Consider stalled if no progress update in the last 10 minutes
        // and not in a waiting state
        $lastUpdate = Carbon::parse($progress['last_updated']);
        $minutesSinceUpdate = $lastUpdate->diffInMinutes(now());
        
        $isWaitingState = in_array($progress['stage'], [
            'queued', 'retry_scheduled', 'completed', 'permanently_failed'
        ]);
        
        return !$isWaitingState && $minutesSinceUpdate > 10;
    }
    
    /**
     * Clean up old progress data
     */
    public function cleanupOldProgress(int $olderThanDays = 7): int
    {
        $cutoffDate = now()->subDays($olderThanDays);
        
        $deletedCount = JobProgress::where('timestamp', '<', $cutoffDate)->delete();
        
        Log::info('Cleaned up old job progress data', [
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate
        ]);
        
        return $deletedCount;
    }
    
    /**
     * Get progress statistics for monitoring
     */
    public function getProgressStatistics(): array
    {
        $now = now();
        $hourAgo = $now->copy()->subHour();
        $dayAgo = $now->copy()->subDay();
        
        return [
            'active_jobs' => JobProgress::where('timestamp', '>=', $hourAgo)
                ->distinct('job_id')
                ->count(),
            'completed_last_hour' => JobProgress::where('stage', 'completed')
                ->where('timestamp', '>=', $hourAgo)
                ->count(),
            'failed_last_hour' => JobProgress::where('stage', 'permanently_failed')
                ->where('timestamp', '>=', $hourAgo)
                ->count(),
            'average_processing_time' => $this->getAverageProcessingTime($dayAgo),
            'stage_distribution' => $this->getCurrentStageDistribution(),
            'stalled_jobs' => $this->getStalledJobsCount(),
        ];
    }
    
    /**
     * Update cached progress data
     */
    private function updateCachedProgress(
        string $jobId,
        string $stage,
        int $percentage,
        string $message,
        array $details,
        Carbon $timestamp
    ): void {
        $cacheKey = self::CACHE_PREFIX . $jobId;
        
        $progressData = [
            'job_id' => $jobId,
            'stage' => $stage,
            'percentage' => $percentage,
            'message' => $message,
            'details' => $details,
            'last_updated' => $timestamp->toISOString(),
            'estimated_completion' => $this->estimateCompletionTime($jobId)?->toISOString(),
        ];
        
        Cache::put($cacheKey, $progressData, self::CACHE_TTL);
    }
    
    /**
     * Get cached progress data
     */
    private function getCachedProgress(string $jobId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $jobId;
        return Cache::get($cacheKey);
    }
    
    /**
     * Get progress from database
     */
    private function getProgressFromDatabase(string $jobId): ?array
    {
        $latestProgress = JobProgress::where('job_id', $jobId)
            ->orderBy('timestamp', 'desc')
            ->first();
            
        if (!$latestProgress) {
            return null;
        }
        
        return [
            'job_id' => $jobId,
            'stage' => $latestProgress->stage,
            'percentage' => $latestProgress->percentage,
            'message' => $latestProgress->message,
            'details' => $latestProgress->details,
            'last_updated' => $latestProgress->timestamp->toISOString(),
        ];
    }
    
    /**
     * Update job execution record with progress
     */
    private function updateJobExecutionProgress(
        string $jobId,
        string $stage,
        int $percentage,
        string $message
    ): void {
        JobExecution::where('job_id', $jobId)->update([
            'progress_percentage' => $percentage,
            'current_stage' => $stage,
            'stage_data' => [
                'message' => $message,
                'updated_at' => now()->toISOString(),
            ],
        ]);
    }
    
    /**
     * Broadcast real-time progress update
     */
    private function broadcastProgressUpdate(
        string $jobId,
        string $stage,
        int $percentage,
        string $message,
        array $details
    ): void {
        // This would integrate with Laravel Broadcasting/Pusher
        // For now, we'll just emit a local event
        Event::dispatch('job.progress.updated', [
            'job_id' => $jobId,
            'stage' => $stage,
            'percentage' => $percentage,
            'message' => $message,
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Calculate duration from job start
     */
    private function calculateDurationFromStart(JobProgress $record): ?int
    {
        $startTime = $this->getJobStartTime($record->job_id);
        if (!$startTime) {
            return null;
        }
        
        return $record->timestamp->diffInSeconds($startTime);
    }
    
    /**
     * Get job start time
     */
    private function getJobStartTime(string $jobId): ?Carbon
    {
        $jobExecution = JobExecution::where('job_id', $jobId)->first();
        return $jobExecution?->started_at ?? $jobExecution?->queued_at;
    }
    
    /**
     * Estimate completion time by stages
     */
    private function estimateByStages(string $currentStage, int $currentPercentage): Carbon
    {
        $remainingDuration = 0;
        $stageFound = false;
        
        foreach (self::STAGE_DURATIONS as $stage => $duration) {
            if ($stage === $currentStage) {
                $stageFound = true;
                // Add remaining portion of current stage
                $stageProgress = max(0, min(100, $currentPercentage));
                $remainingInCurrentStage = $duration * (1 - $stageProgress / 100);
                $remainingDuration += $remainingInCurrentStage;
                continue;
            }
            
            if ($stageFound && $stage !== 'completed') {
                $remainingDuration += $duration;
            }
        }
        
        return now()->addSeconds($remainingDuration);
    }
    
    /**
     * Get average processing time for completed jobs
     */
    private function getAverageProcessingTime(Carbon $since): ?float
    {
        $completedJobs = JobExecution::where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->whereNotNull('started_at')
            ->get();
            
        if ($completedJobs->isEmpty()) {
            return null;
        }
        
        $totalTime = $completedJobs->sum(function ($job) {
            return $job->completed_at->diffInSeconds($job->started_at);
        });
        
        return $totalTime / $completedJobs->count();
    }
    
    /**
     * Get current distribution of jobs by stage
     */
    private function getCurrentStageDistribution(): array
    {
        $activeJobs = JobExecution::whereIn('status', ['processing', 'retry_scheduled', 'queued'])
            ->get();
            
        $distribution = [];
        foreach ($activeJobs as $job) {
            $stage = $job->current_stage ?? 'unknown';
            $distribution[$stage] = ($distribution[$stage] ?? 0) + 1;
        }
        
        return $distribution;
    }
    
    /**
     * Get count of stalled jobs
     */
    private function getStalledJobsCount(): int
    {
        $stalledThreshold = now()->subMinutes(10);
        
        return JobProgress::select('job_id')
            ->whereIn('job_id', function ($query) use ($stalledThreshold) {
                $query->select('job_id')
                    ->from('job_executions')
                    ->where('status', 'processing')
                    ->where('started_at', '<', $stalledThreshold);
            })
            ->whereNotIn('stage', ['queued', 'retry_scheduled', 'completed', 'permanently_failed'])
            ->distinct('job_id')
            ->count();
    }
    
    /**
     * Mark job as stalled and trigger investigation
     */
    public function markJobAsStalled(string $jobId): void
    {
        Log::warning('Job marked as stalled', [
            'job_id' => $jobId,
            'last_progress' => $this->getProgress($jobId)
        ]);
        
        $this->updateProgress(
            $jobId,
            'stalled',
            0,
            'Job appears to be stalled - investigating...',
            ['stalled_at' => now()->toISOString()]
        );
        
        // Trigger stalled job investigation
        Event::dispatch('job.stalled', ['job_id' => $jobId]);
    }
    
    /**
     * Resume stalled job
     */
    public function resumeStalledJob(string $jobId): bool
    {
        try {
            $jobExecution = JobExecution::where('job_id', $jobId)->first();
            
            if (!$jobExecution) {
                return false;
            }
            
            // Reset job to processing state
            $jobExecution->update([
                'status' => 'processing',
                'current_stage' => 'resumed',
            ]);
            
            $this->updateProgress(
                $jobId,
                'resumed',
                $jobExecution->progress_percentage ?? 0,
                'Job resumed after stall detection',
                ['resumed_at' => now()->toISOString()]
            );
            
            Log::info('Stalled job resumed', ['job_id' => $jobId]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to resume stalled job', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get progress for user dashboard
     */
    public function getProgressForUser(int $userId): array
    {
        $userJobs = JobExecution::whereJsonContains('metadata->user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();
            
        $progressData = [];
        
        foreach ($userJobs as $job) {
            $progress = $this->getProgress($job->job_id);
            if ($progress) {
                $progressData[] = [
                    'job_id' => $job->job_id,
                    'model_id' => $job->model_id,
                    'model_type' => $job->model_type,
                    'progress' => $progress,
                    'estimated_completion' => $this->estimateCompletionTime($job->job_id),
                    'created_at' => $job->created_at,
                ];
            }
        }
        
        return $progressData;
    }
}