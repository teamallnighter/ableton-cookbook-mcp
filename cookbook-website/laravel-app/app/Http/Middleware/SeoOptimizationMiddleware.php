<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoOptimizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply to HTML responses
        if (!$response instanceof \Illuminate\Http\Response || 
            !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }

        // Add performance and SEO headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Cache control for static assets
        if ($request->is('images/*') || $request->is('css/*') || $request->is('js/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        }

        // Preload critical resources in HTML
        if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $content = $response->getContent();
            
            // Add preload hints for critical resources
            $preloads = [
                '</css/app.css>; rel=preload; as=style',
                '</js/app.js>; rel=preload; as=script',
                '<https://fonts.bunny.net>; rel=preconnect',
                '<https://cdn.jsdelivr.net>; rel=preconnect',
            ];
            
            $response->headers->set('Link', implode(', ', $preloads));
            
            // Minify HTML in production
            if (app()->environment('production')) {
                $content = $this->minifyHtml($content);
                $response->setContent($content);
            }
        }

        return $response;
    }

    /**
     * Minify HTML content
     */
    private function minifyHtml(string $html): string
    {
        // Remove comments (but preserve IE conditional comments)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around block elements
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
}