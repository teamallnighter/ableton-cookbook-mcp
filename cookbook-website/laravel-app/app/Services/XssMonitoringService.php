<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

/**
 * XSS Monitoring and Security Incident Response Service
 * Detects, logs, and responds to XSS attempts and security violations
 */
class XssMonitoringService
{
    private array $xssPatterns;
    private array $suspiciousPatterns;
    private array $criticalPatterns;
    
    public function __construct()
    {
        $this->initializePatterns();
    }
    
    /**
     * Initialize XSS detection patterns with severity levels
     */
    private function initializePatterns(): void
    {
        $this->xssPatterns = [
            // Basic script injection
            '/<script[^>]*>/i' => 'critical',
            '/<\/script>/i' => 'critical',
            
            // Event handlers
            '/on\w+\s*=/i' => 'high',
            '/onload\s*=/i' => 'critical',
            '/onerror\s*=/i' => 'critical',
            '/onclick\s*=/i' => 'medium',
            
            // JavaScript protocols
            '/javascript:/i' => 'high',
            '/vbscript:/i' => 'high',
            '/data:text\/html/i' => 'high',
            
            // DOM manipulation
            '/document\.write/i' => 'high',
            '/document\.cookie/i' => 'critical',
            '/eval\s*\(/i' => 'critical',
            '/setTimeout\s*\(/i' => 'medium',
            '/setInterval\s*\(/i' => 'medium',
            
            // HTML injection
            '/<iframe[^>]*srcdoc/i' => 'high',
            '/<object[^>]*>/i' => 'high',
            '/<embed[^>]*>/i' => 'high',
            '/<applet[^>]*>/i' => 'high',
            
            // CSS injection
            '/expression\s*\(/i' => 'high',
            '/url\s*\(\s*javascript:/i' => 'high',
            '/@import.*javascript:/i' => 'high',
            
            // Advanced techniques
            '/String\.fromCharCode/i' => 'medium',
            '/\\\u[0-9a-f]{4}/i' => 'medium',
            '/&#x[0-9a-f]+;/i' => 'low',
            
            // SVG injection
            '/<svg[^>]*>/i' => 'medium',
            '/xmlns:xlink/i' => 'medium',
            
            // Form manipulation
            '/<form[^>]*action\s*=\s*["\']javascript:/i' => 'high',
            
            // Meta refresh attacks
            '/<meta[^>]*http-equiv.*refresh/i' => 'medium',
        ];
        
        $this->suspiciousPatterns = [
            '/alert\s*\(/i' => 'Suspicious alert() call',
            '/confirm\s*\(/i' => 'Suspicious confirm() call',
            '/prompt\s*\(/i' => 'Suspicious prompt() call',
            '/console\.log/i' => 'Console manipulation attempt',
            '/window\./i' => 'Window object manipulation',
            '/location\./i' => 'Location manipulation attempt',
            '/history\./i' => 'History manipulation attempt',
            '/%3c/i' => 'URL-encoded < character',
            '/%3e/i' => 'URL-encoded > character',
        ];
        
        $this->criticalPatterns = [
            '/<script[^>]*src\s*=\s*["\'][^"\']*["\'][^>]*>/i' => 'External script inclusion',
            '/fetch\s*\(/i' => 'Fetch API usage',
            '/XMLHttpRequest/i' => 'AJAX request attempt',
            '/WebSocket/i' => 'WebSocket connection attempt',
            '/postMessage/i' => 'PostMessage usage',
            '/localStorage/i' => 'Local storage access',
            '/sessionStorage/i' => 'Session storage access',
        ];
    }
    
