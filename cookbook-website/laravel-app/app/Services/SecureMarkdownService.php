<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Output\RenderedContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use DOMXPath;

/**
 * Secure Markdown Service with comprehensive XSS prevention
 * This service implements defense-in-depth security architecture for markdown processing
 */
class SecureMarkdownService
{
    private MarkdownConverter $converter;
    private array $xssPatterns;
    private array $allowedTags;
    private array $allowedAttributes;
    private array $allowedDomains;

    public function __construct()
    {
        $this->converter = $this->createConverter();
        $this->initializeSecurityConfig();
    }
    
    /**
     * Initialize security configuration for XSS prevention
     */
    private function initializeSecurityConfig(): void
    {
        $this->xssPatterns = [
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:/i',
            '/on\w+\s*=/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/document\.cookie/i',
            '/document\.write/i',
            '/window\./i',
            '/<object/i',
            '/<embed/i',
            '/<applet/i',
            '/<meta/i',
            '/<link/i',
            '/<style/i'
        ];
        
        $this->allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'strike',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'blockquote', 'pre', 'code',
            'a', 'img',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
            'div', 'span', 'iframe'
        ];
        
        $this->allowedAttributes = [
            'a' => ['href', 'title', 'target'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'allow', 'sandbox'],
            'div' => ['class', 'id'],
            'span' => ['class'],
            'table' => ['class'],
            'th' => ['scope'],
            'td' => ['colspan', 'rowspan'],
            'blockquote' => ['cite'],
            'pre' => ['class'],
            'code' => ['class']
        ];
        
