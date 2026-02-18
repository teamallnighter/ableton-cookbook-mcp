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
 *     schema="Session",
 *     type="object",
 *     title="Session",
 *     description="Ableton Live session model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Progressive House Template"),
 *     @OA\Property(property="description", type="string", example="Complete progressive house production template"),
 *     @OA\Property(property="slug", type="string", example="progressive-house-template"),
 *     @OA\Property(property="tempo", type="string", example="128"),
 *     @OA\Property(property="time_signature", type="string", example="4/4"),
 *     @OA\Property(property="key_signature", type="string", example="A minor"),
 *     @OA\Property(property="track_count", type="integer", example=12),
 *     @OA\Property(property="scene_count", type="integer", example=8),
 *     @OA\Property(property="length_seconds", type="number", format="float", example=300.5),
 *     @OA\Property(property="category", type="string", example="template"),
 *     @OA\Property(property="genre", type="string", example="house"),
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
class Session extends Model
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
        'ableton_version',
        'ableton_edition',
        'tempo',
        'time_signature',
        'key_signature',
        'track_count',
        'scene_count',
        'length_seconds',
        'tracks',
        'scenes',
        'embedded_racks',
        'embedded_presets',
        'embedded_samples',
        'embedded_assets',
        'routing_info',
        'automation_data',
        'version_details',
        'parsing_errors',
        'parsing_warnings',
        'category',
        'genre',
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
        'tracks' => 'array',
        'scenes' => 'array',
        'embedded_racks' => 'array',
        'embedded_presets' => 'array',
        'embedded_samples' => 'array',
        'embedded_assets' => 'array',
        'routing_info' => 'array',
        'automation_data' => 'array',
        'version_details' => 'array',
        'parsing_errors' => 'array',
        'parsing_warnings' => 'array',
        'published_at' => 'datetime',
        'how_to_updated_at' => 'datetime',
        'last_auto_save' => 'datetime',
        'length_seconds' => 'decimal:2',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the session
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'session_tags');
    }

    /**
     * Get the ratings for the session
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(SessionRating::class);
    }

    /**
     * Get the comments for the session
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the downloads for the session
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(SessionDownload::class);
    }

    /**
     * Get the favorites for the session
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(SessionFavorite::class);
    }

    /**
     * Get the reports for the session
     */
    public function reports(): HasMany
    {
        return $this->hasMany(SessionReport::class);
    }

    /**
     * Get the bundle items that include this session
     */
    public function bundleItems(): MorphMany
    {
        return $this->morphMany(BundleItem::class, 'bundlable');
    }

    /**
     * Get the bundles that include this session
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
     * Get activity feed entries for this session
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    /**
     * Scope for published sessions
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured sessions
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for specific category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for specific genre
     */
    public function scopeByGenre($query, string $genre)
    {
        return $query->where('genre', $genre);
    }

    /**
     * Scope for specific tempo range
     */
    public function scopeByTempoRange($query, int $minTempo = null, int $maxTempo = null)
    {
        if ($minTempo) {
            $query->where('tempo', '>=', $minTempo);
        }
        if ($maxTempo) {
            $query->where('tempo', '<=', $maxTempo);
        }
        return $query;
    }

    /**
     * Get display name for the category
     */
    public function getCategoryDisplayAttribute(): string
    {
        $categoryMap = [
            'production' => 'Full Production',
            'template' => 'Project Template',
            'remix' => 'Remix Project', 
            'stems' => 'Stem Package',
            'loop_pack' => 'Loop Package',
            'sample_pack' => 'Sample Pack',
            'tutorial' => 'Tutorial Project',
        ];
        
        return $categoryMap[$this->category] ?? $this->category ?? 'Uncategorized';
    }

    /**
     * Get display name for the genre
     */
    public function getGenreDisplayAttribute(): string
    {
        $genreMap = [
            'house' => 'House',
            'techno' => 'Techno',
            'trance' => 'Trance',
            'dubstep' => 'Dubstep',
            'drum_and_bass' => 'Drum & Bass',
            'ambient' => 'Ambient',
            'minimal' => 'Minimal',
            'progressive' => 'Progressive',
            'trap' => 'Trap',
            'future_bass' => 'Future Bass',
            'synthwave' => 'Synthwave',
            'experimental' => 'Experimental',
        ];
        
        return $genreMap[$this->genre] ?? $this->genre ?? 'Unspecified';
    }

    /**
     * Check if user has rated this session
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's rating for this session
     */
    public function getUserRating(User $user): ?SessionRating
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
     * Check if session has a how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }

    /**
     * Scope for sessions with how-to articles
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
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->length_seconds) {
            return 'Unknown';
        }

        $minutes = floor($this->length_seconds / 60);
        $seconds = $this->length_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
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

    /**
     * Check if session has embedded racks
     */
    public function hasEmbeddedRacks(): bool
    {
        return !empty($this->embedded_racks) && is_array($this->embedded_racks) && count($this->embedded_racks) > 0;
    }

    /**
     * Check if session has embedded presets
     */
    public function hasEmbeddedPresets(): bool
    {
        return !empty($this->embedded_presets) && is_array($this->embedded_presets) && count($this->embedded_presets) > 0;
    }

    /**
     * Check if session has embedded samples
     */
    public function hasEmbeddedSamples(): bool
    {
        return !empty($this->embedded_samples) && is_array($this->embedded_samples) && count($this->embedded_samples) > 0;
    }

    /**
     * Get embedded racks count
     */
    public function getEmbeddedRacksCountAttribute(): int
    {
        return is_array($this->embedded_racks) ? count($this->embedded_racks) : 0;
    }

    /**
     * Get embedded presets count
     */
    public function getEmbeddedPresetsCountAttribute(): int
    {
        return is_array($this->embedded_presets) ? count($this->embedded_presets) : 0;
    }

    /**
     * Get embedded samples count
     */
    public function getEmbeddedSamplesCountAttribute(): int
    {
        return is_array($this->embedded_samples) ? count($this->embedded_samples) : 0;
    }

    /**
     * Get estimated total embedded assets size
     */
    public function getEstimatedAssetsSize(): string
    {
        // This is a rough estimation - actual implementation would need to analyze the samples
        $baseSize = $this->file_size;
        $estimatedSamplesSize = $this->getEmbeddedSamplesCountAttribute() * 2048; // Rough estimate: 2MB per sample
        
        $totalSize = $baseSize + $estimatedSamplesSize;
        
        if ($totalSize >= 1048576) {
            return round($totalSize / 1048576, 1) . ' MB';
        } else {
            return round($totalSize / 1024, 1) . ' KB';
        }
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
     * Check if session is compatible with version
     */
    public function isCompatibleWith(string $version): bool
    {
        if (!$this->ableton_version) {
            return true; // Assume compatible if version unknown
        }

        $sessionMajor = (int) explode('.', $this->ableton_version)[0];
        $checkMajor = (int) explode('.', $version)[0];

        return $checkMajor >= $sessionMajor;
    }

    /**
     * Get tempo as integer
     */
    public function getTempoIntAttribute(): ?int
    {
        return $this->tempo ? (int) $this->tempo : null;
    }

    /**
     * Get tempo range category
     */
    public function getTempoRangeAttribute(): string
    {
        $tempo = $this->getTempoIntAttribute();
        
        if (!$tempo) {
            return 'Unknown';
        }

        if ($tempo < 100) {
            return 'Slow (< 100 BPM)';
        } elseif ($tempo < 120) {
            return 'Medium (100-120 BPM)';
        } elseif ($tempo < 140) {
            return 'Fast (120-140 BPM)';
        } else {
            return 'Very Fast (140+ BPM)';
        }
    }

    /**
     * Check if session has automation data
     */
    public function hasAutomation(): bool
    {
        return !empty($this->automation_data) && is_array($this->automation_data) && count($this->automation_data) > 0;
    }

    /**
     * Get complexity score based on tracks, scenes, and embedded content
     */
    public function getComplexityScoreAttribute(): string
    {
        $score = 0;
        
        // Base score from tracks and scenes
        $score += $this->track_count * 2;
        $score += $this->scene_count * 1;
        
        // Add points for embedded content
        $score += $this->getEmbeddedRacksCountAttribute() * 3;
        $score += $this->getEmbeddedPresetsCountAttribute() * 1;
        $score += $this->getEmbeddedSamplesCountAttribute() * 0.5;
        
        // Add points for automation
        if ($this->hasAutomation()) {
            $score += 10;
        }

        if ($score < 20) {
            return 'Simple';
        } elseif ($score < 50) {
            return 'Moderate';
        } elseif ($score < 100) {
            return 'Complex';
        } else {
            return 'Very Complex';
        }
    }
}