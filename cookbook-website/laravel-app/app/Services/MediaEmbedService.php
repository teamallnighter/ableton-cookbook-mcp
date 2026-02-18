<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Media Embed Service
 * Handles rich media embedding with oEmbed support and security validation
 */
class MediaEmbedService
{
    private array $oembed_providers = [
        'youtube' => [
            'endpoint' => 'https://www.youtube.com/oembed',
            'schemes' => [
                'https://www.youtube.com/watch?v=*',
                'https://youtu.be/*',
                'https://www.youtube.com/embed/*'
            ]
        ],
        'vimeo' => [
            'endpoint' => 'https://vimeo.com/api/oembed.json',
            'schemes' => [
                'https://vimeo.com/*',
                'https://player.vimeo.com/video/*'
            ]
        ],
        'soundcloud' => [
            'endpoint' => 'https://soundcloud.com/oembed',
            'schemes' => [
                'https://soundcloud.com/*'
            ]
        ]
    ];
    
    private array $allowed_domains = [
        'youtube.com', 'www.youtube.com', 'youtu.be',
        'vimeo.com', 'player.vimeo.com',
        'soundcloud.com', 'w.soundcloud.com'
    ];

    /**
     * Process rich media embeds in markdown content
     */
    public function processRichMedia(string $markdown): string
    {
        // Process YouTube embeds
        $markdown = $this->processYouTubeEmbeds($markdown);
        
        // Process Vimeo embeds  
        $markdown = $this->processVimeoEmbeds($markdown);
        
        // Process SoundCloud embeds
        $markdown = $this->processSoundCloudEmbeds($markdown);
        
        // Process generic oEmbed URLs
        $markdown = $this->processGenericOEmbeds($markdown);
        
        return $markdown;
    }

