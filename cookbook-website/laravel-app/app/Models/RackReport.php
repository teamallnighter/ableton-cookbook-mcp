<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'rack_id',
        'user_id',
        'issue_type',
        'description',
        'status',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getIssueTypes(): array
    {
        return [
            'corrupted_file' => 'Corrupted or broken file',
            'missing_dependencies' => 'Missing plugins/dependencies',
            'incorrect_category' => 'Wrong category or tags',
            'misleading_description' => 'Misleading description',
            'copyright_violation' => 'Copyright violation',
            'version_compatibility' => 'Version compatibility issue',
            'inappropriate_content' => 'Inappropriate content',
            'other' => 'Other issue'
        ];
    }
}