    /**
     * Monitor content for XSS attempts with comprehensive analysis
     */
    public function monitorContent(string $content, string $context = 'unknown', ?Request $request = null): array
    {
        $request = $request ?: request();
        
        $analysis = [
            'has_threats' => false,
            'threat_level' => 'none',
            'detected_patterns' => [],
            'security_score' => 100,
            'recommendations' => [],
            'incident_id' => null
        ];
        
        // Check rate limiting for this IP
        if ($this->isRateLimited($request)) {
            $analysis['has_threats'] = true;
            $analysis['threat_level'] = 'critical';
            $analysis['recommendations'][] = 'IP rate limited due to excessive security violations';
            return $analysis;
        }
        
        // Analyze content for XSS patterns
        $detectedThreats = $this->analyzeContent($content);
        
        if (!empty($detectedThreats)) {
            $analysis['has_threats'] = true;
            $analysis['detected_patterns'] = $detectedThreats;
            $analysis['threat_level'] = $this->calculateThreatLevel($detectedThreats);
            $analysis['security_score'] = $this->calculateSecurityScore($detectedThreats);
            $analysis['recommendations'] = $this->generateRecommendations($detectedThreats);
            
            // Log security incident
            $analysis['incident_id'] = $this->logSecurityIncident(
                $content,
                $detectedThreats,
                $context,
                $request,
                $analysis['threat_level']
            );
            
            // Apply rate limiting if critical
            if ($analysis['threat_level'] === 'critical') {
                $this->applyRateLimiting($request);
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze content for malicious patterns
     */
    private function analyzeContent(string $content): array
    {
        $threats = [];
        
        // Check XSS patterns
        foreach ($this->xssPatterns as $pattern => $severity) {
            if (preg_match($pattern, $content, $matches)) {
                $threats[] = [
                    'type' => 'xss',
                    'pattern' => $pattern,
                    'severity' => $severity,
                    'match' => $matches[0] ?? '',
                    'context' => substr($content, max(0, strpos($content, $matches[0]) - 20), 60)
                ];
            }
        }
        
        // Check suspicious patterns
        foreach ($this->suspiciousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches)) {
                $threats[] = [
                    'type' => 'suspicious',
                    'pattern' => $pattern,
                    'severity' => 'medium',
                    'description' => $description,
                    'match' => $matches[0] ?? '',
                    'context' => substr($content, max(0, strpos($content, $matches[0]) - 20), 60)
                ];
            }
        }
        
        // Check critical patterns
        foreach ($this->criticalPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches)) {
                $threats[] = [
                    'type' => 'critical',
                    'pattern' => $pattern,
                    'severity' => 'critical',
                    'description' => $description,
                    'match' => $matches[0] ?? '',
                    'context' => substr($content, max(0, strpos($content, $matches[0]) - 20), 60)
                ];
            }
        }
        