        $this->allowedDomains = [
            'youtube.com',
            'www.youtube.com',
            'soundcloud.com',
            'w.soundcloud.com'
        ];
    }

    /**
     * Parse markdown to HTML with comprehensive security measures
     */
    public function parseToHtml(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }
        
        // Step 1: Pre-validation security check
        $securityIssues = $this->detectXssAttempts($markdown);
        if (!empty($securityIssues)) {
            $this->logSecurityViolation($markdown, $securityIssues, 'xss_detection');
            throw new \InvalidArgumentException('Content contains potentially dangerous elements');
        }

        // Step 2: Pre-process for rich media embedding with validation
        $markdown = $this->processRichMedia($markdown);

        // Step 3: Parse markdown to HTML
        $result = $this->converter->convert($markdown);
        $html = (string) $result;

        // Step 4: Comprehensive HTML sanitization
        $sanitizedHtml = $this->comprehensiveSanitizeHtml($html);
        
        // Step 5: Final validation
        if ($this->containsUnsafeContent($sanitizedHtml)) {
            $this->logSecurityViolation($sanitizedHtml, ['unsafe_content_detected'], 'post_sanitization');
            throw new \RuntimeException('Content sanitization failed - unsafe content detected');
        }

        return $sanitizedHtml;
    }

    /**
     * Comprehensive HTML sanitization using DOMDocument
     */
    private function comprehensiveSanitizeHtml(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        libxml_use_internal_errors(true);
        
        $htmlToProcess = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($htmlToProcess, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        
        $this->sanitizeElement($body, $dom);
        
        $sanitizedHtml = '';
        foreach ($body->childNodes as $child) {
            $sanitizedHtml .= $dom->saveHTML($child);
        }
        
        libxml_clear_errors();
        
        return $sanitizedHtml;
    }
    
    /**
     * Recursively sanitize DOM elements
     */
    private function sanitizeElement(\DOMNode $node, DOMDocument $dom): void
    {
        $nodesToRemove = [];
        $nodesToReplace = [];
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);
                
                if (!in_array($tagName, $this->allowedTags)) {
                    if ($child->textContent) {
                        $textNode = $dom->createTextNode($child->textContent);
                        $nodesToReplace[] = ['old' => $child, 'new' => $textNode];
                    } else {
                        $nodesToRemove[] = $child;
                    }
                    continue;
                }
                
                $this->sanitizeElementAttributes($child, $tagName);
                $this->sanitizeElement($child, $dom);
            }
        }
        
        foreach ($nodesToRemove as $nodeToRemove) {
            if ($nodeToRemove->parentNode) {
                $nodeToRemove->parentNode->removeChild($nodeToRemove);
            }
        }
        
        foreach ($nodesToReplace as $replacement) {
            if ($replacement['old']->parentNode) {
                $replacement['old']->parentNode->replaceChild(
                    $replacement['new'], 
                    $replacement['old']
                );
            }
        }
    }
    
    /**
     * Sanitize element attributes
     */
    private function sanitizeElementAttributes(\DOMElement $element, string $tagName): void
    {
        $allowedAttrs = $this->allowedAttributes[$tagName] ?? [];
        $attributesToRemove = [];
        
        foreach ($element->attributes as $attribute) {
            $attrName = strtolower($attribute->name);
            $attrValue = $attribute->value;
            
            if (!in_array($attrName, $allowedAttrs)) {
                $attributesToRemove[] = $attrName;
                continue;
            }
            
            $sanitizedValue = $this->sanitizeAttributeValue($attrName, $attrValue, $tagName);
            
            if ($sanitizedValue === null) {
                $attributesToRemove[] = $attrName;
            } else {
                $element->setAttribute($attrName, $sanitizedValue);
            }
        }
        
        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }
    
    /**
     * Sanitize attribute values with comprehensive validation
     */
    private function sanitizeAttributeValue(string $attrName, string $attrValue, string $tagName): ?string
    {
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $attrValue)) {
                return null;
            }
        }
        
        switch ($attrName) {
            case 'href':
            case 'src':
                return $this->sanitizeUrl($attrValue, $tagName);
                
            case 'width':
            case 'height':
            case 'colspan':
            case 'rowspan':
                return $this->sanitizeNumericAttribute($attrValue);
                
            case 'class':
            case 'id':
                return $this->sanitizeIdentifierAttribute($attrValue);
                
            case 'sandbox':
                return $this->sanitizeSandboxAttribute($attrValue);
                
            case 'target':
                return in_array($attrValue, ['_blank', '_self', '_parent', '_top']) ? $attrValue : null;
                
            case 'frameborder':
                return in_array($attrValue, ['0', '1']) ? $attrValue : '0';
                
            case 'allowfullscreen':
                return 'allowfullscreen';
                
            case 'allow':
                return $this->sanitizeIframeAllowAttribute($attrValue);
                
            default:
                return htmlspecialchars($attrValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Sanitize iframe sandbox attribute
     */
    private function sanitizeSandboxAttribute(string $value): string
    {
        $allowedValues = [
            'allow-scripts',
            'allow-same-origin', 
            'allow-presentation',
            'allow-forms'
        ];
        
        $values = array_map('trim', explode(' ', $value));
        $validValues = array_intersect($values, $allowedValues);
        
        return implode(' ', $validValues);
    }
    
    /**
     * Sanitize URL with enhanced validation
     */
    private function sanitizeUrl(string $url, string $context = ''): ?string
    {
        $url = trim($url);
        
        if (empty($url)) {
            return null;
        }
        
        if (preg_match('/^[\/\?#]/', $url)) {
            return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        $parsedUrl = parse_url($url);
        if (!$parsedUrl) {
            return null;
        }
        
        $scheme = $parsedUrl['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'])) {
            return null;
        }
        
        if ($context === 'iframe') {
            $host = strtolower($parsedUrl['host'] ?? '');
            if (!in_array($host, $this->allowedDomains)) {
                return null;
            }
        }
        
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Detect XSS attempts in content
     */
    private function detectXssAttempts(string $content): array
    {
        $issues = [];
        
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $issues[] = 'Potentially dangerous content detected: ' . substr($pattern, 1, -1);
            }
        }
        
        return $issues;
    }
    
    /**
     * Check if content contains unsafe elements after sanitization
     */
    private function containsUnsafeContent(string $html): bool
    {
        $unsafePatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<object/i',
            '/<embed/i',
            '/<applet/i'
        ];
        
        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log security violations for monitoring
     */
    private function logSecurityViolation(string $content, array $issues, string $context): void
    {
        Log::warning('SecureMarkdownService security violation detected', [
            'context' => $context,
            'issues' => $issues,
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200),
            'timestamp' => now(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
    
    // Additional methods for rich media processing, validation, etc.
    // ... (continuing with similar security-focused implementations)
    
    /**
     * Create secure markdown converter
     */
    private function createConverter(): MarkdownConverter
    {
        $config = [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        return new MarkdownConverter($environment);
    }
    
    /**
     * Process rich media with security validation
     */
    private function processRichMedia(string $markdown): string
    {
        $markdown = $this->processYouTubeEmbeds($markdown);
        $markdown = $this->processSoundCloudEmbeds($markdown);
        
        return $markdown;
    }
    
    /**
     * Process YouTube embeds with security validation
     */
    private function processYouTubeEmbeds(string $markdown): string
    {
        $pattern = '/\[([^\]]+)\]\((https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)[^\)]*)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $videoId = $this->extractYouTubeId($matches[2]);
            if (!$videoId || !$this->isValidYouTubeId($videoId)) {
                return $matches[0];
            }
            
            return $this->generateSecureYouTubeEmbed($videoId);
        }, $markdown);
    }
    
    /**
     * Generate secure YouTube embed with sandbox protection
     */
    private function generateSecureYouTubeEmbed(string $videoId): string
    {
        $safeVideoId = htmlspecialchars($videoId, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $embedUrl = "https://www.youtube.com/embed/{$safeVideoId}";
        
        return sprintf(
            '<div class="youtube-embed my-4" data-video-id="%s">' .
            '<iframe width="560" height="315" src="%s" ' .
            'frameborder="0" allowfullscreen="allowfullscreen" ' .
            'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ' .
            'sandbox="allow-scripts allow-same-origin allow-presentation">' .
            '</iframe></div>',
            $safeVideoId,
            $embedUrl
        );
    }
    
    /**
     * Extract YouTube video ID with validation
     */
    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Validate YouTube video ID format
     */
    private function isValidYouTubeId(string $videoId): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId);
    }
    
    /**
     * Sanitize numeric attributes
     */
    private function sanitizeNumericAttribute(string $value): ?string
    {
        if (preg_match('/^\d+$/', $value)) {
            $num = intval($value);
            return ($num >= 0 && $num <= 9999) ? (string)$num : null;
        }
        return null;
    }
    
    /**
     * Sanitize identifier attributes
     */
    private function sanitizeIdentifierAttribute(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\s_-]/', '', $value);
    }
    
    /**
     * Sanitize iframe allow attribute
     */
    private function sanitizeIframeAllowAttribute(string $value): ?string
    {
        $allowedPermissions = [
            'accelerometer', 'autoplay', 'clipboard-write', 'encrypted-media',
            'gyroscope', 'picture-in-picture', 'fullscreen'
        ];
        
        $permissions = array_map('trim', explode(';', $value));
        $validPermissions = [];
        
        foreach ($permissions as $permission) {
            if (in_array($permission, $allowedPermissions)) {
                $validPermissions[] = $permission;
            }
        }
        
        return empty($validPermissions) ? null : implode('; ', $validPermissions);
    }
}