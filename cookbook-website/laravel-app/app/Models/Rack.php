<?php

/**
 * @OA\Schema(
 *     schema="Rack",
 *     type="object",
 *     title="Rack",
 *     description="Ableton Live rack model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Epic Bass Rack"),
 *     @OA\Property(property="description", type="string", example="A powerful bass rack with multiple layers"),
 *     @OA\Property(property="slug", type="string", example="epic-bass-rack"),
 *     @OA\Property(property="rack_type", type="string", enum={"instrument", "audio_effect", "midi_effect"}),
 *     @OA\Property(property="category", type="string", example="bass"),
 *     @OA\Property(property="device_count", type="integer", example=5),
 *     @OA\Property(property="chain_count", type="integer", example=3),
 *     @OA\Property(property="ableton_version", type="string", example="11.3.4"),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="ratings_count", type="integer", example=25),
 *     @OA\Property(property="downloads_count", type="integer", example=150),
 *     @OA\Property(property="views_count", type="integer", example=500),
 *     @OA\Property(property="comments_count", type="integer", example=12),
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


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\MarkdownService;

class Rack extends Model
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
        'rack_type',
        'category',
        'device_count',
        'chain_count',
        'ableton_version',
        'ableton_edition',
        'macro_controls',
        'devices',
        'chains',
        'chain_annotations',
        'version_details',
        'parsing_errors',
        'parsing_warnings',
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
        'enhanced_analysis_complete',
        'enhanced_analysis_started_at',
        'enhanced_analysis_completed_at',
        'has_nested_chains',
        'total_nested_chains',
        'max_chain_depth',
    ];

    protected $casts = [
        'macro_controls' => 'array',
        'devices' => 'array',
        'chains' => 'array',
        'chain_annotations' => 'array',
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
        'enhanced_analysis_complete' => 'boolean',
        'enhanced_analysis_started_at' => 'datetime',
        'enhanced_analysis_completed_at' => 'datetime',
        'has_nested_chains' => 'boolean',
    ];

    /**
     * Get the user that owns the rack
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tags for the rack
     */
    /**
     * Get the display name for the category
     */
    public function getCategoryDisplayAttribute()
    {
        // Map numeric categories to display names
        $categoryMap = [
            "1" => "Lead",
            "2" => "Bass",
            "3" => "Drums",
            "4" => "Pad",
            "5" => "Arp",
            "6" => "FX",
            "7" => "Texture",
            "8" => "Vocal",
            "dynamics" => "Dynamics",
            "time-based" => "Time Based",
            "modulation" => "Modulation",
            "spectral" => "Spectral",
            "filters" => "Filters",
            "creative-effects" => "Creative Effects",
            "utility" => "Utility",
            "mixing" => "Mixing",
            "distortion" => "Distortion"
        ];

        return $categoryMap[$this->category] ?? $this->category;
    }


    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'rack_tags');
    }

    /**
     * Get the ratings for the rack
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(RackRating::class);
    }

    /**
     * Get the comments for the rack
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the downloads for the rack
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(RackDownload::class);
    }

    /**
     * Get the favorites for the rack
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(RackFavorite::class);
    }

    /**
     * Get the reports for the rack
     */
    public function reports(): HasMany
    {
        return $this->hasMany(RackReport::class);
    }

    /**
     * Get the collections that include this rack
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_racks')
            ->withPivot('position', 'notes')
            ->withTimestamps();
    }

    /**
     * Get activity feed entries for this rack
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    /**
     * Scope for published racks
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured racks
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check if user has rated this rack
     */
    public function hasBeenRatedBy(User $user): bool
    {
        return $this->ratings()->where('user_id', $user->id)->exists();
    }

    /**
     * Get user's rating for this rack
     */
    public function getUserRating(User $user): ?RackRating
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
    public function getHowToPreviewAttribute(?int $length = 200): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        // Strip markdown formatting and get plain text
        $plainText = app(MarkdownService::class)->stripMarkdown($this->how_to_article);

        return Str::limit($plainText, $length ?? 200);
    }

    /**
     * Check if rack has a how-to article
     */
    public function hasHowToArticle(): bool
    {
        return !empty($this->how_to_article);
    }

    /**
     * Scope for racks with how-to articles
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
    public function getReadingTimeHowToAttribute()
    {
        if (!$this->how_to_article) {
            return 0;
        }

        $words = str_word_count(strip_tags($this->how_to_article));
        $minutes = ceil($words / 200); // Average reading speed
        return $minutes;
    }

    /**
     * Get the enhanced analysis for this rack
     */
    public function enhancedAnalysis(): HasOne
    {
        return $this->hasOne(EnhancedRackAnalysis::class);
    }

    /**
     * Get all nested chains for this rack
     */
    public function nestedChains(): HasMany
    {
        return $this->hasMany(NestedChain::class);
    }

    /**
     * Get root nested chains (no parent) for this rack
     */
    public function rootNestedChains(): HasMany
    {
        return $this->hasMany(NestedChain::class)->whereNull('parent_chain_id');
    }

    /**
     * Get nested chains ordered by hierarchy
     */
    public function hierarchicalNestedChains(): HasMany
    {
        return $this->hasMany(NestedChain::class)
            ->orderBy('depth_level')
            ->orderBy('chain_identifier');
    }

    /**
     * Scope for racks with completed enhanced analysis
     */
    public function scopeEnhancedAnalysisComplete($query)
    {
        return $query->where('enhanced_analysis_complete', true);
    }

    /**
     * Scope for racks with nested chains
     */
    public function scopeWithNestedChains($query)
    {
        return $query->where('has_nested_chains', true);
    }

    /**
     * Scope for constitutionally compliant racks
     */
    public function scopeConstitutionallyCompliant($query)
    {
        return $query->whereHas('enhancedAnalysis', function ($q) {
            $q->where('constitutional_compliant', true);
        });
    }

    /**
     * Scope for racks with minimum chain depth
     */
    public function scopeWithMinChainDepth($query, int $minDepth)
    {
        return $query->where('max_chain_depth', '>=', $minDepth);
    }

    /**
     * Check if rack has completed enhanced analysis
     */
    public function hasEnhancedAnalysis(): bool
    {
        return $this->enhanced_analysis_complete && $this->enhancedAnalysis !== null;
    }

    /**
     * Check if rack is constitutionally compliant
     */
    public function isConstitutionallyCompliant(): bool
    {
        return $this->hasEnhancedAnalysis() &&
            $this->enhancedAnalysis->constitutional_compliant;
    }

    /**
     * Check if rack needs enhanced analysis
     */
    public function needsEnhancedAnalysis(): bool
    {
        return !$this->enhanced_analysis_complete ||
            $this->enhancedAnalysis === null;
    }

    /**
     * Get the total device count including nested chains
     */
    public function getTotalDeviceCountIncludingChainsAttribute(): int
    {
        $baseDeviceCount = $this->device_count ?? 0;
        $chainDeviceCount = $this->nestedChains()->sum('device_count');

        return $baseDeviceCount + $chainDeviceCount;
    }

    /**
     * Get nested chain hierarchy as a tree structure
     */
    public function getNestedChainHierarchy(): array
    {
        $rootChains = $this->rootNestedChains()->with('descendants')->get();

        return $rootChains->map(function ($chain) {
            return $this->buildChainTree($chain);
        })->toArray();
    }

    /**
     * Build chain tree recursively
     */
    private function buildChainTree(NestedChain $chain): array
    {
        $node = [
            'id' => $chain->id,
            'chain_identifier' => $chain->chain_identifier,
            'depth_level' => $chain->depth_level,
            'device_count' => $chain->device_count,
            'is_empty' => $chain->is_empty,
            'chain_type' => $chain->chain_type,
            'devices' => $chain->devices,
            'child_chains' => []
        ];

        foreach ($chain->childChains as $child) {
            $node['child_chains'][] = $this->buildChainTree($child);
        }

        return $node;
    }

    /**
     * Get chain analysis summary
     */
    public function getChainAnalysisSummary(): array
    {
        if (!$this->hasEnhancedAnalysis()) {
            return [
                'analyzed' => false,
                'total_chains' => 0,
                'max_depth' => 0,
                'constitutional_compliant' => false
            ];
        }

        $analysis = $this->enhancedAnalysis;

        return [
            'analyzed' => true,
            'total_chains' => $analysis->total_chains_detected,
            'max_depth' => $analysis->max_nesting_depth,
            'constitutional_compliant' => $analysis->constitutional_compliant,
            'has_nested_chains' => $analysis->has_nested_chains,
            'total_devices' => $analysis->total_devices,
            'analysis_duration_ms' => $analysis->analysis_duration_ms,
            'performance_rating' => $analysis->getPerformanceRating(),
            'complexity_rating' => $analysis->getComplexityRating(),
            'processed_at' => $analysis->processed_at
        ];
    }

    /**
     * Start enhanced analysis process
     */
    public function startEnhancedAnalysis(): void
    {
        $this->update([
            'enhanced_analysis_complete' => false,
            'enhanced_analysis_started_at' => now(),
            'enhanced_analysis_completed_at' => null
        ]);
    }

    /**
     * Complete enhanced analysis process
     */
    public function completeEnhancedAnalysis(array $results): void
    {
        $this->update([
            'enhanced_analysis_complete' => true,
            'enhanced_analysis_completed_at' => now(),
            'has_nested_chains' => $results['has_nested_chains'] ?? false,
            'total_nested_chains' => $results['total_chains_detected'] ?? 0,
            'max_chain_depth' => $results['max_nesting_depth'] ?? 0
        ]);
    }

    /**
     * Mark enhanced analysis as failed
     */
    public function failEnhancedAnalysis(string $error = null): void
    {
        $this->update([
            'enhanced_analysis_complete' => false,
            'enhanced_analysis_started_at' => null,
            'enhanced_analysis_completed_at' => null,
            'processing_error' => $error
        ]);
    }

    /**
     * Get constitutional compliance status
     */
    public function getConstitutionalComplianceStatus(): array
    {
        if (!$this->hasEnhancedAnalysis()) {
            return [
                'status' => 'not_analyzed',
                'compliant' => false,
                'issues' => ['Enhanced analysis not completed']
            ];
        }

        $analysis = $this->enhancedAnalysis;

        return [
            'status' => $analysis->constitutional_compliant ? 'compliant' : 'non_compliant',
            'compliant' => $analysis->constitutional_compliant,
            'issues' => $analysis->compliance_issues ?? [],
            'all_chains_detected' => $analysis->has_nested_chains || $analysis->total_chains_detected > 0,
            'analysis_duration_acceptable' => $analysis->analysis_duration_ms <= 5000
        ];
    }

    /**
     * Check if rack has been analyzed in the last N days
     */
    public function hasRecentEnhancedAnalysis(int $days = 30): bool
    {
        return $this->enhanced_analysis_completed_at &&
            $this->enhanced_analysis_completed_at->isAfter(now()->subDays($days));
    }

    /**
     * Get analysis age in days
     */
    public function getAnalysisAgeInDays(): ?int
    {
        if (!$this->enhanced_analysis_completed_at) {
            return null;
        }

        return (int) $this->enhanced_analysis_completed_at->diffInDays(now());
    }

    /**
     * Check if the analysis_complete field exists and add it if missing
     */
    public function getAnalysisCompleteAttribute(): bool
    {
        // Fallback to basic analysis if enhanced analysis not available
        return $this->attributes['analysis_complete'] ?? false;
    }
}
