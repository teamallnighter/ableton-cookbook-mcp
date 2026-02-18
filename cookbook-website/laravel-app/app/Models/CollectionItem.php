<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @OA\Schema(
 *     schema="CollectionItem",
 *     type="object",
 *     title="Collection Item",
 *     description="Individual item within a collection",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="collection_id", type="integer", example=1),
 *     @OA\Property(property="collectable_type", type="string", example="App\\Models\\Rack"),
 *     @OA\Property(property="collectable_id", type="integer", example=42),
 *     @OA\Property(property="position", type="integer", example=1),
 *     @OA\Property(property="section", type="string", example="Basslines"),
 *     @OA\Property(property="chapter_title", type="string", example="Building Your First Bassline"),
 *     @OA\Property(property="is_required", type="boolean", example=true),
 *     @OA\Property(property="estimated_duration", type="number", format="float", example=2.5)
 * )
 */
class CollectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'collectable_type',
        'collectable_id',
        'position',
        'section',
        'chapter_title',
        'description',
        'learning_notes',
        'is_required',
        'is_unlocked_by_default',
        'unlock_conditions',
        'estimated_duration',
        'learning_weight',
        'completion_criteria',
        'include_in_downloads',
        'download_filename',
        'download_folder',
        'external_url',
        'external_type',
        'external_metadata',
        'views_count',
        'completions_count',
        'likes_count',
    ];

    protected $casts = [
        'unlock_conditions' => 'array',
        'completion_criteria' => 'array',
        'external_metadata' => 'array',
        'is_required' => 'boolean',
        'is_unlocked_by_default' => 'boolean',
        'include_in_downloads' => 'boolean',
        'estimated_duration' => 'decimal:2',
    ];

    /**
     * Get the collection this item belongs to
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(EnhancedCollection::class, 'collection_id');
    }

    /**
     * Get the collectable model (Rack, Preset, Session, etc.)
     */
    public function collectable(): MorphTo
    {
        return $this->morphTo();
    }

    // SCOPES

    /**
     * Scope for required items
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for items in a specific section
     */
    public function scopeInSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Scope for external links
     */
    public function scopeExternalLinks($query)
    {
        return $query->whereNotNull('external_url');
    }

    /**
     * Scope for downloadable items
     */
    public function scopeDownloadable($query)
    {
        return $query->where('include_in_downloads', true);
    }

    // ACCESSORS

    /**
     * Get display name for external type
     */
    public function getExternalTypeDisplayAttribute(): ?string
    {
        if (!$this->external_type) {
            return null;
        }

        $typeMap = [
            'youtube_video' => 'YouTube Video',
            'vimeo_video' => 'Vimeo Video',
            'soundcloud_track' => 'SoundCloud Track',
            'spotify_playlist' => 'Spotify Playlist',
            'blog_article' => 'Blog Article',
            'documentation' => 'Documentation',
            'website' => 'Website',
            'other' => 'External Link',
        ];

        return $typeMap[$this->external_type] ?? 'External Link';
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->estimated_duration) {
            return 'Unknown';
        }

        $hours = floor($this->estimated_duration);
        $minutes = ($this->estimated_duration - $hours) * 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get item type display name
     */
    public function getItemTypeDisplayAttribute(): string
    {
        if ($this->external_url) {
            return $this->external_type_display;
        }

        return class_basename($this->collectable_type);
    }

    /**
     * Get item title
     */
    public function getItemTitleAttribute(): string
    {
        if ($this->chapter_title) {
            return $this->chapter_title;
        }

        if ($this->external_metadata && isset($this->external_metadata['title'])) {
            return $this->external_metadata['title'];
        }

        if ($this->collectable) {
            return $this->collectable->title ?? $this->collectable->name ?? 'Untitled';
        }

        return 'Untitled Item';
    }

    /**
     * Get item description
     */
    public function getItemDescriptionAttribute(): ?string
    {
        if ($this->description) {
            return $this->description;
        }

        if ($this->external_metadata && isset($this->external_metadata['description'])) {
            return $this->external_metadata['description'];
        }

        if ($this->collectable && isset($this->collectable->description)) {
            return $this->collectable->description;
        }

        return null;
    }

    /**
     * Get thumbnail/preview image
     */
    public function getItemThumbnailAttribute(): ?string
    {
        if ($this->external_metadata && isset($this->external_metadata['thumbnail'])) {
            return $this->external_metadata['thumbnail'];
        }

        if ($this->collectable) {
            // Try various image fields that might exist
            $imageFields = ['cover_image_path', 'preview_image_path', 'image_path', 'thumbnail_path'];
            foreach ($imageFields as $field) {
                if (isset($this->collectable->{$field}) && $this->collectable->{$field}) {
                    return $this->collectable->{$field};
                }
            }
        }

        return null;
    }

    // BUSINESS METHODS

    /**
     * Check if item is external link
     */
    public function isExternalLink(): bool
    {
        return !empty($this->external_url);
    }

    /**
     * Check if item is unlocked for user
     */
    public function isUnlockedFor(User $user): bool
    {
        if ($this->is_unlocked_by_default) {
            return true;
        }

        if (empty($this->unlock_conditions)) {
            return true;
        }

        // Check unlock conditions against user progress
        $userProgress = $this->collection->getUserProgress($user);
        if (!$userProgress) {
            return false;
        }

        // Implementation would check specific unlock conditions
        // This is a simplified version
        return true;
    }

    /**
     * Check if item is completed by user
     */
    public function isCompletedBy(User $user): bool
    {
        $progress = $this->collection->getUserProgress($user);
        
        if (!$progress || !$progress->completed_items) {
            return false;
        }

        return in_array($this->id, $progress->completed_items);
    }

    /**
     * Get download path for this item
     */
    public function getDownloadPath(): ?string
    {
        if (!$this->include_in_downloads || $this->isExternalLink()) {
            return null;
        }

        if ($this->collectable && method_exists($this->collectable, 'getDownloadPath')) {
            return $this->collectable->getDownloadPath();
        }

        return null;
    }

    /**
     * Get download filename for this item
     */
    public function getDownloadFilename(): string
    {
        if ($this->download_filename) {
            return $this->download_filename;
        }

        $title = $this->item_title;
        $extension = $this->getFileExtension();

        return $this->sanitizeFilename($title) . ($extension ? '.' . $extension : '');
    }

    /**
     * Get file extension for this item
     */
    protected function getFileExtension(): ?string
    {
        if ($this->isExternalLink()) {
            return null;
        }

        $typeExtensions = [
            'App\\Models\\Rack' => 'adg',
            'App\\Models\\Preset' => 'adv',
            'App\\Models\\Session' => 'als',
            'App\\Models\\Bundle' => 'zip',
        ];

        return $typeExtensions[$this->collectable_type] ?? null;
    }

    /**
     * Sanitize filename for downloads
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid filename characters
        $filename = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '-', $filename);
        $filename = preg_replace('/\s+/', ' ', $filename);
        $filename = trim($filename, ' .-');
        
        return substr($filename, 0, 200); // Limit length
    }

    /**
     * Update completion count
     */
    public function recordCompletion(User $user): void
    {
        $this->increment('completions_count');
        
        // Update user progress
        $progress = $this->collection->getUserProgress($user);
        if ($progress) {
            $completedItems = $progress->completed_items ?? [];
            if (!in_array($this->id, $completedItems)) {
                $completedItems[] = $this->id;
                $progress->update([
                    'completed_items' => $completedItems,
                    'items_completed' => count($completedItems),
                ]);
            }
        }
    }

    /**
     * Update view count
     */
    public function recordView(): void
    {
        $this->increment('views_count');
    }
}