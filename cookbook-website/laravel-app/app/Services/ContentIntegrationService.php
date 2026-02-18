<?php

namespace App\Services;

use App\Models\EnhancedCollection;
use App\Models\CollectionItem;
use App\Models\Rack;
use App\Models\Preset;
use App\Models\Session;
use App\Models\Bundle;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Service for integrating Collections with existing content types
 */
class ContentIntegrationService
{
    /**
     * Content type mappings for polymorphic relationships
     */
    protected array $contentTypeMap = [
        'rack' => Rack::class,
        'preset' => Preset::class,
        'session' => Session::class,
        'bundle' => Bundle::class,
        'blog_post' => BlogPost::class,
    ];

    /**
     * Get content suggestions for collection building
     */
    public function getContentSuggestions(
        EnhancedCollection $collection,
        string $contentType = null,
        int $limit = 10
    ): Collection {
        $cacheKey = "content_suggestions:{$collection->id}:{$contentType}:{$limit}";
        
        return Cache::remember($cacheKey, 1800, function () use ($collection, $contentType, $limit) {
            if ($contentType && isset($this->contentTypeMap[$contentType])) {
                return $this->getSuggestionsByType($collection, $this->contentTypeMap[$contentType], $limit);
            }

            return $this->getMixedContentSuggestions($collection, $limit);
        });
    }

