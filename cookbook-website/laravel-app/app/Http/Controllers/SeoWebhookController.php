<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class SeoWebhookController extends Controller
{
    public function refreshSitemap(Request $request): JsonResponse
    {
        // Simple security check - you might want to add proper authentication
        $token = $request->header('X-SEO-Token') ?? $request->get('token');
        $expectedToken = config('app.seo_webhook_token', 'change-this-token');
        
        if ($token !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Clear sitemap caches
            Cache::forget('sitemap.index');
            Cache::forget('sitemap.static');
            Cache::forget('sitemap.racks');
            Cache::forget('sitemap.users');

            // Regenerate sitemaps
            Artisan::call('sitemap:generate', ['--cache-only' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Sitemaps refreshed successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to refresh sitemaps',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'SEO Webhook Service',
            'timestamp' => now()->toISOString(),
            'sitemaps' => [
                'index' => Cache::has('sitemap.index'),
                'static' => Cache::has('sitemap.static'),
                'racks' => Cache::has('sitemap.racks'),
                'users' => Cache::has('sitemap.users'),
            ]
        ]);
    }
}
