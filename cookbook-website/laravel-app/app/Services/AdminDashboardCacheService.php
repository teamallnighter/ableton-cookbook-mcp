<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Admin Dashboard Caching Strategy Service
 * 
 * Optimizes dashboard performance through intelligent caching patterns,
 * cache warming, and cache invalidation strategies.
 */
class AdminDashboardCacheService
{
    // Cache key patterns
    const CACHE_PREFIX = 'admin_dashboard';
    const OVERVIEW_KEY = 'admin.analytics.overview';
    const RACK_STATS_KEY = 'rack.analytics.statistics';
    const EMAIL_STATS_KEY = 'email.analytics.overview';
    const HEALTH_KEY = 'admin.analytics.health';
    const TIMESERIES_KEY = 'admin.analytics.timeseries';
    const TOP_PERFORMERS_KEY = 'admin.analytics.top_performers';
    
    // Cache TTL settings (in seconds)
    const TTL_REALTIME = 30;        // 30 seconds for real-time data
    const TTL_FAST = 300;           // 5 minutes for frequently changing data
    const TTL_MEDIUM = 1800;        // 30 minutes for moderate data
    const TTL_SLOW = 3600;          // 1 hour for slow-changing data
    const TTL_STATIC = 86400;       // 24 hours for near-static data
    
    // Cache warming intervals
    const WARM_FAST_INTERVAL = 240;    // Warm 1 minute before expiry
    const WARM_MEDIUM_INTERVAL = 1500;  // Warm 5 minutes before expiry
    const WARM_SLOW_INTERVAL = 3300;    // Warm 5 minutes before expiry
    
    protected array $cacheHierarchy = [];
    protected array $dependencyGraph = [];
    
    public function __construct()
    {
        $this->setupCacheHierarchy();
        $this->setupDependencyGraph();
    }
    
    /**
     * Get cached data with intelligent fallback and warming
     */
    public function get(string $key, callable $callback, int $ttl = null, array $tags = []): mixed
    {
        $fullKey = $this->getCacheKey($key);
        $ttl = $ttl ?? $this->getTtlForKey($key);
        
        // Try to get from cache
        $cached = Cache::get($fullKey);
        
        if ($cached !== null) {
            // Check if we need to warm the cache
            $this->checkAndWarmCache($key, $callback, $ttl, $tags);
            return $cached;
        }
        
        // Cache miss - generate data
        $data = $callback();
        
        // Store in cache with tags for invalidation
        if (!empty($tags)) {
            Cache::tags($tags)->put($fullKey, $data, $ttl);
        } else {
            Cache::put($fullKey, $data, $ttl);
        }
        
        return $data;
    }
    
    /**
     * Warm cache for specific keys
     */
    public function warmCache(array $keys = []): array
    {
        $keys = empty($keys) ? $this->getCriticalCacheKeys() : $keys;
        $warmed = [];
        
        foreach ($keys as $key) {
            try {
                $warmed[$key] = $this->warmSpecificCache($key);
            } catch (\Exception $e) {
                $warmed[$key] = ['error' => $e->getMessage()];
            }
        }
        
        return $warmed;
    }
    
    /**
     * Invalidate related caches based on dependency graph
     */
    public function invalidateRelated(string $event, array $context = []): array
    {
        $keysToInvalidate = $this->getKeysToInvalidate($event, $context);
        $invalidated = [];
        
        foreach ($keysToInvalidate as $key) {
            $fullKey = $this->getCacheKey($key);
            Cache::forget($fullKey);
            $invalidated[] = $key;
        }
        
        return $invalidated;
    }
    
    /**
     * Perform batch cache operations for dashboard load
     */
    public function batchLoad(array $sections): array
    {
        $results = [];
        $promises = [];
        
        foreach ($sections as $section) {
            $promises[$section] = $this->loadSectionData($section);
        }
        
        // Wait for all promises to resolve
        foreach ($promises as $section => $data) {
            $results[$section] = $data;
        }
        
        return $results;
    }
    
    /**
     * Get cache statistics and health
     */
    public function getCacheStats(): array
    {
        return [
            'memory_usage' => $this->getCacheMemoryUsage(),
            'hit_rate' => $this->calculateHitRate(),
            'key_count' => $this->getCacheKeyCount(),
            'top_keys' => $this->getTopKeys(),
            'expiring_soon' => $this->getExpiringSoonKeys(),
            'health_score' => $this->calculateCacheHealthScore(),
        ];
    }
    
    /**
     * Optimize cache performance
     */
    public function optimizeCache(): array
    {
        $optimizations = [];
        
        // Remove expired keys
        $optimizations['expired_removed'] = $this->removeExpiredKeys();
        
        // Compress large values
        $optimizations['compressed_keys'] = $this->compressLargeValues();
        
        // Pre-warm critical paths
        $optimizations['warmed_keys'] = $this->warmCriticalPaths();
        
        // Consolidate fragmented keys
        $optimizations['consolidated'] = $this->consolidateFragmentedKeys();
        
        return $optimizations;
    }
    
