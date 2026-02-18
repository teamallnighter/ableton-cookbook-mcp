<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\MarkdownService;

/**
 * @OA\Schema(
 *     schema="EnhancedCollection",
 *     type="object",
 *     title="Enhanced Collection",
 *     description="Mixed-content collection with learning path capabilities",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Progressive House Production Cookbook"),
 *     @OA\Property(property="description", type="string", example="Complete guide to progressive house production"),
 *     @OA\Property(property="collection_type", type="string", enum={"genre_cookbook", "technique_masterclass", "artist_series", "quick_start_pack", "preset_library", "custom"}),
 *     @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
 *     @OA\Property(property="is_learning_path", type="boolean", example=false),
 *     @OA\Property(property="items_count", type="integer", example=15),
 *     @OA\Property(property="completions_count", type="integer", example=42),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.7),
 *     @OA\Property(property="is_featured", type="boolean", example=true)
 * )
 */
class EnhancedCollection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'how_to_article',
        'how_to_updated_at',
        'slug',
        'cover_image_path',
        'preview_image_path',
        'collection_type',
        'difficulty_level',
        'genre',
        'mood',
        'energy_level',
        'estimated_completion_time',
        'required_packs',
        'required_plugins',
        'min_ableton_version',
        'is_free',
        'collection_price',
        'allow_individual_downloads',
        'require_full_download',
        'is_public',
        'is_featured',
        'status',
        'published_at',
        'is_learning_path',
        'prerequisites',
        'has_certificate',
        'learning_objectives',
        'archive_path',
        'archive_hash',
        'archive_size',
        'archive_updated_at',
        'average_rating',
        'ratings_count',
        'downloads_count',
        'views_count',
        'saves_count',
        'comments_count',
        'likes_count',
        'items_count',
        'completions_count',
        'last_auto_save',
        'last_auto_save_session',
        'version',
    ];

    protected $casts = [
        'required_packs' => 'array',
        'required_plugins' => 'array',
        'prerequisites' => 'array',
        'learning_objectives' => 'array',
        'published_at' => 'datetime',
        'how_to_updated_at' => 'datetime',
        'last_auto_save' => 'datetime',
        'archive_updated_at' => 'datetime',
        'estimated_completion_time' => 'decimal:2',
        'collection_price' => 'decimal:2',
        'allow_individual_downloads' => 'boolean',
        'require_full_download' => 'boolean',
        'is_free' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'is_learning_path' => 'boolean',
        'has_certificate' => 'boolean',
        'average_rating' => 'decimal:2',
        'version' => 'integer',
    ];

    /**
     * Get the user that owns the collection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in this collection
     */
    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class, 'collection_id')->orderBy('position');
    }

    /**
     * Get the tags for the collection
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'collection_tags');
    }

    /**
     * Get all racks in this collection
     */
    public function racks()
    {
        return $this->hasManyThrough(
            Rack::class,
            CollectionItem::class,
            'collection_id',
            'id',
            'id',
            'collectable_id'
        )->where('collectable_type', Rack::class);
    }

    /**
     * Get all presets in this collection
     */
    public function presets()
    {
        return $this->hasManyThrough(
            Preset::class,
            CollectionItem::class,
            'collection_id',
            'id',
            'id',
            'collectable_id'
        )->where('collectable_type', Preset::class);
    }

    /**
     * Get all sessions in this collection
     */
    public function sessions()
    {
        return $this->hasManyThrough(
            Session::class,
            CollectionItem::class,
            'collection_id',
            'id',
            'id',
            'collectable_id'
        )->where('collectable_type', Session::class);
    }

    /**
     * Get all bundles in this collection
     */
    public function bundles()
    {
        return $this->hasManyThrough(
            Bundle::class,
            CollectionItem::class,
            'collection_id',
            'id',
            'id',
            'collectable_id'
        )->where('collectable_type', Bundle::class);
    }

    /**
     * Get all blog posts in this collection
     */
    public function blogPosts()
    {
        return $this->hasManyThrough(
            BlogPost::class,
            CollectionItem::class,
            'collection_id',
            'id',
            'id',
            'collectable_id'
        )->where('collectable_type', BlogPost::class);
    }

    /**
     * Get the ratings for the collection
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(CollectionRating::class);
    }

    /**
     * Get the comments for the collection
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the saves for the collection
     */
    public function saves(): HasMany
    {
        return $this->hasMany(CollectionSave::class);
    }

    /**
     * Get the downloads for the collection
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(CollectionDownload::class);
    }

    /**
     * Get user progress for this collection
     */
    public function userProgress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'progressable');
    }

    /**
     * Get analytics for this collection
     */
    public function analytics(): MorphMany
    {
        return $this->morphMany(CollectionAnalytics::class, 'analyticsable');
    }

    /**
     * Get activity feed entries for this collection
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    // SCOPES

    /**
     * Scope for published collections
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured collections
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for learning paths
     */
    public function scopeLearningPaths($query)
    {
        return $query->where('is_learning_path', true);
    }

    /**
     * Scope by collection type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('collection_type', $type);
    }

    /**
     * Scope by difficulty level
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Scope for free collections
     */
    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    // ACCESSORS & MUTATORS

    /**
     * Get display name for collection type
     */
    public function getCollectionTypeDisplayAttribute(): string
    {
        $typeMap = [
            'genre_cookbook' => 'Genre Cookbook',
            'technique_masterclass' => 'Technique Masterclass',
            'artist_series' => 'Artist Series',
            'quick_start_pack' => 'Quick Start Pack',
            'preset_library' => 'Preset Library',
            'custom' => 'Custom Collection',
        ];
        
        return $typeMap[$this->collection_type] ?? $this->collection_type ?? 'Collection';
    }

    /**
     * Get HTML version of how-to article
     */
    public function getHtmlHowToAttribute(): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        return app(MarkdownService::class)->parseToHtml($this->how_to_article);
    }

    /**
     * Get how-to preview
     */
    public function getHowToPreviewAttribute(int $length = 200): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        $plainText = app(MarkdownService::class)->stripMarkdown($this->how_to_article);
        return Str::limit($plainText, $length);
    }

    /**
     * Get reading time for how-to article
     */
    public function getReadingTimeHowToAttribute(): int
    {
        if (!$this->how_to_article) {
            return 0;
        }
        
        $words = str_word_count(strip_tags($this->how_to_article));
        return ceil($words / 200); // Average reading speed
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_free) {
            return 'Free';
        }

        return '$' . number_format($this->collection_price, 2);
    }

    /**
     * Get formatted completion time
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
     * Get content summary
     */
    public function getContentsSummaryAttribute(): string
    {
        $types = $this->items()
            ->selectRaw('collectable_type, COUNT(*) as count')
            ->groupBy('collectable_type')
            ->get()
            ->map(function ($item) {
                $type = class_basename($item->collectable_type);
                $count = $item->count;
                return $count . ' ' . Str::plural(strtolower($type), $count);
            })
            ->toArray();

        return !empty($types) ? implode(', ', $types) : 'Empty collection';
    }

    // BUSINESS METHODS

    /**
     * Check if user has rated this collection
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user has saved this collection
     */
    public function hasBeenSavedBy(User $user): bool
    {
        return $this->saves()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's progress on this collection
     */
    public function getUserProgress(User $user): ?UserProgress
    {
        return $this->userProgress()->where('user_id', $user->id)->first();
    }

    /**
     * Check if collection is ready for download
     */
    public function isReadyForDownload(): bool
    {
        return $this->status === 'approved' 
            && $this->is_public 
            && $this->items_count > 0;
    }

    /**
     * Check if individual downloads are allowed
     */
    public function allowsIndividualDownloads(): bool
    {
        return $this->allow_individual_downloads && !$this->require_full_download;
    }

    /**
     * Get items grouped by section
     */
    public function getItemsBySection(): array
    {
        return $this->items()
            ->with('collectable')
            ->get()
            ->groupBy('section')
            ->map(function ($group) {
                return $group->sortBy('position');
            })
            ->toArray();
    }

    /**
     * Get all unique sections in collection
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
     * Update various counts
     */
    public function updateCounts(): void
    {
        $this->update([
            'items_count' => $this->items()->count(),
        ]);
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
     * Record a download
     */
    public function recordDownload(?User $user = null, string $ipAddress = null): void
    {
        $this->downloads()->create([
            'user_id' => $user?->id,
            'download_token' => Str::random(64),
            'download_type' => 'full_collection',
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'downloaded_at' => now(),
        ]);

        $this->increment('downloads_count');
    }

    /**
     * Check if collection has archive
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
     * Touch how-to article timestamp
     */
    public function touchHowTo(): void
    {
        $this->how_to_updated_at = now();
        $this->save();
    }

    /**
     * Check if collection has how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }
}