    /**
     * Process YouTube URLs with enhanced oEmbed support
     */
    private function processYouTubeEmbeds(string $markdown): string
    {
        // Enhanced pattern for YouTube links with title extraction
        $pattern = '/\[([^\]]*)\]\((https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)[^\)]*)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $title = $matches[1];
            $url = $matches[2];
            $videoId = $this->extractYouTubeId($url);
            
            if (!$videoId) {
                return $matches[0]; // Return original if can't extract ID
            }
            
            // Get enhanced embed data
            $embedData = $this->getYouTubeOEmbedData($url);
            
            return $this->generateYouTubeEmbed($videoId, $title, $embedData);
        }, $markdown);
    }

    /**
     * Process Vimeo URLs with oEmbed
     */
    private function processVimeoEmbeds(string $markdown): string
    {
        $pattern = '/\[([^\]]*)\]\((https?:\/\/(www\.)?vimeo\.com\/(\d+)[^\)]*)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $title = $matches[1];
            $url = $matches[2];
            $videoId = $matches[4];
            
            $embedData = $this->getVimeoOEmbedData($url);
            
            return $this->generateVimeoEmbed($videoId, $title, $embedData);
        }, $markdown);
    }

    /**
     * Process SoundCloud URLs with enhanced oEmbed
     */
    private function processSoundCloudEmbeds(string $markdown): string
    {
        $pattern = '/\[SoundCloud(?:\s*-\s*([^\]]*))?\]\((https:\/\/soundcloud\.com\/[^\)]+)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $title = $matches[1] ?? '';
            $url = $matches[2];
            
            if (!$this->isValidSoundCloudUrl($url)) {
                return $matches[0];
            }
            
            $embedData = $this->getSoundCloudOEmbedData($url);
            
            return $this->generateSoundCloudEmbed($url, $title, $embedData);
        }, $markdown);
    }

    /**
     * Process generic oEmbed URLs
     */
    private function processGenericOEmbeds(string $markdown): string
    {
        // Pattern for [embed](url) syntax
        $pattern = '/\[embed\]\((https?:\/\/[^\)]+)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $url = $matches[1];
            
            if (!$this->isAllowedDomain($url)) {
                return $matches[0]; // Return original if not allowed
            }
            
            $embedData = $this->getGenericOEmbedData($url);
            
            if ($embedData) {
                return $this->generateGenericEmbed($embedData);
            }
            
            return $matches[0];
        }, $markdown);
    }

    /**
     * Get YouTube oEmbed data with caching
     */
    private function getYouTubeOEmbedData(string $url): ?array
    {
        $cacheKey = 'oembed_youtube_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::timeout(10)
                    ->get('https://www.youtube.com/oembed', [
                        'url' => $url,
                        'format' => 'json',
                        'maxwidth' => 560,
                        'maxheight' => 315
                    ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('YouTube oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
            
            return null;
        });
    }

    /**
     * Get Vimeo oEmbed data with caching
     */
    private function getVimeoOEmbedData(string $url): ?array
    {
        $cacheKey = 'oembed_vimeo_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::timeout(10)
                    ->get('https://vimeo.com/api/oembed.json', [
                        'url' => $url,
                        'maxwidth' => 560,
                        'maxheight' => 315
                    ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('Vimeo oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
            
            return null;
        });
    }

    /**
     * Get SoundCloud oEmbed data with caching
     */
    private function getSoundCloudOEmbedData(string $url): ?array
    {
        $cacheKey = 'oembed_soundcloud_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::timeout(10)
                    ->get('https://soundcloud.com/oembed', [
                        'url' => $url,
                        'format' => 'json',
                        'maxheight' => 166
                    ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('SoundCloud oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
            
            return null;
        });
    }

    /**
     * Get generic oEmbed data
     */
    private function getGenericOEmbedData(string $url): ?array
    {
        $provider = $this->findOEmbedProvider($url);
        if (!$provider) {
            return null;
        }
        
        $cacheKey = 'oembed_generic_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url, $provider) {
            try {
                $response = Http::timeout(10)
                    ->get($provider['endpoint'], [
                        'url' => $url,
                        'format' => 'json'
                    ]);
                
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('Generic oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
            
            return null;
        });
    }

    /**
     * Generate enhanced YouTube embed HTML
     */
    private function generateYouTubeEmbed(string $videoId, string $title, ?array $embedData): string
    {
        $actualTitle = $embedData['title'] ?? $title ?? 'YouTube Video';
        $authorName = $embedData['author_name'] ?? '';
        $thumbnailUrl = $embedData['thumbnail_url'] ?? '';
        
        $html = '<div class="youtube-embed my-6">';
        
        // Add header with title and author
        if ($actualTitle || $authorName) {
            $html .= '<div class="youtube-embed__header mb-3">';
            if ($actualTitle) {
                $html .= '<h4 class="youtube-embed__title text-lg font-medium">' . htmlspecialchars($actualTitle) . '</h4>';
            }
            if ($authorName) {
                $html .= '<p class="youtube-embed__author text-sm text-gray-600">by ' . htmlspecialchars($authorName) . '</p>';
            }
            $html .= '</div>';
        }
        
        // Add responsive iframe
        $html .= '<div class="youtube-embed__player relative pb-[56.25%] h-0 overflow-hidden rounded-lg shadow-lg">';
        $html .= '<iframe ';
        $html .= 'class="absolute top-0 left-0 w-full h-full" ';
        $html .= 'src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '?rel=0" ';
        $html .= 'frameborder="0" ';
        $html .= 'allowfullscreen ';
        $html .= 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
        $html .= 'loading="lazy">';
        $html .= '</iframe>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate enhanced Vimeo embed HTML
     */
    private function generateVimeoEmbed(string $videoId, string $title, ?array $embedData): string
    {
        $actualTitle = $embedData['title'] ?? $title ?? 'Vimeo Video';
        $authorName = $embedData['author_name'] ?? '';
        
        $html = '<div class="vimeo-embed my-6">';
        
        if ($actualTitle || $authorName) {
            $html .= '<div class="vimeo-embed__header mb-3">';
            if ($actualTitle) {
                $html .= '<h4 class="vimeo-embed__title text-lg font-medium">' . htmlspecialchars($actualTitle) . '</h4>';
            }
            if ($authorName) {
                $html .= '<p class="vimeo-embed__author text-sm text-gray-600">by ' . htmlspecialchars($authorName) . '</p>';
            }
            $html .= '</div>';
        }
        
        $html .= '<div class="vimeo-embed__player relative pb-[56.25%] h-0 overflow-hidden rounded-lg shadow-lg">';
        $html .= '<iframe ';
        $html .= 'class="absolute top-0 left-0 w-full h-full" ';
        $html .= 'src="https://player.vimeo.com/video/' . htmlspecialchars($videoId) . '" ';
        $html .= 'frameborder="0" ';
        $html .= 'allowfullscreen ';
        $html .= 'loading="lazy">';
        $html .= '</iframe>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate enhanced SoundCloud embed HTML
     */
    private function generateSoundCloudEmbed(string $url, string $title, ?array $embedData): string
    {
        $actualTitle = $embedData['title'] ?? $title ?? 'SoundCloud Track';
        $authorName = $embedData['author_name'] ?? '';
        $embedHtml = $embedData['html'] ?? '';
        
        $html = '<div class="soundcloud-embed my-6">';
        
        if ($actualTitle || $authorName) {
            $html .= '<div class="soundcloud-embed__header mb-3">';
            if ($actualTitle) {
                $html .= '<h4 class="soundcloud-embed__title text-lg font-medium">' . htmlspecialchars($actualTitle) . '</h4>';
            }
            if ($authorName) {
                $html .= '<p class="soundcloud-embed__author text-sm text-gray-600">by ' . htmlspecialchars($authorName) . '</p>';
            }
            $html .= '</div>';
        }
        
        if ($embedHtml) {
            // Use the official embed HTML from SoundCloud oEmbed
            $html .= '<div class="soundcloud-embed__player">' . $embedHtml . '</div>';
        } else {
            // Fallback embed
            $encodedUrl = urlencode($url);
            $embedUrl = "https://w.soundcloud.com/player/?url={$encodedUrl}&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true";
            
            $html .= '<div class="soundcloud-embed__player">';
            $html .= '<iframe ';
            $html .= 'width="100%" ';
            $html .= 'height="166" ';
            $html .= 'scrolling="no" ';
            $html .= 'frameborder="no" ';
            $html .= 'allow="autoplay" ';
            $html .= 'src="' . htmlspecialchars($embedUrl) . '" ';
            $html .= 'loading="lazy">';
            $html .= '</iframe>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate generic embed HTML
     */
    private function generateGenericEmbed(array $embedData): string
    {
        $type = $embedData['type'] ?? 'rich';
        $html = $embedData['html'] ?? '';
        $title = $embedData['title'] ?? '';
        $providerName = $embedData['provider_name'] ?? '';
        
        if (!$html) {
            return '';
        }
        
        $output = '<div class="generic-embed my-6">';
        
        if ($title || $providerName) {
            $output .= '<div class="generic-embed__header mb-3">';
            if ($title) {
                $output .= '<h4 class="generic-embed__title text-lg font-medium">' . htmlspecialchars($title) . '</h4>';
            }
            if ($providerName) {
                $output .= '<p class="generic-embed__provider text-sm text-gray-600">via ' . htmlspecialchars($providerName) . '</p>';
            }
            $output .= '</div>';
        }
        
        $output .= '<div class="generic-embed__content">' . $this->sanitizeEmbedHtml($html) . '</div>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Extract YouTube video ID from various URL formats
     */
    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/i',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/i',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Validate SoundCloud URL
     */
    private function isValidSoundCloudUrl(string $url): bool
    {
        try {
            $parsedUrl = parse_url($url);
            return $parsedUrl && 
                   isset($parsedUrl['host']) && 
                   in_array(strtolower($parsedUrl['host']), ['soundcloud.com', 'www.soundcloud.com']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if domain is allowed for embeds
     */
    private function isAllowedDomain(string $url): bool
    {
        try {
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['host'])) {
                return false;
            }
            
            return in_array(strtolower($parsedUrl['host']), $this->allowed_domains);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Find oEmbed provider for URL
     */
    private function findOEmbedProvider(string $url): ?array
    {
        foreach ($this->oembed_providers as $providerName => $provider) {
            foreach ($provider['schemes'] as $scheme) {
                $pattern = str_replace('*', '.*', preg_quote($scheme, '/'));
                if (preg_match('/^' . $pattern . '$/i', $url)) {
                    return $provider;
                }
            }
        }
        
        return null;
    }

    /**
     * Sanitize embed HTML for security
     */
    private function sanitizeEmbedHtml(string $html): string
    {
        // Basic sanitization for embed HTML
        // In a production environment, you'd want more sophisticated sanitization
        $allowedTags = '<iframe><div><span><p><br><strong><em><a>';
        
        return strip_tags($html, $allowedTags);
    }

    /**
     * Clear oEmbed cache for specific URL
     */
    public function clearCache(string $url): void
    {
        $patterns = [
            'oembed_youtube_',
            'oembed_vimeo_',
            'oembed_soundcloud_',
            'oembed_generic_'
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern . md5($url));
        }
    }

    /**
     * Clear all oEmbed cache
     */
    public function clearAllCache(): void
    {
        // This would require a more sophisticated cache tagging system
        // For now, we'll implement individual cache clearing
        Log::info('oEmbed cache clear requested - implement cache tagging for bulk clearing');
    }
}