    /**
     * Get suggestions by specific content type
     */
    protected function getSuggestionsByType(
        EnhancedCollection $collection,
        string $modelClass,
        int $limit
    ): Collection {
        $existingIds = $collection->items()
            ->where('collectable_type', $modelClass)
            ->pluck('collectable_id')
            ->toArray();

        $query = $modelClass::query();

        // Apply published/approved filters based on model
        if (method_exists($modelClass, 'scopePublished')) {
            $query->published();
        } elseif (method_exists($modelClass, 'scopeApproved')) {
            $query->approved();
        }

        // Exclude already added items
        if (!empty($existingIds)) {
            $query->whereNotIn('id', $existingIds);
        }

        // Apply smart filtering based on collection attributes
        $this->applySmartFiltering($query, $collection, $modelClass);

        return $query->with(['user:id,name,avatar_path', 'tags'])
            ->orderBy('average_rating', 'desc')
            ->orderBy('downloads_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get mixed content suggestions across all types
     */
    protected function getMixedContentSuggestions(
        EnhancedCollection $collection,
        int $limit
    ): Collection {
        $suggestions = collect();
        $perType = max(2, intval($limit / count($this->contentTypeMap)));

        foreach ($this->contentTypeMap as $type => $modelClass) {
            $typeSuggestions = $this->getSuggestionsByType($collection, $modelClass, $perType);
            
            // Add content type identifier for frontend
            $typeSuggestions->each(function ($item) use ($type) {
                $item->content_type = $type;
            });

            $suggestions = $suggestions->concat($typeSuggestions);
        }

        return $suggestions->shuffle()->take($limit);
    }

    /**
     * Apply smart filtering based on collection characteristics
     */
    protected function applySmartFiltering($query, EnhancedCollection $collection, string $modelClass): void
    {
        // Filter by genre if available
        if ($collection->genre && $this->modelHasColumn($modelClass, 'genre')) {
            $query->where('genre', $collection->genre);
        }

        // Filter by difficulty level if available
        if ($collection->difficulty_level && $this->modelHasColumn($modelClass, 'difficulty_level')) {
            $query->where('difficulty_level', $collection->difficulty_level);
        }

        // Filter by category if available
        if ($this->modelHasColumn($modelClass, 'category')) {
            // Try to match category with collection type
            $categoryMap = [
                'genre_cookbook' => 'production',
                'technique_masterclass' => 'sound_design',
                'artist_series' => 'template',
                'quick_start_pack' => 'production',
            ];

            if (isset($categoryMap[$collection->collection_type])) {
                $query->where('category', $categoryMap[$collection->collection_type]);
            }
        }

        // Filter by tags if the collection has similar content
        $this->applyTagBasedFiltering($query, $collection);
    }

    /**
     * Apply tag-based filtering for similar content
     */
    protected function applyTagBasedFiltering($query, EnhancedCollection $collection): void
    {
        $collectionTags = $collection->tags->pluck('id')->toArray();
        
        if (!empty($collectionTags)) {
            $query->whereHas('tags', function ($tagQuery) use ($collectionTags) {
                $tagQuery->whereIn('tags.id', $collectionTags);
            });
        }
    }

    /**
     * Get related collections for a piece of content
     */
    public function getRelatedCollections(Model $content, int $limit = 5): Collection
    {
        $contentType = get_class($content);
        
        $cacheKey = "related_collections:{$contentType}:{$content->id}:{$limit}";
        
        return Cache::remember($cacheKey, 1800, function () use ($content, $contentType, $limit) {
            // Find collections that contain similar content
            $query = EnhancedCollection::query()
                ->published()
                ->where('id', '!=', $content->id ?? 0);

            // Content is in these collections
            $directCollections = EnhancedCollection::whereHas('items', function ($q) use ($contentType, $content) {
                $q->where('collectable_type', $contentType)
                  ->where('collectable_id', $content->id);
            })->published()->limit($limit)->get();

            if ($directCollections->count() >= $limit) {
                return $directCollections;
            }

            // Find collections with similar content
            $similarCollections = $this->findSimilarCollections($content, $limit - $directCollections->count());

            return $directCollections->concat($similarCollections)->unique('id');
        });
    }

    /**
     * Find collections with similar content characteristics
     */
    protected function findSimilarCollections(Model $content, int $limit): Collection
    {
        $query = EnhancedCollection::query()->published();

        // Filter by genre if available
        if (isset($content->genre)) {
            $query->where('genre', $content->genre);
        }

        // Filter by difficulty if available
        if (isset($content->difficulty_level)) {
            $query->where('difficulty_level', $content->difficulty_level);
        }

        // Filter by tags if available
        if (method_exists($content, 'tags')) {
            $tagIds = $content->tags->pluck('id')->toArray();
            if (!empty($tagIds)) {
                $query->whereHas('tags', function ($tagQuery) use ($tagIds) {
                    $tagQuery->whereIn('tags.id', $tagIds);
                });
            }
        }

        return $query->with(['user:id,name,avatar_path'])
            ->orderBy('average_rating', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get content analytics for collection building insights
     */
    public function getContentAnalytics(User $user): array
    {
        $cacheKey = "content_analytics:{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return [
                'user_preferences' => $this->analyzeUserPreferences($user),
                'trending_content' => $this->getTrendingContentByType(),
                'popular_combinations' => $this->getPopularContentCombinations(),
                'genre_insights' => $this->getGenreInsights(),
            ];
        });
    }

    /**
     * Analyze user's content preferences
     */
    protected function analyzeUserPreferences(User $user): array
    {
        $userContent = collect();

        // Analyze user's created content
        foreach ($this->contentTypeMap as $type => $modelClass) {
            $content = $modelClass::where('user_id', $user->id)->get();
            $userContent = $userContent->concat($content->map(function ($item) use ($type) {
                $item->content_type = $type;
                return $item;
            }));
        }

        // Analyze user's collections
        $userCollections = $user->collections ?? collect();

        return [
            'preferred_genres' => $this->extractPreferredGenres($userContent, $userCollections),
            'preferred_difficulty' => $this->extractPreferredDifficulty($userContent, $userCollections),
            'content_type_distribution' => $userContent->groupBy('content_type')->map->count(),
            'most_used_tags' => $this->extractMostUsedTags($userContent),
        ];
    }

    /**
     * Get trending content across all types
     */
    protected function getTrendingContentByType(): array
    {
        $trending = [];

        foreach ($this->contentTypeMap as $type => $modelClass) {
            $query = $modelClass::query();

            if (method_exists($modelClass, 'scopePublished')) {
                $query->published();
            }

            $trendingContent = $query->orderByRaw('(downloads_count + views_count * 0.1) / DATEDIFF(NOW(), created_at) DESC')
                ->limit(5)
                ->get();

            $trending[$type] = $trendingContent;
        }

        return $trending;
    }

    /**
     * Get popular content combinations in collections
     */
    protected function getPopularContentCombinations(): array
    {
        // Analyze what content types are commonly combined in successful collections
        $combinations = EnhancedCollection::query()
            ->published()
            ->where('average_rating', '>=', 4.0)
            ->with(['items' => function ($query) {
                $query->select('collection_id', 'collectable_type');
            }])
            ->get()
            ->map(function ($collection) {
                $types = $collection->items->groupBy('collectable_type')->keys()->sort()->values();
                return $types->implode(',');
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10);

        return $combinations->toArray();
    }

    /**
     * Get genre-based insights
     */
    protected function getGenreInsights(): array
    {
        return [
            'popular_genres' => $this->getPopularGenres(),
            'genre_difficulty_map' => $this->getGenreDifficultyMapping(),
            'genre_content_types' => $this->getGenreContentTypeMapping(),
        ];
    }

    /**
     * Check if model has specific column
     */
    protected function modelHasColumn(string $modelClass, string $column): bool
    {
        $fillable = (new $modelClass)->getFillable();
        return in_array($column, $fillable);
    }

    /**
     * Extract preferred genres from user content
     */
    protected function extractPreferredGenres($userContent, $userCollections): array
    {
        $genres = collect();

        // From user's content
        $contentGenres = $userContent->pluck('genre')->filter();
        $genres = $genres->concat($contentGenres);

        // From user's collections
        $collectionGenres = $userCollections->pluck('genre')->filter();
        $genres = $genres->concat($collectionGenres);

        return $genres->countBy()->sortDesc()->take(5)->toArray();
    }

    /**
     * Extract preferred difficulty from user content
     */
    protected function extractPreferredDifficulty($userContent, $userCollections): ?string
    {
        $difficulties = collect();

        // From user's content
        $contentDifficulties = $userContent->pluck('difficulty_level')->filter();
        $difficulties = $difficulties->concat($contentDifficulties);

        // From user's collections
        $collectionDifficulties = $userCollections->pluck('difficulty_level')->filter();
        $difficulties = $difficulties->concat($collectionDifficulties);

        $preferred = $difficulties->countBy()->sortDesc()->keys()->first();
        
        return $preferred;
    }

    /**
     * Extract most used tags from user content
     */
    protected function extractMostUsedTags($userContent): array
    {
        $allTags = collect();

        foreach ($userContent as $content) {
            if (method_exists($content, 'tags') && $content->tags) {
                $allTags = $allTags->concat($content->tags->pluck('name'));
            }
        }

        return $allTags->countBy()->sortDesc()->take(10)->toArray();
    }

    /**
     * Get popular genres across all content
     */
    protected function getPopularGenres(): array
    {
        $genres = collect();

        foreach ($this->contentTypeMap as $modelClass) {
            if ($this->modelHasColumn($modelClass, 'genre')) {
                $contentGenres = $modelClass::whereNotNull('genre')
                    ->pluck('genre');
                $genres = $genres->concat($contentGenres);
            }
        }

        $collectionGenres = EnhancedCollection::whereNotNull('genre')
            ->pluck('genre');
        $genres = $genres->concat($collectionGenres);

        return $genres->countBy()->sortDesc()->take(10)->toArray();
    }

    /**
     * Get genre to difficulty level mapping
     */
    protected function getGenreDifficultyMapping(): array
    {
        return EnhancedCollection::whereNotNull('genre')
            ->whereNotNull('difficulty_level')
            ->selectRaw('genre, difficulty_level, COUNT(*) as count')
            ->groupBy('genre', 'difficulty_level')
            ->get()
            ->groupBy('genre')
            ->map(function ($items) {
                return $items->sortByDesc('count')->first()->difficulty_level;
            })
            ->toArray();
    }

    /**
     * Get genre to content type mapping
     */
    protected function getGenreContentTypeMapping(): array
    {
        // This would analyze which content types are most popular for each genre
        // Simplified implementation for now
        return [
            'house' => ['rack', 'preset', 'session'],
            'techno' => ['rack', 'session', 'preset'],
            'ambient' => ['preset', 'session', 'rack'],
            'trap' => ['preset', 'rack', 'session'],
        ];
    }

    /**
     * Create smart collection suggestions based on user's existing content
     */
    public function suggestCollectionsForUser(User $user, int $limit = 5): Collection
    {
        $userContent = $this->getUserContent($user);
        $suggestions = collect();

        if ($userContent->isEmpty()) {
            // Return popular collections for new users
            return EnhancedCollection::published()
                ->featured()
                ->with(['user:id,name,avatar_path'])
                ->limit($limit)
                ->get();
        }

        // Group user content by characteristics
        $genreGroups = $userContent->groupBy('genre')->filter();
        $typeGroups = $userContent->groupBy('content_type');

        foreach ($genreGroups as $genre => $content) {
            if ($content->count() >= 3) { // Enough content for a collection
                $suggestions->push([
                    'type' => 'genre_cookbook',
                    'title' => ucfirst($genre) . ' Production Cookbook',
                    'description' => "A comprehensive guide to {$genre} production using your existing content",
                    'suggested_items' => $content->take(10),
                    'estimated_items' => $content->count(),
                    'genre' => $genre,
                ]);
            }
        }

        foreach ($typeGroups as $type => $content) {
            if ($content->count() >= 5) {
                $typeName = ucfirst(str_replace('_', ' ', $type));
                $suggestions->push([
                    'type' => 'preset_library',
                    'title' => "My {$typeName} Library",
                    'description' => "Curated collection of your best {$typeName}s",
                    'suggested_items' => $content->take(15),
                    'estimated_items' => $content->count(),
                    'content_type_focus' => $type,
                ]);
            }
        }

        return $suggestions->take($limit);
    }

    /**
     * Get all content created by user across different types
     */
    protected function getUserContent(User $user): Collection
    {
        $userContent = collect();

        foreach ($this->contentTypeMap as $type => $modelClass) {
            $content = $modelClass::where('user_id', $user->id)->get();
            $content->each(function ($item) use ($type) {
                $item->content_type = $type;
            });
            $userContent = $userContent->concat($content);
        }

        return $userContent;
    }

    /**
     * Validate content compatibility for collection
     */
    public function validateContentCompatibility(
        EnhancedCollection $collection,
        string $contentType,
        int $contentId
    ): array {
        $validation = [
            'compatible' => true,
            'warnings' => [],
            'suggestions' => [],
        ];

        if (!isset($this->contentTypeMap[$contentType])) {
            $validation['compatible'] = false;
            $validation['warnings'][] = 'Invalid content type';
            return $validation;
        }

        $modelClass = $this->contentTypeMap[$contentType];
        $content = $modelClass::find($contentId);

        if (!$content) {
            $validation['compatible'] = false;
            $validation['warnings'][] = 'Content not found';
            return $validation;
        }

        // Check if already in collection
        if ($collection->items()->where('collectable_type', $modelClass)->where('collectable_id', $contentId)->exists()) {
            $validation['compatible'] = false;
            $validation['warnings'][] = 'Content already in collection';
            return $validation;
        }

        // Check genre compatibility
        if ($collection->genre && isset($content->genre) && $content->genre !== $collection->genre) {
            $validation['warnings'][] = "Genre mismatch: collection is {$collection->genre}, content is {$content->genre}";
            $validation['suggestions'][] = 'Consider creating a separate collection for this genre';
        }

        // Check difficulty compatibility
        if ($collection->difficulty_level && isset($content->difficulty_level)) {
            $difficultyOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
            $collectionLevel = $difficultyOrder[$collection->difficulty_level] ?? 2;
            $contentLevel = $difficultyOrder[$content->difficulty_level] ?? 2;

            if (abs($collectionLevel - $contentLevel) > 1) {
                $validation['warnings'][] = 'Significant difficulty level difference';
                $validation['suggestions'][] = 'Consider if this content fits your target audience';
            }
        }

        return $validation;
    }
}