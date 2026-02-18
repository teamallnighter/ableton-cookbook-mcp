<?php

namespace App\Services;

use App\Models\Rack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RackCacheService
{
    // Cache key prefixes
    const FEATURED_RACKS = 'featured_racks';
    const POPULAR_RACKS = 'popular_racks';
    const RECENT_RACKS = 'recent_racks';
    const RACK_CATEGORIES = 'rack_categories';
    const USER_STATS = 'user_stats';
    const RACK_STRUCTURE = 'rack_structure';
    const USER_INTERACTIONS = 'user_interactions';

    // Cache durations (in seconds)
    const SHORT_CACHE = 300;    // 5 minutes
    const MEDIUM_CACHE = 600;   // 10 minutes  
    const LONG_CACHE = 3600;    // 1 hour
    const DAILY_CACHE = 86400;  // 24 hours

    public static function getFeaturedRacks(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            self::FEATURED_RACKS . "_{$limit}",
            self::LONG_CACHE,
            function() use ($limit) {
                return Rack::select([
                    'id', 'uuid', 'title', 'slug', 'user_id', 'average_rating', 
                    'downloads_count', 'rack_type', 'category', 'created_at'
                ])
                ->with('user:id,name,profile_photo_path')
                ->where('status', 'approved')
                ->where('is_public', true)
                ->where('is_featured', true)
                ->orderByDesc('average_rating')
                ->limit($limit)
                ->get();
            }
        );
    }

    public static function getPopularRacks(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            self::POPULAR_RACKS . "_{$limit}",
            self::MEDIUM_CACHE,
            function() use ($limit) {
                return Rack::select([
                    'id', 'uuid', 'title', 'slug', 'user_id', 'downloads_count', 
                    'average_rating', 'rack_type', 'category', 'created_at'
                ])
                ->with('user:id,name,profile_photo_path')
                ->where('status', 'approved')
                ->where('is_public', true)
                ->orderByDesc('downloads_count')
                ->limit($limit)
                ->get();
            }
        );
    }

    public static function getRecentRacks(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(
            self::RECENT_RACKS . "_{$limit}",
            self::SHORT_CACHE,
            function() use ($limit) {
                return Rack::select([
                    'id', 'uuid', 'title', 'slug', 'user_id', 'created_at',
                    'rack_type', 'category', 'average_rating', 'downloads_count'
                ])
                ->with('user:id,name,profile_photo_path')
                ->where('status', 'approved')
                ->where('is_public', true)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
            }
        );
    }

    public static function getRackCategories(): array
    {
        return Cache::remember(
            self::RACK_CATEGORIES,
            self::LONG_CACHE,
            function() {
                return Rack::whereNotNull('category')
                    ->where('status', 'approved')
                    ->where('is_public', true)
                    ->distinct()
                    ->pluck('category')
                    ->filter()
                    ->sort()
                    ->values()
                    ->toArray();
            }
        );
    }

    public static function getUserStats(int $userId): array
    {
        return Cache::remember(
            self::USER_STATS . "_{$userId}",
            self::MEDIUM_CACHE,
            function() use ($userId) {
                $rackStats = DB::table('racks')
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->where('is_public', true)
                    ->selectRaw('
                        COUNT(*) as total_uploads,
                        COALESCE(SUM(downloads_count), 0) as total_downloads,
                        COALESCE(SUM(views_count), 0) as total_views,
                        COALESCE(AVG(average_rating), 0) as average_rating
                    ')
                    ->first();

                $favoritesCount = DB::table('rack_favorites')
                    ->where('user_id', $userId)
                    ->count();

                return [
                    'total_uploads' => $rackStats->total_uploads ?? 0,
                    'total_downloads' => $rackStats->total_downloads ?? 0,
                    'total_views' => $rackStats->total_views ?? 0,
                    'average_rating' => round($rackStats->average_rating ?? 0, 2),
                    'favorites_count' => $favoritesCount,
                ];
            }
        );
    }

    public static function getRackStructure(int $rackId): ?array
    {
        return Cache::remember(
            self::RACK_STRUCTURE . "_{$rackId}",
            self::LONG_CACHE,
            function() use ($rackId) {
                $rack = Rack::find($rackId);
                if (!$rack) {
                    return null;
                }

                return [
                    'chains' => $rack->chains,
                    'type' => $rack->rack_type,
                    'devices' => $rack->devices,
                    'macro_controls' => $rack->macro_controls
                ];
            }
        );
    }

    public static function getUserInteractions(int $rackId, int $userId): array
    {
        return Cache::remember(
            self::USER_INTERACTIONS . "_{$rackId}_{$userId}",
            self::SHORT_CACHE,
            function() use ($rackId, $userId) {
                $rating = DB::table('rack_ratings')
                    ->where('rack_id', $rackId)
                    ->where('user_id', $userId)
                    ->value('rating');

                $favorited = DB::table('rack_favorites')
                    ->where('rack_id', $rackId)
                    ->where('user_id', $userId)
                    ->exists();

                return [
                    'rating' => $rating ?: 0,
                    'favorited' => $favorited
                ];
            }
        );
    }

    // Cache invalidation methods
    public static function clearUserCaches(int $userId): void
    {
        Cache::forget(self::USER_STATS . "_{$userId}");
        
        // Clear user interaction caches for this user
        $userRacks = Rack::where('user_id', $userId)->pluck('id');
        foreach ($userRacks as $rackId) {
            Cache::forget(self::USER_INTERACTIONS . "_{$rackId}_{$userId}");
        }
    }

    public static function clearRackCaches(int $rackId): void
    {
        Cache::forget(self::RACK_STRUCTURE . "_{$rackId}");
        
        // Clear featured/popular caches as they might include this rack
        Cache::forget(self::FEATURED_RACKS . '_10');
        Cache::forget(self::POPULAR_RACKS . '_10');
        Cache::forget(self::RECENT_RACKS . '_10');
        Cache::forget(self::RACK_CATEGORIES);
    }

    public static function clearUserInteractionCache(int $rackId, int $userId): void
    {
        Cache::forget(self::USER_INTERACTIONS . "_{$rackId}_{$userId}");
    }

    public static function clearAllRackListCaches(): void
    {
        Cache::forget(self::FEATURED_RACKS . '_10');
        Cache::forget(self::POPULAR_RACKS . '_10');
        Cache::forget(self::RECENT_RACKS . '_10');
        Cache::forget(self::RACK_CATEGORIES);
    }

    public static function warmupCaches(): void
    {
        // Warm up commonly accessed caches
        self::getFeaturedRacks();
        self::getPopularRacks();
        self::getRecentRacks();
        self::getRackCategories();
    }
}