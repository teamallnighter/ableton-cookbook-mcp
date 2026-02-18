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
 *     schema="Bundle",
 *     type="object",
 *     title="Bundle",
 *     description="Production bundle containing racks, presets, and sessions",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Progressive House Production Pack"),
 *     @OA\Property(property="description", type="string", example="Complete progressive house production bundle"),
 *     @OA\Property(property="slug", type="string", example="progressive-house-production-pack"),
 *     @OA\Property(property="bundle_type", type="string", enum={"production", "template", "sample_pack", "tutorial", "remix_stems"}),
 *     @OA\Property(property="genre", type="string", example="house"),
 *     @OA\Property(property="category", type="string", example="production"),
 *     @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
 *     @OA\Property(property="racks_count", type="integer", example=3),
 *     @OA\Property(property="presets_count", type="integer", example=8),
 *     @OA\Property(property="sessions_count", type="integer", example=1),
 *     @OA\Property(property="total_items_count", type="integer", example=12),
 *     @OA\Property(property="is_free", type="boolean", example=true),
 *     @OA\Property(property="bundle_price", type="number", format="float", example=29.99),
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
class Bundle extends Model
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
        'bundle_type',
        'genre',
        'mood',
        'energy_level',
        'estimated_completion_time',
        'racks_count',
        'presets_count',
        'sessions_count',
        'total_items_count',
        'min_ableton_version',
        'required_packs',
        'required_plugins',
        'difficulty_level',
        'allow_individual_downloads',
        'require_full_download',
        'bundle_price',
        'is_free',
        'archive_path',
        'archive_hash',
        'archive_size',
        'archive_updated_at',
        'category',
        'preview_audio_path',
        'preview_image_path',
        'cover_image_path',
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
        'required_packs' => 'array',
        'required_plugins' => 'array',
        'published_at' => 'datetime',
        'how_to_updated_at' => 'datetime',
        'last_auto_save' => 'datetime',
        'archive_updated_at' => 'datetime',
        'estimated_completion_time' => 'decimal:2',
        'bundle_price' => 'decimal:2',
        'allow_individual_downloads' => 'boolean',
        'require_full_download' => 'boolean',
        'is_free' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'average_rating' => 'decimal:2',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the bundle
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the bundle
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'bundle_tags');
    }

    /**
     * Get the items in this bundle
     */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('position');
    }

    /**
     * Get the racks in this bundle
     */
    public function racks()
    {
        return $this->hasManyThrough(
            Rack::class,
            BundleItem::class,
            'bundle_id',
            'id',
            'id',
            'bundlable_id'
        )->where('bundlable_type', Rack::class);
    }

    /**
     * Get the presets in this bundle
     */
    public function presets()
    {
        return $this->hasManyThrough(
            Preset::class,
            BundleItem::class,
            'bundle_id',
            'id',
            'id',
            'bundlable_id'
        )->where('bundlable_type', Preset::class);
    }

    /**
     * Get the sessions in this bundle
     */
    public function sessions()
    {
        return $this->hasManyThrough(
            Session::class,
            BundleItem::class,
            'bundle_id',
            'id',
            'id',
            'bundlable_id'
        )->where('bundlable_type', Session::class);
    }

    /**
     * Get the ratings for the bundle
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(BundleRating::class);
    }

    /**
     * Get the comments for the bundle
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the downloads for the bundle
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(BundleDownload::class);
    }

    /**
     * Get the favorites for the bundle
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(BundleFavorite::class);
    }

    /**
     * Get the reports for the bundle
     */
    public function reports(): HasMany
    {
        return $this->hasMany(BundleReport::class);
    }

    /**
     * Get activity feed entries for this bundle
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    /**
     * Scope for published bundles
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured bundles
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for free bundles
     */
    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    /**
     * Scope for paid bundles
     */
    public function scopePaid($query)
    {
        return $query->where('is_free', false);
    }

    /**
     * Scope for specific bundle type
     */
    public function scopeByType($query, string $bundleType)
    {
        return $query->where('bundle_type', $bundleType);
    }

    /**
     * Scope for specific genre
     */
    public function scopeByGenre($query, string $genre)
    {
        return $query->where('genre', $genre);
    }

    /**
     * Scope for specific difficulty level
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Get display name for the bundle type
     */
    public function getBundleTypeDisplayAttribute(): string
    {
        $typeMap = [
            'production' => 'Full Production',
            'template' => 'Project Template',
            'sample_pack' => 'Sample Pack',
            'tutorial' => 'Tutorial Bundle',
            'remix_stems' => 'Remix Stems',
        ];
        
        return $typeMap[$this->bundle_type] ?? $this->bundle_type ?? 'Bundle';
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
     * Get display name for difficulty level
     */
    public function getDifficultyDisplayAttribute(): string
    {
        $difficultyMap = [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
        ];
        
        return $difficultyMap[$this->difficulty_level] ?? $this->difficulty_level ?? 'Intermediate';
    }

    /**
     * Check if user has rated this bundle
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's rating for this bundle
     */
    public function getUserRating(User $user): ?BundleRating
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
     * Get download URL for bundle archive
     */
    public function getDownloadUrl(): string
    {
        // This will be handled by the BundleManager service
        return app(\App\Services\BundleManager\BundleManager::class)
            ->getBundleDownloadUrl($this);
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
     * Check if bundle has a how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }

    /**
     * Scope for bundles with how-to articles
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
     * Get formatted archive size
     */
    public function getFormattedArchiveSizeAttribute(): string
    {
        if (!$this->archive_size) {
            return 'Unknown';
        }

        $bytes = $this->archive_size;
        
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_free) {
            return 'Free';
        }

        return '$' . number_format($this->bundle_price, 2);
    }

    /**
     * Check if bundle has archive
     */
    public function hasArchive(): bool
    {
        return !empty($this->archive_path) && Storage::disk('private')->exists($this->archive_path);
    }

    /**
     * Check if archive is current
     */
    public function isArchiveCurrent(): bool
    {
        if (!$this->hasArchive() || !$this->archive_updated_at) {
            return false;
        }

        return $this->archive_updated_at >= $this->updated_at;
    }

    /**
     * Get required Ableton packs as formatted list
     */
    public function getRequiredPacksListAttribute(): string
    {
        if (empty($this->required_packs) || !is_array($this->required_packs)) {
            return 'None';
        }

        return implode(', ', $this->required_packs);
    }

    /**
     * Get required plugins as formatted list
     */
    public function getRequiredPluginsListAttribute(): string
    {
        if (empty($this->required_plugins) || !is_array($this->required_plugins)) {
            return 'None';
        }

        return implode(', ', $this->required_plugins);
    }

    /**
     * Get estimated completion time formatted
     */
    public function getFormattedCompletionTimeAttribute(): string
    {
        if (!$this->estimated_completion_time) {
            return 'Unknown';
        }

        $hours = floor($this->estimated_completion_time);
        $minutes = ($this->estimated_completion_time - $hours) * 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get bundle contents summary
     */
    public function getContentsSummaryAttribute(): string
    {
        $parts = [];

        if ($this->racks_count > 0) {
            $parts[] = $this->racks_count . ' rack' . ($this->racks_count > 1 ? 's' : '');
        }

        if ($this->presets_count > 0) {
            $parts[] = $this->presets_count . ' preset' . ($this->presets_count > 1 ? 's' : '');
        }

        if ($this->sessions_count > 0) {
            $parts[] = $this->sessions_count . ' session' . ($this->sessions_count > 1 ? 's' : '');
        }

        return !empty($parts) ? implode(', ', $parts) : 'Empty bundle';
    }

    /**
     * Check if bundle is ready for download
     */
    public function isReadyForDownload(): bool
    {
        return $this->status === 'approved' 
            && $this->is_public 
            && $this->total_items_count > 0;
    }

    /**
     * Check if individual downloads are allowed
     */
    public function allowsIndividualDownloads(): bool
    {
        return $this->allow_individual_downloads && !$this->require_full_download;
    }

    /**
     * Get items grouped by type
     */
    public function getItemsByType(): array
    {
        $items = $this->items()->with('bundlable')->get();

        return $items->groupBy('bundlable_type')->map(function ($group) {
            return $group->sortBy('position');
        })->toArray();
    }

    /**
     * Get items in specified section
     */
    public function getItemsInSection(string $section)
    {
        return $this->items()->where('section', $section)->with('bundlable')->orderBy('position')->get();
    }

    /**
     * Get all unique sections in bundle
     */
    public function getSections(): array
    {
        return $this->items()
            ->whereNotNull('section')
            ->distinct()
            ->pluck('section')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Check if bundle has required items (for completion validation)
     */
    public function hasAllRequiredItems(): bool
    {
        $requiredCount = $this->items()->where('is_required', true)->count();
        return $requiredCount === 0 || $this->total_items_count >= $requiredCount;
    }
}