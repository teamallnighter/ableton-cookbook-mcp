<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Job progress tracking model
 * 
 * This model stores individual progress updates for jobs,
 * enabling real-time progress tracking and historical analysis.
 */
class JobProgress extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'job_id',
        'stage',
        'percentage',
        'message',
        'details',
        'timestamp',
    ];
    
    protected $casts = [
        'details' => 'array',
        'timestamp' => 'datetime',
    ];
    
    /**
     * Get the job execution this progress belongs to
     */
    public function jobExecution(): BelongsTo
    {
        return $this->belongsTo(JobExecution::class, 'job_id', 'job_id');
    }
    
    /**
     * Scope for specific job
     */
    public function scopeForJob($query, string $jobId)
    {
        return $query->where('job_id', $jobId);
    }
    
    /**
     * Scope for specific stage
     */
    public function scopeInStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }
    
    /**
     * Scope for recent progress
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('timestamp', '>=', now()->subMinutes($minutes));
    }
    
    /**
     * Get the latest progress for a job
     */
    public static function getLatestForJob(string $jobId): ?self
    {
        return static::where('job_id', $jobId)
            ->orderBy('timestamp', 'desc')
            ->first();
    }
    
    /**
     * Get progress stages for a job in chronological order
     */
    public static function getStagesForJob(string $jobId): array
    {
        return static::where('job_id', $jobId)
            ->orderBy('timestamp')
            ->pluck('stage')
            ->unique()
            ->toArray();
    }
    
    /**
     * Get time spent in current stage
     */
    public function getTimeInStage(): ?int
    {
        $previousProgress = static::where('job_id', $this->job_id)
            ->where('timestamp', '<', $this->timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();
            
        if (!$previousProgress) {
            return null;
        }
        
        return $this->timestamp->diffInSeconds($previousProgress->timestamp);
    }
    
    /**
     * Check if this is a significant progress update
     */
    public function isSignificantUpdate(): bool
    {
        $previousProgress = static::where('job_id', $this->job_id)
            ->where('timestamp', '<', $this->timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();
            
        if (!$previousProgress) {
            return true; // First update is always significant
        }
        
        // Significant if stage changed or percentage increased by 10% or more
        return $this->stage !== $previousProgress->stage || 
               ($this->percentage - $previousProgress->percentage) >= 10;
    }
}