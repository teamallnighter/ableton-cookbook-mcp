<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IssueFileUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_size',
        'file_type',
        'ableton_version',
        'rack_name',
        'rack_description',
        'tags',
        'is_processed',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_processed' => 'boolean',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getDownloadUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    public function delete()
    {
        // Delete the physical file
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }
        
        return parent::delete();
    }
}
