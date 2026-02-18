<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Job execution tracking model
 * 
 * This model tracks the complete lifecycle of background job executions,
 * including progress, failures, retries, and performance metrics.
 */
class JobExecution extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'job_id',
        'job_class',
        'queue',
        'model_id',
        'model_type',
        'status',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'retry_delay',
        'progress_percentage',
        'current_stage',
        'stage_data',
        'memory_peak',
        'execution_time',
        'queue_wait_time',
        'failure_category',
        'failure_reason',
        'failure_context',
        'stack_trace',
        'payload',
        'result_data',
        'tags',
        'metadata',
    ];
    
    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'stage_data' => 'array',
        'failure_context' => 'array',
        'payload' => 'array',
        'result_data' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
    ];
    
    /**
     * Get the model that this job execution belongs to
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Get all progress records for this job
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(JobProgress::class, 'job_id', 'job_id');
    }
    
    /**
     * Get all error logs for this job
     */
    public function errorLogs(): HasMany
    {
        return $this->hasMany(JobErrorLog::class, 'job_id', 'job_id');
    }
    
    /**
     * Get all notifications for this job
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(JobNotification::class, 'job_id', 'job_id');
    }
    
    /**
     * Scope for jobs with specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope for jobs of specific class
     */
    public function scopeOfClass($query, $jobClass)
    {
        return $query->where('job_class', $jobClass);
    }
    
    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'permanently_failed']);
    }
    
    /**
     * Scope for retryable jobs
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', 'retry_scheduled')
            ->where('next_retry_at', '<=', now());
    }
    
    /**
     * Scope for stalled jobs
     */
    public function scopeStalled($query, int $minutesThreshold = 10)
    {
        return $query->where('status', 'processing')
            ->where('started_at', '<', now()->subMinutes($minutesThreshold));
    }
    
    /**
     * Check if job is currently running
     */
    public function isRunning(): bool
    {
        return $this->status === 'processing';
    }
    
    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'permanently_failed']);
    }
    
    /**
     * Check if job is scheduled for retry
     */
    public function isScheduledForRetry(): bool
    {
        return $this->status === 'retry_scheduled';
    }
    
    /**
     * Get job duration in seconds
     */
    public function getDurationInSeconds(): ?int
    {
        if (!$this->started_at) {
            return null;
        }
        
        $endTime = $this->completed_at ?? $this->failed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }
    
    /**
     * Get queue wait time in seconds
     */
    public function getQueueWaitTimeInSeconds(): ?int
    {
        if (!$this->started_at || !$this->queued_at) {
            return null;
        }
        
        return $this->queued_at->diffInSeconds($this->started_at);
    }
    
    /**
     * Get success rate for similar jobs
     */
    public function getSimilarJobsSuccessRate(): float
    {
        $similarJobs = static::where('job_class', $this->job_class)
            ->where('created_at', '>=', now()->subDays(7))
            ->where('id', '!=', $this->id);
            
        $total = $similarJobs->count();
        
        if ($total === 0) {
            return 0.0;
        }
        
        $successful = $similarJobs->where('status', 'completed')->count();
        
        return ($successful / $total) * 100;
    }
    
    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTime(): ?string
    {
        if (!$this->execution_time) {
            return null;
        }
        
        $seconds = $this->execution_time / 1000; // Convert from milliseconds
        
        if ($seconds < 60) {
            return number_format($seconds, 2) . 's';
        } elseif ($seconds < 3600) {
            return number_format($seconds / 60, 2) . 'm';
        } else {
            return number_format($seconds / 3600, 2) . 'h';
        }
    }
    
    /**
     * Get formatted memory usage
     */
    public function getFormattedMemoryUsage(): ?string
    {
        if (!$this->memory_peak) {
            return null;
        }
        
        $bytes = $this->memory_peak;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return number_format($bytes, 2) . ' ' . $units[$i];
    }
}