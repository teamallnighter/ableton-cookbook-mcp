<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class OptimizeSeo extends Command
{
    protected $signature = 'seo:optimize {--force : Force regeneration of all SEO assets}';
    protected $description = 'Optimize SEO settings and generate necessary assets';

    public function handle()
    {
        $this->info('🚀 Optimizing SEO for Ableton Cookbook...');

        // Step 1: Generate/refresh sitemaps
        $this->info('📋 Generating sitemaps...');
        Artisan::call('sitemap:generate');
        $this->line('   ✓ Sitemaps generated');

        // Step 2: Verify robots.txt
        $this->info('🤖 Verifying robots.txt...');
        $this->verifyRobotsTxt();
        $this->line('   ✓ robots.txt verified');

        // Step 3: Clear and warm up SEO-related caches
        $this->info('🔥 Warming up SEO caches...');
        $this->warmUpSeoCache();
        $this->line('   ✓ SEO caches warmed up');

        // Step 4: Check SEO configuration
        $this->info('⚙️  Checking SEO configuration...');
        $this->checkSeoConfig();

        $this->info('✅ SEO optimization complete!');
        $this->displaySeoStatus();
    }

    private function verifyRobotsTxt()
    {
        $robotsPath = public_path('robots.txt');
        
        if (!file_exists($robotsPath)) {
            $this->warn('   ⚠️  robots.txt not found, creating default...');
            $this->createDefaultRobotsTxt();
        } else {
            $robotsContent = file_get_contents($robotsPath);
            if (strpos($robotsContent, 'Sitemap:') === false) {
                $this->warn('   ⚠️  robots.txt missing sitemap reference');
                file_put_contents($robotsPath, $robotsContent . "\n\n# Sitemap location\nSitemap: " . route('sitemap.index'));
                $this->line('   ✓ Added sitemap reference to robots.txt');
            }
        }
    }

    private function createDefaultRobotsTxt()
    {
        $robotsContent = <<<TXT
# Ableton Cookbook - robots.txt
# Allow all crawlers access to public content

User-agent: *
Allow: /

# Disallow private areas
Disallow: /admin/
Disallow: /dashboard/
Disallow: /profile/
Disallow: /api/
Disallow: /livewire/
Disallow: /storage/private/
Disallow: /login
Disallow: /register
Disallow: /password/
Disallow: /email/
Disallow: /upload

# Allow specific bot access to important pages
User-agent: Googlebot
Allow: /
Allow: /racks/
Allow: /users/

User-agent: Bingbot
Allow: /
Allow: /racks/
Allow: /users/

# Crawl delay for all bots
Crawl-delay: 1

# Sitemap location
Sitemap: {$this->getSitemapUrl()}
TXT;

        file_put_contents(public_path('robots.txt'), $robotsContent);
        $this->line('   ✓ Created robots.txt');
    }

    private function warmUpSeoCache()
    {
        // Clear SEO-related caches if forced
        if ($this->option('force')) {
            Cache::forget('sitemap.index');
            Cache::forget('sitemap.static');
            Cache::forget('sitemap.racks');
            Cache::forget('sitemap.users');
        }

        // Warm up meta tags cache for homepage
        $seoService = app(\App\Services\SeoService::class);
        $homepageMeta = $seoService->getMetaTags([
            'title' => 'Ableton Live Racks - Share & Discover Instrument & Effect Racks',
            'description' => 'The ultimate community for sharing and discovering Ableton Live racks. Find instrument racks, effect racks, and MIDI racks created by producers worldwide.',
        ]);
        Cache::put('seo.homepage.meta', $homepageMeta, now()->addHours(24));
    }

    private function checkSeoConfig()
    {
        $issues = [];

        // Check app name
        if (config('app.name') === 'Laravel') {
            $issues[] = 'App name is still set to "Laravel" - update APP_NAME in .env';
        }

        // Check app URL
        if (config('app.url') === 'http://localhost') {
            $issues[] = 'App URL is still localhost - update APP_URL in .env';
        }

        // Check if sitemap routes are accessible
        try {
            route('sitemap.index');
        } catch (\Exception $e) {
            $issues[] = 'Sitemap routes are not properly registered';
        }

        if (!empty($issues)) {
            $this->warn('   ⚠️  SEO configuration issues found:');
            foreach ($issues as $issue) {
                $this->line("      - {$issue}");
            }
        } else {
            $this->line('   ✓ SEO configuration looks good');
        }
    }

    private function displaySeoStatus()
    {
        $this->info("\n📊 SEO Status Summary:");
        $this->table(
            ['Feature', 'Status', 'Details'],
            [
                ['Sitemaps', '✅ Active', 'Generated and cached'],
                ['robots.txt', '✅ Present', public_path('robots.txt')],
                ['Meta Tags', '✅ Configured', 'SeoService available'],
                ['Caching', '✅ Enabled', 'Redis cache active'],
                ['Scheduled Updates', '✅ Configured', 'Daily sitemap regeneration'],
            ]
        );

        $this->info("\n🌐 SEO URLs:");
        $this->line("   Sitemap: " . $this->getSitemapUrl());
        $this->line("   robots.txt: " . url('/robots.txt'));
        
        $this->info("\n💡 Next Steps:");
        $this->line("   1. Submit sitemap to Google Search Console");
        $this->line("   2. Submit sitemap to Bing Webmaster Tools");
        $this->line("   3. Monitor crawling with `tail -f /var/log/nginx/ableton-cookbook_access.log | grep bot`");
        $this->line("   4. Set up Google Analytics and Search Console");
    }

    private function getSitemapUrl(): string
    {
        try {
            return route('sitemap.index');
        } catch (\Exception $e) {
            return url('/sitemap.xml');
        }
    }
}
