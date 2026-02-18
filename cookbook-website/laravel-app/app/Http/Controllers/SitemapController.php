<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // Try to get cached sitemap first, generate if not found
        $content = Cache::get('sitemap.index', function () {
            $sitemaps = [
                ['loc' => route('sitemap.static'), 'lastmod' => now()->toISOString()],
                ['loc' => route('sitemap.racks'), 'lastmod' => $this->getLastRackUpdate()],
                ['loc' => route('sitemap.users'), 'lastmod' => $this->getLastUserUpdate()],
            ];

            $xml = view('sitemaps.index', compact('sitemaps'))->render();
            Cache::put('sitemap.index', $xml, now()->addDay());
            return $xml;
        });

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
    }

    public function static(): Response
    {
        $content = Cache::get('sitemap.static', function () {
            $staticPages = [
                [
                    'loc' => route('home'),
                    'lastmod' => now()->toISOString(),
                    'changefreq' => 'daily',
                    'priority' => '1.0'
                ],
                [
                    'loc' => route('racks.upload'),
                    'lastmod' => now()->toISOString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7'
                ],
            ];

            $xml = view('sitemaps.urlset', ['urls' => $staticPages])->render();
            Cache::put('sitemap.static', $xml, now()->addDay());
            return $xml;
        });

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function racks(): Response
    {
        $content = Cache::get('sitemap.racks', function () {
            $racks = Rack::published()
                ->select(['id', 'slug', 'title', 'updated_at', 'published_at', 'preview_image_path'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($rack) {
                    return [
                        'loc' => route('racks.show', $rack),
                        'lastmod' => $rack->updated_at->toISOString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                        'image' => $rack->preview_image_path ? [
                            'loc' => asset('storage/' . $rack->preview_image_path),
                            'title' => $rack->title,
                            'caption' => $rack->title . ' - Ableton Live Rack Preview',
                        ] : null,
                    ];
                });

            $xml = view('sitemaps.urlset', ['urls' => $racks])->render();
            Cache::put('sitemap.racks', $xml, now()->addHour(4));
            return $xml;
        });

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=14400'); // Cache for 4 hours
    }

    public function users(): Response
    {
        $content = Cache::get('sitemap.users', function () {
            $users = User::whereHas('racks', function ($query) {
                    $query->published();
                })
                ->select(['id', 'name', 'updated_at'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($user) {
                    return [
                        'loc' => route('users.show', $user),
                        'lastmod' => $user->updated_at->toISOString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                    ];
                });

            $xml = view('sitemaps.urlset', ['urls' => $users])->render();
            Cache::put('sitemap.users', $xml, now()->addHour(4));
            return $xml;
        });

        return response($content)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=14400');
    }

    private function getLastRackUpdate(): string
    {
        $lastRack = Rack::published()->latest('updated_at')->first();
        return $lastRack ? $lastRack->updated_at->toISOString() : now()->toISOString();
    }

    private function getLastUserUpdate(): string
    {
        $lastUser = User::whereHas('racks', function ($query) {
            $query->published();
        })->latest('updated_at')->first();
        
        return $lastUser ? $lastUser->updated_at->toISOString() : now()->toISOString();
    }
}
