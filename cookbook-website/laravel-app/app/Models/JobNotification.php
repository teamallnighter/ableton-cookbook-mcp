<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Job notification model
 * 
 * This model stores notifications sent to users about job status changes,
 * progress updates, failures, and completions across multiple channels.
 */
class JobNotification extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'job_id',
        'user_id',
        'type',
        'channel',
        'title',
        'message',
        'data',
        'status',
        'scheduled_for',
        'sent_at',
        'read_at',
    ];
    
    protected $casts = [
        'data' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];
    
    /**
     * Get the job execution this notification belongs to
     */
    public function jobExecution(): BelongsTo
    {
        return $this->belongsTo(JobExecution::class, 'job_id', 'job_id');
    }
    
    /**
     * Get the user this notification belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Scope for specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope for specific notification type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
    
    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
    
    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
    
    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    /**
     * Scope for specific channel
     */
    public function scopeViaChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return true; // Already read
        }
        
        return $this->update(['read_at' => now()]);
    }
    
    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
    
    /**
     * Check if notification is sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
    
    /**
     * Check if notification failed to send
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
    
    /**
     * Get time since notification was sent
     */
    public function getTimeSinceSent(): ?string
    {
        if (!$this->sent_at) {
            return null;
        }
        
        return $this->sent_at->diffForHumans();
    }
    
    /**
     * Get time since notification was read
     */
    public function getTimeSinceRead(): ?string
    {
        if (!$this->read_at) {
            return null;
        }
        
        return $this->read_at->diffForHumans();
    }
}