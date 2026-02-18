<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content Security Policy Middleware
 * Implements comprehensive CSP headers to prevent XSS attacks
 */
class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Generate nonce for inline scripts/styles
        $nonce = $this->generateNonce();
        $request->attributes->set('csp_nonce', $nonce);
        
        // Build CSP directives
        $cspDirectives = $this->buildCspDirectives($nonce, $request);
        
        // Set CSP header
        $response->headers->set('Content-Security-Policy', $cspDirectives);
        
        // Additional security headers
        $this->setAdditionalSecurityHeaders($response);
        
        return $response;
    }
    
    /**
     * Generate a cryptographically secure nonce
     */
    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Build CSP directives based on environment and request context
     */
    private function buildCspDirectives(string $nonce, Request $request): string
    {
        $isDevelopment = app()->environment('local', 'development');
        
        $directives = [
            // Default source - restrict to self only
            "default-src 'self'",
            
            // Script sources - very restrictive
            "script-src 'self' 'nonce-{$nonce}'" . ($isDevelopment ? " 'unsafe-eval'" : ""),
            
            // Style sources - allow inline styles with nonce
            "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline'",
            
            // Image sources - allow data URIs for small images and CDNs
            "img-src 'self' data: https:",
            
            // Font sources
            "font-src 'self' data:",
            
            // Connect sources for AJAX requests
            "connect-src 'self'",
            
            // Frame sources - only allow specific domains for embeds
            "frame-src 'self' https://www.youtube.com https://w.soundcloud.com",
            
            // Child sources (for web workers, if needed)
            "child-src 'self'",
            
            // Object sources - completely disallow
            "object-src 'none'",
            
            // Base URI - restrict to self
            "base-uri 'self'",
            
            // Form action - restrict to self
            "form-action 'self'",
            
            // Frame ancestors - prevent clickjacking
            "frame-ancestors 'none'",
            
            // Media sources - allow self and data URIs
            "media-src 'self' data:",
            
            // Manifest sources
            "manifest-src 'self'",
            
            // Worker sources
            "worker-src 'self'",
        ];
        
        // Add upgrade-insecure-requests in production
        if (!$isDevelopment) {
            $directives[] = "upgrade-insecure-requests";
        }
        
        // Add report-uri for CSP violation reporting
        if (config('app.csp_report_uri')) {
            $directives[] = "report-uri " . config('app.csp_report_uri');
        }
        
        return implode('; ', $directives);
    }
    
    /**
     * Set additional security headers
     */
    private function setAdditionalSecurityHeaders(Response $response): void
    {
        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // X-Frame-Options (backup for frame-ancestors)
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // X-XSS-Protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy (Feature Policy replacement)
        $permissionsPolicy = [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'interest-cohort=()',
            'payment=()',
            'usb=()',
        ];
        $response->headers->set('Permissions-Policy', implode(', ', $permissionsPolicy));
        
        // Strict-Transport-Security (HSTS) - only in production over HTTPS
        if (app()->environment('production') && request()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Cross-Origin-Embedder-Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        
        // Cross-Origin-Opener-Policy
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        
        // Cross-Origin-Resource-Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
    }
}