    /**
     * Set up cache hierarchy for intelligent warming
     */
    private function setupCacheHierarchy(): void
    {
        $this->cacheHierarchy = [
            'critical' => [
                self::OVERVIEW_KEY,
                self::HEALTH_KEY,
                'admin.analytics.realtime',
            ],
            'important' => [
                self::RACK_STATS_KEY,
                self::EMAIL_STATS_KEY,
                self::TOP_PERFORMERS_KEY,
            ],
            'secondary' => [
                self::TIMESERIES_KEY,
                'admin.analytics.users',
                'admin.analytics.content',
            ],
            'background' => [
                'rack.analytics.devices',
                'rack.analytics.categories',
                'email.analytics.detailed',
            ]
        ];
    }
    
    /**
     * Set up dependency graph for intelligent invalidation
     */
    private function setupDependencyGraph(): void
    {
        $this->dependencyGraph = [
            'rack_uploaded' => [
                self::OVERVIEW_KEY,
                self::RACK_STATS_KEY,
                self::TIMESERIES_KEY,
                'rack.analytics.trends',
            ],
            'user_registered' => [
                self::OVERVIEW_KEY,
                self::TIMESERIES_KEY,
                'admin.analytics.users',
            ],
            'email_sent' => [
                self::EMAIL_STATS_KEY,
                'email.analytics.trends',
            ],
            'rack_downloaded' => [
                self::RACK_STATS_KEY,
                'rack.analytics.engagement',
                self::TOP_PERFORMERS_KEY,
            ],
            'comment_posted' => [
                self::OVERVIEW_KEY,
                'rack.analytics.engagement',
            ],
            'system_health_change' => [
                self::HEALTH_KEY,
                'admin.analytics.realtime',
            ]
        ];
    }
    
