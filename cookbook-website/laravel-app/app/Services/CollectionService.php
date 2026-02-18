<?php

namespace App\Services;

use App\Models\EnhancedCollection;
use App\Models\CollectionItem;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service for managing collections and their content
 */
class CollectionService
{
    public function __construct(
        protected MarkdownService $markdownService,
        protected CollectionAnalyticsService $analyticsService
    ) {}

    /**
     * Create a new collection
     */
    public function createCollection(User $user, array $data): EnhancedCollection
    {
        return DB::transaction(function () use ($user, $data) {
            $collection = EnhancedCollection::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'slug' => $this->generateUniqueSlug($data['title']),
                ...$data,
            ]);

            // Create analytics tracking
            $this->analyticsService->initializeCollectionTracking($collection);

            // Log activity
            $this->logActivity($user, 'collection_created', $collection);

            return $collection;
        });
    }

    /**
     * Update collection
     */
    public function updateCollection(EnhancedCollection $collection, array $data): EnhancedCollection
    {
        return DB::transaction(function () use ($collection, $data) {
            // Handle slug update if title changed
            if (isset($data['title']) && $data['title'] !== $collection->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $collection->id);
            }

            // Handle how-to article updates
            if (isset($data['how_to_article'])) {
                $data['how_to_updated_at'] = now();
            }

            $collection->update($data);
            
            // Update counts if items were modified
            if (isset($data['items'])) {
                $collection->updateCounts();
            }

            // Clear related caches
            $this->clearCollectionCaches($collection);

            return $collection->fresh();
        });
    }

    /**
     * Add item to collection
     */
    public function addItemToCollection(
        EnhancedCollection $collection,
        string $itemType,
        int $itemId,
        array $itemData = []
    ): CollectionItem {
        return DB::transaction(function () use ($collection, $itemType, $itemId, $itemData) {
            // Get next position
            $position = $collection->items()->max('position') + 1;

            $item = $collection->items()->create([
                'collectable_type' => $itemType,
                'collectable_id' => $itemId,
                'position' => $position,
                ...$itemData,
            ]);

            // Update collection counts
            $collection->updateCounts();

            // Clear caches
            $this->clearCollectionCaches($collection);

            return $item;
        });
    }

    /**
     * Remove item from collection
     */
    public function removeItemFromCollection(EnhancedCollection $collection, int $itemId): bool
    {
        return DB::transaction(function () use ($collection, $itemId) {
            $item = $collection->items()->findOrFail($itemId);
            $removedPosition = $item->position;
            
            $item->delete();

            // Reorder remaining items
            $collection->items()
                ->where('position', '>', $removedPosition)
                ->decrement('position');

            // Update collection counts
            $collection->updateCounts();

            // Clear caches
            $this->clearCollectionCaches($collection);

            return true;
        });
    }

    /**
     * Reorder collection items
     */
    public function reorderItems(EnhancedCollection $collection, array $itemOrder): void
    {
        DB::transaction(function () use ($collection, $itemOrder) {
            foreach ($itemOrder as $position => $itemId) {
                $collection->items()
                    ->where('id', $itemId)
                    ->update(['position' => $position + 1]);
            }

            // Clear caches
            $this->clearCollectionCaches($collection);
        });
    }

    /**
     * Organize items into sections
     */
    public function organizeIntoSections(EnhancedCollection $collection, array $sectionData): void
    {
        DB::transaction(function () use ($collection, $sectionData) {
            foreach ($sectionData as $section => $itemIds) {
                foreach ($itemIds as $position => $itemId) {
                    $collection->items()
                        ->where('id', $itemId)
                        ->update([
                            'section' => $section,
                            'position' => $position + 1,
                        ]);
                }
            }

            // Clear caches
            $this->clearCollectionCaches($collection);
        });
    }

    /**
     * Duplicate collection
     */
    public function duplicateCollection(EnhancedCollection $collection, User $user, array $overrides = []): EnhancedCollection
    {
        return DB::transaction(function () use ($collection, $user, $overrides) {
            $newCollection = $collection->replicate([
                'uuid',
                'user_id',
                'slug',
                'downloads_count',
                'views_count',
                'saves_count',
                'comments_count',
                'likes_count',
                'ratings_count',
                'average_rating',
                'completions_count',
                'published_at',
            ]);

            $newCollection->fill([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'title' => ($overrides['title'] ?? $collection->title) . ' (Copy)',
                'slug' => $this->generateUniqueSlug(($overrides['title'] ?? $collection->title) . ' Copy'),
                'is_public' => false,
                'status' => 'draft',
                ...$overrides,
            ]);

            $newCollection->save();

            // Duplicate items
            foreach ($collection->items as $item) {
                $newItem = $item->replicate([
                    'collection_id',
                    'views_count',
                    'completions_count',
                    'likes_count',
                ]);
                
                $newItem->collection_id = $newCollection->id;
                $newItem->save();
            }

            // Update counts
            $newCollection->updateCounts();

            // Initialize analytics
            $this->analyticsService->initializeCollectionTracking($newCollection);

            return $newCollection;
        });
    }

    /**
     * Get collection with related data for display
     */
    public function getCollectionForDisplay(string $slug, ?User $user = null): ?EnhancedCollection
    {
        $cacheKey = "collection:display:{$slug}:" . ($user?->id ?? 'guest');
        
        return Cache::remember($cacheKey, 3600, function () use ($slug, $user) {
            $collection = EnhancedCollection::where('slug', $slug)
                ->published()
                ->with([
                    'user:id,name,avatar_path',
                    'items.collectable',
                    'tags',
                ])
                ->first();

            if (!$collection) {
                return null;
            }

            // Record view
            $this->recordView($collection, $user);

            return $collection;
        });
    }

    /**
     * Get collections for discovery page
     */
    public function getDiscoveryCollections(array $filters = [], int $limit = 20): Collection
    {
        $cacheKey = 'collections:discovery:' . md5(serialize($filters)) . ":{$limit}";
        
        return Cache::remember($cacheKey, 1800, function () use ($filters, $limit) {
            $query = EnhancedCollection::query()
                ->published()
                ->with(['user:id,name,avatar_path', 'tags']);

            // Apply filters
            if (isset($filters['collection_type'])) {
                $query->where('collection_type', $filters['collection_type']);
            }

            if (isset($filters['difficulty_level'])) {
                $query->where('difficulty_level', $filters['difficulty_level']);
            }

            if (isset($filters['genre'])) {
                $query->where('genre', $filters['genre']);
            }

            if (isset($filters['is_free'])) {
                $query->where('is_free', $filters['is_free']);
            }

            if (isset($filters['has_certificate'])) {
                $query->where('has_certificate', $filters['has_certificate']);
            }

            if (isset($filters['search'])) {
                $query->whereFullText(['title', 'description'], $filters['search']);
            }

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'featured';
            match ($sortBy) {
                'newest' => $query->orderBy('published_at', 'desc'),
                'popular' => $query->orderBy('downloads_count', 'desc'),
                'rating' => $query->orderBy('average_rating', 'desc'),
                'trending' => $query->orderByRaw('(downloads_count + views_count * 0.1) / DATEDIFF(NOW(), created_at) DESC'),
                default => $query->orderBy('is_featured', 'desc')->orderBy('average_rating', 'desc'),
            };

            return $query->limit($limit)->get();
        });
    }

    /**
     * Get featured collections
     */
    public function getFeaturedCollections(int $limit = 8): Collection
    {
        return Cache::remember("collections:featured:{$limit}", 3600, function () use ($limit) {
            return EnhancedCollection::query()
                ->published()
                ->featured()
                ->with(['user:id,name,avatar_path', 'tags'])
                ->orderBy('average_rating', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get collections by type
     */
    public function getCollectionsByType(string $type, int $limit = 12): Collection
    {
        return Cache::remember("collections:type:{$type}:{$limit}", 3600, function () use ($type, $limit) {
            return EnhancedCollection::query()
                ->published()
                ->byType($type)
                ->with(['user:id,name,avatar_path', 'tags'])
                ->orderBy('average_rating', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get user's collections
     */
    public function getUserCollections(User $user, array $filters = []): Collection
    {
        $query = $user->collections()
            ->with(['tags'])
            ->orderBy('updated_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['collection_type'])) {
            $query->where('collection_type', $filters['collection_type']);
        }

        return $query->get();
    }

    /**
     * Get user's saved collections
     */
    public function getUserSavedCollections(User $user, string $folder = null): Collection
    {
        $query = EnhancedCollection::query()
            ->whereHas('saves', function ($q) use ($user, $folder) {
                $q->where('user_id', $user->id);
                if ($folder) {
                    $q->where('folder', $folder);
                }
            })
            ->published()
            ->with(['user:id,name,avatar_path', 'tags']);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Save/unsave collection for user
     */
    public function toggleSaveCollection(EnhancedCollection $collection, User $user, array $saveData = []): bool
    {
        $existingSave = $collection->saves()->where('user_id', $user->id)->first();

        if ($existingSave) {
            $existingSave->delete();
            $collection->decrement('saves_count');
            return false; // Unsaved
        } else {
            $collection->saves()->create([
                'user_id' => $user->id,
                ...$saveData,
            ]);
            $collection->increment('saves_count');
            return true; // Saved
        }
    }

    /**
     * Start collection for user (create progress tracking)
     */
    public function startCollectionForUser(EnhancedCollection $collection, User $user): UserProgress
    {
        $progress = $collection->userProgress()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'status' => 'not_started',
            'total_items' => $collection->items_count,
            'started_at' => now(),
        ]);

        if ($progress->wasRecentlyCreated) {
            // Log activity
            $this->logActivity($user, 'collection_started', $collection);
        }

        $progress->start();
        
        return $progress;
    }

    /**
     * Record view for analytics
     */
    public function recordView(EnhancedCollection $collection, ?User $user = null): void
    {
        $collection->increment('views_count');
        
        $this->analyticsService->recordCollectionView($collection, [
            'user_id' => $user?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer'),
        ]);
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStatistics(EnhancedCollection $collection): array
    {
        return [
            'views' => [
                'total' => $collection->views_count,
                'unique_today' => $this->analyticsService->getUniqueViewsToday($collection),
                'trend' => $this->analyticsService->getViewsTrend($collection, 7),
            ],
            'engagement' => [
                'downloads' => $collection->downloads_count,
                'saves' => $collection->saves_count,
                'comments' => $collection->comments_count,
                'likes' => $collection->likes_count,
                'rating' => [
                    'average' => $collection->average_rating,
                    'count' => $collection->ratings_count,
                ],
            ],
            'progress' => [
                'enrollments' => $collection->userProgress()->count(),
                'completions' => $collection->completions_count,
                'completion_rate' => $this->calculateCompletionRate($collection),
                'average_progress' => $this->getAverageProgress($collection),
            ],
            'content' => [
                'items_count' => $collection->items_count,
                'sections' => count($collection->getSections()),
                'estimated_time' => $collection->estimated_completion_time,
            ],
        ];
    }

    /**
     * Publish collection
     */
    public function publishCollection(EnhancedCollection $collection): bool
    {
        if ($collection->status !== 'draft' || $collection->items_count === 0) {
            return false;
        }

        return DB::transaction(function () use ($collection) {
            $collection->update([
                'status' => 'review', // Admin approval required
                'published_at' => now(),
                'is_public' => true,
            ]);

            // Log activity
            $this->logActivity($collection->user, 'collection_published', $collection);

            // Clear caches
            $this->clearCollectionCaches($collection);

            return true;
        });
    }

    /**
     * Generate unique slug
     */
    protected function generateUniqueSlug(string $title, int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    protected function slugExists(string $slug, int $excludeId = null): bool
    {
        $query = EnhancedCollection::where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Calculate completion rate
     */
    protected function calculateCompletionRate(EnhancedCollection $collection): float
    {
        $totalEnrollments = $collection->userProgress()->count();
        
        if ($totalEnrollments === 0) {
            return 0;
        }

        $completions = $collection->userProgress()->completed()->count();
        
        return ($completions / $totalEnrollments) * 100;
    }

    /**
     * Get average progress percentage
     */
    protected function getAverageProgress(EnhancedCollection $collection): float
    {
        return $collection->userProgress()->avg('completion_percentage') ?? 0;
    }

    /**
     * Log user activity
     */
    protected function logActivity(User $user, string $action, EnhancedCollection $collection): void
    {
        $user->activities()->create([
            'action' => $action,
            'subject_type' => EnhancedCollection::class,
            'subject_id' => $collection->id,
            'metadata' => [
                'collection_title' => $collection->title,
                'collection_type' => $collection->collection_type,
            ],
        ]);
    }

    /**
     * Clear collection-related caches
     */
    protected function clearCollectionCaches(EnhancedCollection $collection): void
    {
        $tags = [
            "collection:{$collection->id}",
            "collection:slug:{$collection->slug}",
            "collections:user:{$collection->user_id}",
            "collections:discovery",
            "collections:featured",
            "collections:type:{$collection->collection_type}",
        ];

        foreach ($tags as $tag) {
            Cache::tags($tag)->flush();
        }
    }
}