<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'user_id',
        'comment_text',
        'is_admin_comment',
        'is_public',
    ];

    protected $casts = [
        'is_admin_comment' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAuthorNameAttribute()
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->is_admin_comment ? 'Admin' : 'Anonymous';
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeAdminComments($query)
    {
        return $query->where('is_admin_comment', true);
    }
}