        return $threats;
    }
    
    /**
     * Calculate overall threat level
     */
    private function calculateThreatLevel(array $threats): string
    {
        $maxSeverity = 'low';
        
        foreach ($threats as $threat) {
            $severity = $threat['severity'];
            
            if ($severity === 'critical') {
                return 'critical';
            } elseif ($severity === 'high' && $maxSeverity !== 'critical') {
                $maxSeverity = 'high';
            } elseif ($severity === 'medium' && !in_array($maxSeverity, ['critical', 'high'])) {
                $maxSeverity = 'medium';
            }
        }
        
        return $maxSeverity;
    }
    
    /**
     * Calculate security score (0-100, lower is worse)
     */
    private function calculateSecurityScore(array $threats): int
    {
        $score = 100;
        
        foreach ($threats as $threat) {
            switch ($threat['severity']) {
                case 'critical':
                    $score -= 40;
                    break;
                case 'high':
                    $score -= 25;
                    break;
                case 'medium':
                    $score -= 15;
                    break;
                case 'low':
                    $score -= 5;
                    break;
            }
        }
        
        return max(0, $score);
    }
    
    /**
     * Generate security recommendations
     */
    private function generateRecommendations(array $threats): array
    {
        $recommendations = [];
        $threatTypes = array_unique(array_column($threats, 'type'));
        
        if (in_array('xss', $threatTypes)) {
            $recommendations[] = 'Content contains potential XSS vectors - sanitize all user input';
            $recommendations[] = 'Use CSP headers to prevent script execution';
            $recommendations[] = 'Implement proper output encoding';
        }
        
        if (in_array('critical', $threatTypes)) {
            $recommendations[] = 'Critical security threat detected - content should be rejected';
            $recommendations[] = 'Consider implementing additional input validation';
            $recommendations[] = 'Review and strengthen security policies';
        }
        
        if (in_array('suspicious', $threatTypes)) {
            $recommendations[] = 'Suspicious patterns detected - review content manually';
            $recommendations[] = 'Consider additional user verification';
        }
        
        return $recommendations;
    }
    
    /**
     * Log security incident with comprehensive details
     */
    private function logSecurityIncident(
        string $content,
        array $threats,
        string $context,
        Request $request,
        string $threatLevel
    ): string {
        $incidentId = 'XSS-' . date('Ymd-His') . '-' . substr(md5($content), 0, 8);
        
        $incidentData = [
            'incident_id' => $incidentId,
            'timestamp' => now()->toISOString(),
            'threat_level' => $threatLevel,
            'context' => $context,
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 500),
            'detected_threats' => $threats,
            'request_data' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'referer' => $request->header('referer'),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ],
            'environment' => [
                'app_env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ]
        ];
        
        // Log based on severity
        switch ($threatLevel) {
            case 'critical':
                Log::critical('XSS Security Incident - CRITICAL', $incidentData);
                break;
            case 'high':
                Log::error('XSS Security Incident - HIGH', $incidentData);
                break;
            case 'medium':
                Log::warning('XSS Security Incident - MEDIUM', $incidentData);
                break;
            default:
                Log::info('XSS Security Incident - LOW', $incidentData);
        }
        
        // Store in cache for quick access
        Cache::put("security_incident_{$incidentId}", $incidentData, 3600);
        
        // Send notifications for critical threats
        if ($threatLevel === 'critical') {
            $this->sendCriticalThreatNotification($incidentData);
        }
        
        return $incidentId;
    }
    
    /**
     * Check if IP is rate limited
     */
    private function isRateLimited(Request $request): bool
    {
        $key = 'security_violations:' . $request->ip();
        return RateLimiter::tooManyAttempts($key, 10); // 10 violations per hour
    }
    
    /**
     * Apply rate limiting to suspicious IP
     */
    private function applyRateLimiting(Request $request): void
    {
        $key = 'security_violations:' . $request->ip();
        RateLimiter::hit($key, 3600); // 1 hour rate limit
        
        Log::warning('Rate limiting applied to IP', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempts' => RateLimiter::attempts($key)
        ]);
    }
    
    /**
     * Send critical threat notification
     */
    private function sendCriticalThreatNotification(array $incidentData): void
    {
        // This could integrate with Slack, email, SMS, or other notification systems
        Log::critical('SECURITY ALERT: Critical XSS threat detected', [
            'incident_id' => $incidentData['incident_id'],
            'ip' => $incidentData['request_data']['ip'],
            'user_id' => $incidentData['request_data']['user_id'],
            'url' => $incidentData['request_data']['url'],
            'threat_count' => count($incidentData['detected_threats'])
        ]);
        
        // Store in high-priority cache for security team dashboard
        Cache::put(
            "critical_security_alert_{$incidentData['incident_id']}", 
            $incidentData, 
            86400 // 24 hours
        );
    }
    
    /**
     * Get security metrics for monitoring dashboard
     */
    public function getSecurityMetrics(string $period = '24h'): array
    {
        $cacheKey = "security_metrics_{$period}";
        
        return Cache::remember($cacheKey, 300, function () use ($period) {
            // This would typically query a database or log aggregation service
            // For now, return mock data structure
            return [
                'total_incidents' => 0,
                'critical_incidents' => 0,
                'blocked_ips' => 0,
                'threat_types' => [],
                'top_attack_vectors' => [],
                'geographic_distribution' => [],
                'hourly_distribution' => [],
            ];
        });
    }
    
    /**
     * Emergency sanitization for suspected attacks
     */
    public function emergencySanitize(string $content): string
    {
        Log::critical('Emergency content sanitization triggered', [
            'content_length' => strlen($content),
            'content_hash' => md5($content),
            'timestamp' => now()
        ]);
        
        // Aggressive sanitization
        $content = strip_tags($content);
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[<>"\']/', '', $content);
        
        return $content;
    }
}