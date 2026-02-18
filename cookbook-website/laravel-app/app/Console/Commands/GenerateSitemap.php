<?php

namespace App\Console\Commands;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--cache-only : Only generate cache, don\'t save to files}';
    protected $description = 'Generate XML sitemaps for SEO';

    public function handle()
    {
        $this->info('Generating sitemaps...');

        // Generate main sitemap index
        $this->generateSitemapIndex();

        // Generate individual sitemaps
        $this->generateStaticSitemap();
        $this->generateRacksSitemap();
        $this->generateUsersSitemap();

        if (!$this->option('cache-only')) {
            $this->generateSitemapFiles();
        }

        $this->info('Sitemaps generated successfully!');
    }

    private function generateSitemapIndex()
    {
        $sitemaps = [
            ['loc' => route('sitemap.static'), 'lastmod' => now()->toISOString()],
            ['loc' => route('sitemap.racks'), 'lastmod' => $this->getLastRackUpdate()],
            ['loc' => route('sitemap.users'), 'lastmod' => $this->getLastUserUpdate()],
        ];

        $xml = view('sitemaps.index', compact('sitemaps'))->render();
        Cache::put('sitemap.index', $xml, now()->addDay());
        
        $this->line('Generated main sitemap index');
    }

    private function generateStaticSitemap()
    {
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
        
        $this->line('Generated static pages sitemap');
    }

    private function generateRacksSitemap()
    {
        $racksCount = Rack::published()->count();
        
        if ($racksCount === 0) {
            $this->line('No published racks found, generating empty racks sitemap');
            $urls = collect();
        } else {
            $urls = Rack::published()
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
        }

        $xml = view('sitemaps.urlset', ['urls' => $urls])->render();
        Cache::put('sitemap.racks', $xml, now()->addHour(4));
        
        $this->line("Generated racks sitemap ({$racksCount} racks)");
    }

    private function generateUsersSitemap()
    {
        $usersCount = User::whereHas('racks', function ($query) {
            $query->published();
        })->count();

        if ($usersCount === 0) {
            $this->line('No users with published racks found, generating empty users sitemap');
            $urls = collect();
        } else {
            $urls = User::whereHas('racks', function ($query) {
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
        }

        $xml = view('sitemaps.urlset', ['urls' => $urls])->render();
        Cache::put('sitemap.users', $xml, now()->addHour(4));
        
        $this->line("Generated users sitemap ({$usersCount} users)");
    }

    private function generateSitemapFiles()
    {
        // Save to public directory for direct access
        $sitemaps = [
            'sitemap.xml' => Cache::get('sitemap.index'),
            'sitemap-static.xml' => Cache::get('sitemap.static'),
            'sitemap-racks.xml' => Cache::get('sitemap.racks'),
            'sitemap-users.xml' => Cache::get('sitemap.users'),
        ];

        foreach ($sitemaps as $filename => $content) {
            file_put_contents(public_path($filename), $content);
            $this->line("Saved {$filename} to public directory");
        }
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
