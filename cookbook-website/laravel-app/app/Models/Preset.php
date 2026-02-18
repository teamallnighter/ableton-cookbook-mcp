<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\MarkdownService;

/**
 * @OA\Schema(
 *     schema="Preset",
 *     type="object",
 *     title="Preset",
 *     description="Ableton Live device preset model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Epic Lead Preset"),
 *     @OA\Property(property="description", type="string", example="A powerful lead preset for Wavetable"),
 *     @OA\Property(property="slug", type="string", example="epic-lead-preset"),
 *     @OA\Property(property="preset_type", type="string", enum={"instrument", "audio_effect", "midi_effect"}),
 *     @OA\Property(property="device_name", type="string", example="Wavetable"),
 *     @OA\Property(property="device_type", type="string", example="Wavetable"),
 *     @OA\Property(property="category", type="string", example="lead"),
 *     @OA\Property(property="ableton_version", type="string", example="11.3.4"),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="ratings_count", type="integer", example=25),
 *     @OA\Property(property="downloads_count", type="integer", example=150),
 *     @OA\Property(property="views_count", type="integer", example=500),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="published_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user",
 *         ref="#/components/schemas/User"
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Tag")
 *     )
 * )
 */
class Preset extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'how_to_article',
        'how_to_updated_at',
        'slug',
        'file_path',
        'file_hash',
        'file_size',
        'original_filename',
        'preset_type',
        'device_name',
        'device_type',
        'ableton_version',
        'ableton_edition',
        'parameters',
        'macro_mappings',
        'version_details',
        'parsing_errors',
        'parsing_warnings',
        'category',
        'preview_audio_path',
        'preview_image_path',
        'status',
        'processing_error',
        'published_at',
        'average_rating',
        'ratings_count',
        'downloads_count',
        'views_count',
        'comments_count',
        'likes_count',
        'is_public',
        'is_featured',
        'version',
        'last_auto_save',
        'last_auto_save_session',
    ];

    protected $casts = [
        'parameters' => 'array',
        'macro_mappings' => 'array',
        'version_details' => 'array',
        'parsing_errors' => 'array',
        'parsing_warnings' => 'array',
        'published_at' => 'datetime',
        'how_to_updated_at' => 'datetime',
        'last_auto_save' => 'datetime',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the preset
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the preset
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'preset_tags');
    }

    /**
     * Get the ratings for the preset
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(PresetRating::class);
    }

    /**
     * Get the comments for the preset
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the downloads for the preset
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(PresetDownload::class);
    }

    /**
     * Get the favorites for the preset
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(PresetFavorite::class);
    }

    /**
     * Get the reports for the preset
     */
    public function reports(): HasMany
    {
        return $this->hasMany(PresetReport::class);
    }

    /**
     * Get the bundle items that include this preset
     */
    public function bundleItems(): MorphMany
    {
        return $this->morphMany(BundleItem::class, 'bundlable');
    }

    /**
     * Get the bundles that include this preset
     */
    public function bundles(): \Illuminate\Database\Eloquent\Relations\BelongsToManyThrough
    {
        return $this->hasManyThrough(
            Bundle::class,
            BundleItem::class,
            'bundlable_id',
            'id',
            'id',
            'bundle_id'
        )->where('bundlable_type', self::class);
    }

    /**
     * Get activity feed entries for this preset
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    /**
     * Scope for published presets
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured presets
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for specific device type
     */
    public function scopeForDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope for preset type
     */
    public function scopeByType($query, string $presetType)
    {
        return $query->where('preset_type', $presetType);
    }

    /**
     * Get display name for the category
     */
    public function getCategoryDisplayAttribute(): string
    {
        $categoryMap = [
            'analog' => 'Analog',
            'bass' => 'Bass', 
            'drums' => 'Drums',
            'keys' => 'Keys',
            'lead' => 'Lead',
            'pad' => 'Pad',
            'strings' => 'Strings',
            'synth' => 'Synth',
            'texture' => 'Texture',
            'vocal' => 'Vocal',
            'delay' => 'Delay',
            'reverb' => 'Reverb', 
            'compression' => 'Compression',
            'eq' => 'EQ',
            'distortion' => 'Distortion',
            'modulation' => 'Modulation',
            'filter' => 'Filter',
            'dynamics' => 'Dynamics',
            'utility' => 'Utility',
            'creative' => 'Creative',
        ];
        
        return $categoryMap[$this->category] ?? $this->category ?? 'Uncategorized';
    }

    /**
     * Check if user has rated this preset
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's rating for this preset
     */
    public function getUserRating(User $user): ?PresetRating
    {
        return $this->ratings()->where('user_id', $user->id)->first();
    }

    /**
     * Update average rating
     */
    public function updateAverageRating(): void
    {
        $stats = $this->ratings()
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        $this->update([
            'average_rating' => $stats->average ?? 0,
            'ratings_count' => $stats->count ?? 0,
        ]);
    }

    /**
     * Increment download count
     */
    public function recordDownload(?User $user = null, string $ipAddress = null): void
    {
        $this->downloads()->create([
            'user_id' => $user?->id,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'download_token' => Str::random(64),
            'downloaded_at' => now(),
        ]);

        $this->increment('downloads_count');
    }

    /**
     * Get download URL with temporary signed URL
     */
    public function getDownloadUrl(): string
    {
        return Storage::disk('private')->temporaryUrl(
            $this->file_path,
            now()->addMinutes(5)
        );
    }

    /**
     * Get HTML version of how-to article from markdown
     */
    public function getHtmlHowToAttribute(): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        return app(MarkdownService::class)->parseToHtml($this->how_to_article);
    }

    /**
     * Get truncated preview of how-to article (plain text)
     */
    public function getHowToPreviewAttribute(int $length = 200): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        // Strip markdown formatting and get plain text
        $plainText = app(MarkdownService::class)->stripMarkdown($this->how_to_article);
        
        return Str::limit($plainText, $length);
    }

    /**
     * Check if preset has a how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }

    /**
     * Scope for presets with how-to articles
     */
    public function scopeWithHowTo($query)
    {
        return $query->whereNotNull('how_to_article')
                    ->where('how_to_article', '!=', '');
    }

    /**
     * Update how-to article timestamp
     */
    public function touchHowTo(): void
    {
        $this->how_to_updated_at = now();
        $this->save();
    }
    
    /**
     * Get reading time estimate for how-to article
     */
    public function getReadingTimeHowToAttribute(): int
    {
        if (!$this->how_to_article) {
            return 0;
        }
        
        $words = str_word_count(strip_tags($this->how_to_article));
        $minutes = ceil($words / 200); // Average reading speed
        return $minutes;
    }

    /**
     * Get device display name
     */
    public function getDeviceDisplayNameAttribute(): string
    {
        return $this->device_name ?? ($this->device_type ?? 'Unknown Device');
    }

    /**
     * Check if preset has parameters
     */
    public function hasParameters(): bool
    {
        return !empty($this->parameters) && is_array($this->parameters);
    }

    /**
     * Check if preset has macro mappings
     */
    public function hasMacroMappings(): bool
    {
        return !empty($this->macro_mappings) && is_array($this->macro_mappings);
    }

    /**
     * Get compatible Ableton versions
     */
    public function getCompatibleVersionsAttribute(): array
    {
        if (!$this->ableton_version) {
            return [];
        }

        // Extract major version
        $majorVersion = (int) explode('.', $this->ableton_version)[0];
        
        // Return compatible versions (current and newer)
        return array_filter(range($majorVersion, 12), function($version) {
            return $version >= 9; // Minimum supported version
        });
    }

    /**
     * Check if preset is compatible with version
     */
    public function isCompatibleWith(string $version): bool
    {
        if (!$this->ableton_version) {
            return true; // Assume compatible if version unknown
        }

        $presetMajor = (int) explode('.', $this->ableton_version)[0];
        $checkMajor = (int) explode('.', $version)[0];

        return $checkMajor >= $presetMajor;
    }

    /**
     * Get preset file extension
     */
    public function getFileExtensionAttribute(): string
    {
        return pathinfo($this->original_filename, PATHINFO_EXTENSION);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}