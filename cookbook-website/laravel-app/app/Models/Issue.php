<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\NotificationService;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_type_id',
        'user_id',
        'rack_id',
        'title',
        'description',
        'submitter_name',
        'submitter_email',
        'status',
        'priority',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function fileUploads(): HasMany
    {
        return $this->hasMany(IssueFileUpload::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('issue_type_id', $typeId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Methods
    public function updateStatusWithNotification($status, $adminNotes = null, $comment = null)
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $status,
            'admin_notes' => $adminNotes,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);

        // Add public comment if provided
        if ($comment) {
            $this->comments()->create([
                'comment_text' => $comment,
                'is_admin_comment' => true,
                'is_public' => true,
            ]);
        }

        // Send notification if email is available
        if ($this->submitter_email || ($this->user && $this->user->email)) {
            $email = $this->submitter_email ?: $this->user->email;
            $notificationService = new NotificationService();
            $notificationService->sendIssueStatusUpdate(
                $this->id,
                $email,
                $oldStatus,
                $status,
                $comment
            );
        }

        return $this;
    }

    public static function createWithNotification(array $data)
    {
        $issue = static::create($data);

        // Send confirmation email
        if ($issue->submitter_email || ($issue->user && $issue->user->email)) {
            $email = $issue->submitter_email ?: $issue->user->email;
            $notificationService = new NotificationService();
            $notificationService->sendNewIssueConfirmation($issue->id, $email);
        }

        // Notify admin
        $notificationService = new NotificationService();
        $notificationService->notifyAdminNewIssue(
            $issue->id,
            $issue->title,
            $issue->issueType->name
        );

        return $issue;
    }

    public function getStatusBadgeClass()
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'in_review' => 'bg-blue-100 text-blue-800',
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'resolved' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getPriorityBadgeClass()
    {
        return match($this->priority) {
            'urgent' => 'bg-red-500 text-white',
            'high' => 'bg-orange-500 text-white',
            'medium' => 'bg-yellow-500 text-white',
            'low' => 'bg-green-500 text-white',
            default => 'bg-gray-500 text-white'
        };
    }
}
