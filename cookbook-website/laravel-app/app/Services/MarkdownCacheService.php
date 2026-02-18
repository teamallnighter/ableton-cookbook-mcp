<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Markdown Cache Service
 * High-performance caching layer for markdown processing with intelligent invalidation
 */
class MarkdownCacheService
{
    private const CACHE_PREFIX = 'markdown:';
    private const DEFAULT_TTL = 3600; // 1 hour
    private const LONG_TTL = 86400; // 24 hours
    private const SHORT_TTL = 300; // 5 minutes

    private array $config;

    public function __construct()
    {
        $this->config = [
            'enabled' => config('cache.markdown.enabled', true),
            'ttl' => config('cache.markdown.ttl', self::DEFAULT_TTL),
            'max_size' => config('cache.markdown.max_size', 100000), // 100KB
            'compression' => config('cache.markdown.compression', true),
            'tags' => config('cache.default') === 'redis' || config('cache.default') === 'memcached',
        ];
    }

    /**
     * Get cached markdown HTML or generate if not exists
     */
    public function getOrGenerate(string $markdown, callable $generator, array $options = []): string
    {
        if (!$this->config['enabled']) {
            return $generator($markdown);
        }

        $cacheKey = $this->generateCacheKey($markdown, $options);
        
        // Try to get from cache
        $cached = $this->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Generate and cache
        $html = $generator($markdown);
        $this->put($cacheKey, $html, $this->determineTTL($markdown, $options));

        return $html;
    }

    /**
     * Cache markdown HTML with metadata
     */
    public function cacheWithMetadata(
        string $markdown, 
        string $html, 
        array $metadata = [], 
        array $options = []
    ): void {
        if (!$this->config['enabled']) {
            return;
        }

        $cacheKey = $this->generateCacheKey($markdown, $options);
        $metadataKey = $cacheKey . ':meta';
        
        $ttl = $this->determineTTL($markdown, $options);
        
        // Cache HTML
        $this->put($cacheKey, $html, $ttl);
        
        // Cache metadata separately
        $this->put($metadataKey, $metadata, $ttl);
    }