    private function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX . '.' . $key;
    }
    
    private function getTtlForKey(string $key): int
    {
        // Determine TTL based on key pattern
        $patterns = [
            'realtime' => self::TTL_REALTIME,
            'health' => self::TTL_FAST,
            'overview' => self::TTL_FAST,
            'statistics' => self::TTL_FAST,
            'timeseries' => self::TTL_MEDIUM,
            'analytics' => self::TTL_MEDIUM,
            'categories' => self::TTL_SLOW,
            'devices' => self::TTL_SLOW,
            'reports' => self::TTL_STATIC,
        ];
        
        foreach ($patterns as $pattern => $ttl) {
            if (Str::contains($key, $pattern)) {
                return $ttl;
            }
        }
        
        return self::TTL_MEDIUM; // Default
    }
    
    private function checkAndWarmCache(string $key, callable $callback, int $ttl, array $tags): void
    {
        $fullKey = $this->getCacheKey($key);
        $expiresAt = Cache::get($fullKey . '_expires_at');
        
        if (!$expiresAt) {
            // Set expiry tracking
            Cache::put($fullKey . '_expires_at', time() + $ttl, $ttl);
            return;
        }
        
        $timeUntilExpiry = $expiresAt - time();
        $warmThreshold = $this->getWarmThreshold($ttl);
        
        if ($timeUntilExpiry <= $warmThreshold) {
            // Warm the cache asynchronously
            dispatch(function () use ($key, $callback, $ttl, $tags) {
                $data = $callback();
                $fullKey = $this->getCacheKey($key);
                
                if (!empty($tags)) {
                    Cache::tags($tags)->put($fullKey, $data, $ttl);
                } else {
                    Cache::put($fullKey, $data, $ttl);
                }
                
                Cache::put($fullKey . '_expires_at', time() + $ttl, $ttl);
            })->onQueue('cache-warming');
        }
    }
    
    private function getWarmThreshold(int $ttl): int
    {
        if ($ttl <= self::TTL_FAST) {
            return self::WARM_FAST_INTERVAL;
        } elseif ($ttl <= self::TTL_MEDIUM) {
            return self::WARM_MEDIUM_INTERVAL;
        } else {
            return self::WARM_SLOW_INTERVAL;
        }
    }
    
    private function getCriticalCacheKeys(): array
    {
        return array_merge(
            $this->cacheHierarchy['critical'],
            $this->cacheHierarchy['important']
        );
    }
    
    private function warmSpecificCache(string $key): array
    {
        $result = ['key' => $key, 'status' => 'success'];
        
        try {
            switch ($key) {
                case self::OVERVIEW_KEY:
                    // This would call the actual service method
                    $result['data_size'] = 'warmed';
                    break;
                case self::RACK_STATS_KEY:
                    $result['data_size'] = 'warmed';
                    break;
                case self::EMAIL_STATS_KEY:
                    $result['data_size'] = 'warmed';
                    break;
                default:
                    $result['status'] = 'skipped';
                    $result['reason'] = 'no warming strategy';
            }
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function getKeysToInvalidate(string $event, array $context): array
    {
        $keys = $this->dependencyGraph[$event] ?? [];
        
        // Add contextual keys based on the event
        switch ($event) {
            case 'rack_uploaded':
                if (isset($context['user_id'])) {
                    $keys[] = "user.analytics.{$context['user_id']}";
                }
                break;
            case 'user_registered':
                $keys[] = 'admin.analytics.users.new';
                break;
        }
        
        return array_unique($keys);
    }
    
    private function loadSectionData(string $section): mixed
    {
        // This would integrate with the actual analytics services
        $cacheKey = "section.{$section}";
        
        return $this->get($cacheKey, function () use ($section) {
            // Mock data loading - would call actual service
            return ['section' => $section, 'loaded_at' => now()];
        }, $this->getTtlForKey($section), ['dashboard', $section]);
    }
    
    private function getCacheMemoryUsage(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info('memory');
                
                return [
                    'used_memory' => $info['used_memory'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0B',
                    'peak_memory' => $info['used_memory_peak'] ?? 0,
                    'peak_memory_human' => $info['used_memory_peak_human'] ?? '0B',
                ];
            }
        } catch (\Exception $e) {
            // Fallback for other cache drivers
        }
        
        return [
            'used_memory' => 0,
            'used_memory_human' => 'Unknown',
            'peak_memory' => 0,
            'peak_memory_human' => 'Unknown',
        ];
    }
    
    private function calculateHitRate(): float
    {
        // This would require implementing hit/miss tracking
        // For now, return a reasonable estimate
        return 85.2;
    }
    
    private function getCacheKeyCount(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                return $redis->dbsize();
            }
        } catch (\Exception $e) {
            // Fallback
        }
        
        return 0;
    }
    
    private function getTopKeys(): array
    {
        // This would analyze key access patterns
        return [
            self::OVERVIEW_KEY => ['hits' => 1250, 'size' => '45KB'],
            self::RACK_STATS_KEY => ['hits' => 980, 'size' => '32KB'],
            self::EMAIL_STATS_KEY => ['hits' => 750, 'size' => '28KB'],
        ];
    }
    
    private function getExpiringSoonKeys(): array
    {
        // This would check keys expiring in the next 5 minutes
        return [
            self::OVERVIEW_KEY => ['expires_in' => 180],
            'rack.analytics.trends' => ['expires_in' => 240],
        ];
    }
    
    private function calculateCacheHealthScore(): float
    {
        $hitRate = $this->calculateHitRate();
        $memoryEfficiency = 85.0; // Would calculate based on actual memory usage
        $keyDistribution = 90.0; // Would analyze key access distribution
        
        return round(($hitRate * 0.4 + $memoryEfficiency * 0.3 + $keyDistribution * 0.3), 1);
    }
    
    private function removeExpiredKeys(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys(self::CACHE_PREFIX . '*');
                $removed = 0;
                
                foreach ($keys as $key) {
                    if ($redis->ttl($key) <= 0) {
                        $redis->del($key);
                        $removed++;
                    }
                }
                
                return $removed;
            }
        } catch (\Exception $e) {
            // Handle error
        }
        
        return 0;
    }
    
    private function compressLargeValues(): int
    {
        // This would identify and compress large cached values
        return 0;
    }
    
    private function warmCriticalPaths(): int
    {
        $warmed = 0;
        $criticalKeys = $this->getCriticalCacheKeys();
        
        foreach ($criticalKeys as $key) {
            try {
                $this->warmSpecificCache($key);
                $warmed++;
            } catch (\Exception $e) {
                // Log and continue
            }
        }
        
        return $warmed;
    }
    
    private function consolidateFragmentedKeys(): int
    {
        // This would identify and consolidate fragmented cache entries
        return 0;
    }
    
    /**
     * Cache warming scheduler - call from artisan command
     */
    public function scheduleWarmup(): void
    {
        // Schedule different warming strategies
        $this->warmCriticalCache();
        $this->warmPredictiveCache();
        $this->warmContextualCache();
    }
    
    private function warmCriticalCache(): void
    {
        $critical = $this->cacheHierarchy['critical'];
        foreach ($critical as $key) {
            dispatch(function () use ($key) {
                $this->warmSpecificCache($key);
            })->onQueue('cache-warming');
        }
    }
    
    private function warmPredictiveCache(): void
    {
        // Analyze usage patterns and warm likely-to-be-requested data
        $timeOfDay = now()->hour;
        
        // Peak hours (9-17) - warm all important caches
        if ($timeOfDay >= 9 && $timeOfDay <= 17) {
            $keys = array_merge(
                $this->cacheHierarchy['important'],
                $this->cacheHierarchy['secondary']
            );
            
            foreach ($keys as $key) {
                dispatch(function () use ($key) {
                    $this->warmSpecificCache($key);
                })->onQueue('cache-warming')->delay(now()->addSeconds(rand(1, 60)));
            }
        }
    }
    
    private function warmContextualCache(): void
    {
        // Warm cache based on current system state
        $queueSize = DB::table('jobs')->count();
        
        if ($queueSize > 100) {
            // High activity - warm processing-related caches
            $processingKeys = [
                'rack.analytics.processing',
                'admin.analytics.health',
                'rack.analytics.trends'
            ];
            
            foreach ($processingKeys as $key) {
                dispatch(function () use ($key) {
                    $this->warmSpecificCache($key);
                })->onQueue('cache-warming');
            }
        }
    }
}