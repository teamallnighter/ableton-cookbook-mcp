<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @OA\Schema(
 *     schema="BundleItem",
 *     type="object",
 *     title="Bundle Item",
 *     description="An item within a production bundle (rack, preset, or session)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="bundle_id", type="integer", example=1),
 *     @OA\Property(property="bundlable_type", type="string", example="App\\Models\\Rack"),
 *     @OA\Property(property="bundlable_id", type="integer", example=5),
 *     @OA\Property(property="position", type="integer", example=1),
 *     @OA\Property(property="section", type="string", example="Intro"),
 *     @OA\Property(property="notes", type="string", example="Use this rack for the main lead line"),
 *     @OA\Property(property="usage_instructions", type="string", example="Load into track 3, adjust filter cutoff"),
 *     @OA\Property(property="is_downloadable_individually", type="boolean", example=true),
 *     @OA\Property(property="is_required", type="boolean", example=false),
 *     @OA\Property(property="individual_downloads_count", type="integer", example=25),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="bundle",
 *         ref="#/components/schemas/Bundle"
 *     )
 * )
 */
class BundleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_id',
        'bundlable_type',
        'bundlable_id',
        'position',
        'section',
        'notes',
        'usage_instructions',
        'is_downloadable_individually',
        'is_required',
        'individual_downloads_count',
    ];

    protected $casts = [
        'is_downloadable_individually' => 'boolean',
        'is_required' => 'boolean',
        'individual_downloads_count' => 'integer',
        'position' => 'integer',
    ];

    /**
     * Get the bundle that owns this item
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Get the bundlable model (rack, preset, or session)
     */
    public function bundlable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for items in a specific bundle
     */
    public function scopeInBundle($query, int $bundleId)
    {
        return $query->where('bundle_id', $bundleId);
    }

    /**
     * Scope for items of a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('bundlable_type', $type);
    }

    /**
     * Scope for items in a specific section
     */
    public function scopeInSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /**
     * Scope for required items
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for individually downloadable items
     */
    public function scopeDownloadable($query)
    {
        return $query->where('is_downloadable_individually', true);
    }

    /**
     * Scope ordered by position
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Get the item type display name
     */
    public function getItemTypeDisplayAttribute(): string
    {
        return match($this->bundlable_type) {
            Rack::class => 'Rack',
            Preset::class => 'Preset',
            Session::class => 'Session',
            default => 'Unknown'
        };
    }

    /**
     * Get the item title
     */
    public function getItemTitleAttribute(): string
    {
        return $this->bundlable?->title ?? 'Untitled';
    }

    /**
     * Get the item description
     */
    public function getItemDescriptionAttribute(): ?string
    {
        return $this->bundlable?->description;
    }

    /**
     * Get the item file size
     */
    public function getItemFileSizeAttribute(): ?int
    {
        return $this->bundlable?->file_size;
    }

    /**
     * Get formatted item file size
     */
    public function getFormattedItemFileSizeAttribute(): string
    {
        $bytes = $this->getItemFileSizeAttribute();
        
        if (!$bytes) {
            return 'Unknown';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * Check if item can be downloaded individually
     */
    public function canDownloadIndividually(): bool
    {
        return $this->is_downloadable_individually 
            && $this->bundle?->allow_individual_downloads 
            && !$this->bundle?->require_full_download;
    }

    /**
     * Get download URL for this item (if allowed)
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->canDownloadIndividually()) {
            return null;
        }

        return app(\App\Services\BundleManager\BundleManager::class)
            ->getItemDownloadUrl($this);
    }

    /**
     * Record individual download
     */
    public function recordDownload(?User $user = null): void
    {
        $this->increment('individual_downloads_count');

        // Record download on the bundled item as well
        if ($this->bundlable && method_exists($this->bundlable, 'recordDownload')) {
            $this->bundlable->recordDownload($user);
        }
    }

    /**
     * Get usage instructions with fallbacks
     */
    public function getUsageInstructionsDisplayAttribute(): string
    {
        if ($this->usage_instructions) {
            return $this->usage_instructions;
        }

        // Provide default instructions based on type
        return match($this->bundlable_type) {
            Rack::class => 'Drag and drop the .adg file into Ableton Live to load the rack.',
            Preset::class => 'Load the preset into the appropriate device in Ableton Live.',
            Session::class => 'Open the .als file directly in Ableton Live.',
            default => 'Import this item into Ableton Live.'
        };
    }

    /**
     * Get section display name with fallback
     */
    public function getSectionDisplayAttribute(): string
    {
        return $this->section ?? 'Main';
    }

    /**
     * Check if item has custom notes
     */
    public function hasNotes(): bool
    {
        return !empty($this->notes);
    }

    /**
     * Check if item has custom usage instructions
     */
    public function hasUsageInstructions(): bool
    {
        return !empty($this->usage_instructions);
    }

    /**
     * Get item preview image URL
     */
    public function getPreviewImageUrlAttribute(): ?string
    {
        $item = $this->bundlable;
        
        if (!$item || !$item->preview_image_path) {
            return null;
        }

        return asset('storage/' . $item->preview_image_path);
    }

    /**
     * Get item preview audio URL
     */
    public function getPreviewAudioUrlAttribute(): ?string
    {
        $item = $this->bundlable;
        
        if (!$item || !$item->preview_audio_path) {
            return null;
        }

        return asset('storage/' . $item->preview_audio_path);
    }

    /**
     * Check if item has preview media
     */
    public function hasPreviewMedia(): bool
    {
        $item = $this->bundlable;
        return $item && ($item->preview_image_path || $item->preview_audio_path);
    }

    /**
     * Get the item owner
     */
    public function getItemOwnerAttribute(): ?User
    {
        return $this->bundlable?->user;
    }

    /**
     * Check if the bundle owner owns this item
     */
    public function isOwnedByBundleOwner(): bool
    {
        $bundleOwner = $this->bundle?->user_id;
        $itemOwner = $this->getItemOwnerAttribute()?->id;
        
        return $bundleOwner && $itemOwner && $bundleOwner === $itemOwner;
    }

    /**
     * Get item creation date
     */
    public function getItemCreatedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->bundlable?->created_at;
    }

    /**
     * Get item rating information
     */
    public function getItemRatingAttribute(): array
    {
        $item = $this->bundlable;
        
        if (!$item) {
            return ['average' => 0, 'count' => 0];
        }

        return [
            'average' => $item->average_rating ?? 0,
            'count' => $item->ratings_count ?? 0,
        ];
    }

    /**
     * Get item download count
     */
    public function getItemDownloadsCountAttribute(): int
    {
        return $this->bundlable?->downloads_count ?? 0;
    }

    /**
     * Get combined download count (individual + item)
     */
    public function getTotalDownloadsCountAttribute(): int
    {
        return $this->individual_downloads_count + $this->getItemDownloadsCountAttribute();
    }

    /**
     * Get item tags
     */
    public function getItemTagsAttribute(): array
    {
        $item = $this->bundlable;
        
        if (!$item || !method_exists($item, 'tags')) {
            return [];
        }

        return $item->tags->pluck('name')->toArray();
    }

    /**
     * Check if item is published and public
     */
    public function isItemPublic(): bool
    {
        $item = $this->bundlable;
        
        if (!$item) {
            return false;
        }

        return $item->is_public 
            && $item->status === 'approved' 
            && $item->published_at;
    }

    /**
     * Get full item data for API responses
     */
    public function getFullItemDataAttribute(): array
    {
        $item = $this->bundlable;
        
        if (!$item) {
            return [];
        }

        return [
            'id' => $item->id,
            'uuid' => $item->uuid ?? null,
            'title' => $item->title,
            'description' => $item->description,
            'slug' => $item->slug ?? null,
            'type' => $this->getItemTypeDisplayAttribute(),
            'file_size' => $item->file_size,
            'formatted_file_size' => $this->getFormattedItemFileSizeAttribute(),
            'created_at' => $item->created_at,
            'rating' => $this->getItemRatingAttribute(),
            'downloads_count' => $this->getItemDownloadsCountAttribute(),
            'tags' => $this->getItemTagsAttribute(),
            'owner' => $item->user?->name,
            'preview_image_url' => $this->getPreviewImageUrlAttribute(),
            'preview_audio_url' => $this->getPreviewAudioUrlAttribute(),
        ];
    }
}