    /**
     * Get cached HTML
     */
    public function get(string $cacheKey): ?string
    {
        try {
            if ($this->config['tags']) {
                return Cache::tags(['markdown'])->get($cacheKey);
            }
            
            $cached = Cache::get($cacheKey);
            
            // Handle compressed data
            if ($cached && $this->config['compression'] && is_array($cached) && isset($cached['compressed'])) {
                return gzuncompress($cached['data']);
            }
            
            return $cached;
        } catch (\Exception $e) {
            Log::warning('Markdown cache get failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store HTML in cache
     */
    public function put(string $cacheKey, string $html, int $ttl = null): void
    {
        try {
            $ttl = $ttl ?? $this->config['ttl'];
            
            // Skip caching if content is too large
            if (strlen($html) > $this->config['max_size']) {
                Log::info('Skipping cache for large content', ['size' => strlen($html), 'key' => $cacheKey]);
                return;
            }

            $data = $html;
            
            // Compress large content
            if ($this->config['compression'] && strlen($html) > 1000) {
                $compressed = gzcompress($html, 6);
                if ($compressed !== false && strlen($compressed) < strlen($html) * 0.8) {
                    $data = [
                        'compressed' => true,
                        'data' => $compressed,
                        'original_size' => strlen($html)
                    ];
                }
            }

            if ($this->config['tags']) {
                Cache::tags(['markdown'])->put($cacheKey, $data, $ttl);
            } else {
                Cache::put($cacheKey, $data, $ttl);
            }
        } catch (\Exception $e) {
            Log::warning('Markdown cache put failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get cached metadata
     */
    public function getMetadata(string $markdown, array $options = []): ?array
    {
        if (!$this->config['enabled']) {
            return null;
        }

        $cacheKey = $this->generateCacheKey($markdown, $options) . ':meta';
        
        try {
            if ($this->config['tags']) {
                return Cache::tags(['markdown'])->get($cacheKey);
            }
            
            return Cache::get($cacheKey);
        } catch (\Exception $e) {
            Log::warning('Markdown metadata cache get failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Invalidate cache for specific content
     */
    public function invalidate(string $markdown, array $options = []): void
    {
        $cacheKey = $this->generateCacheKey($markdown, $options);
        $metadataKey = $cacheKey . ':meta';
        
        try {
            if ($this->config['tags']) {
                Cache::tags(['markdown'])->forget($cacheKey);
                Cache::tags(['markdown'])->forget($metadataKey);
            } else {
                Cache::forget($cacheKey);
                Cache::forget($metadataKey);
            }
        } catch (\Exception $e) {
            Log::warning('Markdown cache invalidation failed', ['key' => $cacheKey, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Clear all markdown cache
     */
    public function flush(): void
    {
        try {
            if ($this->config['tags']) {
                Cache::tags(['markdown'])->flush();
            } else {
                // Without tags, we need to clear by pattern (Redis only)
                if (config('cache.default') === 'redis') {
                    $keys = Cache::getRedis()->keys(self::CACHE_PREFIX . '*');
                    if (!empty($keys)) {
                        Cache::getRedis()->del($keys);
                    }
                } else {
                    Log::warning('Cannot flush markdown cache without tag support');
                }
            }
        } catch (\Exception $e) {
            Log::error('Markdown cache flush failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        try {
            $stats = [
                'enabled' => $this->config['enabled'],
                'driver' => config('cache.default'),
                'supports_tags' => $this->config['tags'],
                'compression' => $this->config['compression'],
                'default_ttl' => $this->config['ttl'],
            ];

            // Try to get cache hit/miss stats if available
            if (config('cache.default') === 'redis') {
                $redis = Cache::getRedis();
                $info = $redis->info('stats');
                if (isset($info['Stats'])) {
                    $stats['redis_stats'] = [
                        'keyspace_hits' => $info['Stats']['keyspace_hits'] ?? 0,
                        'keyspace_misses' => $info['Stats']['keyspace_misses'] ?? 0,
                    ];
                }
            }

            return $stats;
        } catch (\Exception $e) {
            Log::warning('Could not retrieve cache stats', ['error' => $e->getMessage()]);
            return ['enabled' => $this->config['enabled'], 'error' => $e->getMessage()];
        }
    }

    /**
     * Warm cache with pre-computed content
     */
    public function warmCache(array $contentItems): int
    {
        if (!$this->config['enabled']) {
            return 0;
        }

        $warmed = 0;

        foreach ($contentItems as $item) {
            try {
                $markdown = $item['markdown'] ?? '';
                $html = $item['html'] ?? '';
                $options = $item['options'] ?? [];
                $metadata = $item['metadata'] ?? [];

                if (!empty($markdown) && !empty($html)) {
                    $this->cacheWithMetadata($markdown, $html, $metadata, $options);
                    $warmed++;
                }
            } catch (\Exception $e) {
                Log::warning('Cache warming failed for item', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Markdown cache warmed', ['items_cached' => $warmed]);

        return $warmed;
    }

    /**
     * Generate cache key from markdown content and options
     */
    private function generateCacheKey(string $markdown, array $options = []): string
    {
        // Create a hash from content and options
        $optionsHash = md5(serialize($options));
        $contentHash = md5($markdown);
        
        return self::CACHE_PREFIX . $contentHash . ':' . $optionsHash;
    }

    /**
     * Determine appropriate TTL based on content characteristics
     */
    private function determineTTL(string $markdown, array $options = []): int
    {
        $length = strlen($markdown);
        
        // Very short content (likely dynamic) - short TTL
        if ($length < 100) {
            return self::SHORT_TTL;
        }
        
        // Large, complex content (likely stable) - long TTL
        if ($length > 10000) {
            return self::LONG_TTL;
        }
        
        // Check if content has dynamic elements
        $hasDynamicElements = (
            strpos($markdown, '[[rack:') !== false ||
            strpos($markdown, '{param:') !== false ||
            strpos($markdown, ':::audio') !== false
        );
        
        if ($hasDynamicElements) {
            return self::SHORT_TTL;
        }
        
        return $this->config['ttl'];
    }

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Enable or disable caching
     */
    public function setEnabled(bool $enabled): void
    {
        $this->config['enabled'] = $enabled;
    }

    /**
     * Get cache key for content (useful for manual cache management)
     */
    public function getCacheKey(string $markdown, array $options = []): string
    {
        return $this->generateCacheKey($markdown, $options);
    }

    /**
     * Check if content is cached
     */
    public function isCached(string $markdown, array $options = []): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($markdown, $options);
        
        try {
            if ($this->config['tags']) {
                return Cache::tags(['markdown'])->has($cacheKey);
            }
            
            return Cache::has($cacheKey);
        } catch (\Exception $e) {
            return false;
        }
    }
}