<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Rack;
use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\RackDownload;
use App\Models\RackRating;
use App\Services\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

/**
 * Performance Testing Suite for Admin Dashboard
 * 
 * Tests dashboard performance under various load conditions,
 * validates caching strategies, and ensures sub-2 second load times.
 */
class AdminDashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected AdminAnalyticsService $adminAnalytics;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        $this->adminAnalytics = app(AdminAnalyticsService::class);
        
        Cache::flush();
    }

    /** @test */
    public function dashboard_loads_within_performance_target_with_minimal_data()
    {
        // Create minimal test data
        User::factory()->count(10)->create();
        Rack::factory()->count(20)->create();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $loadTime = microtime(true) - $startTime;
        
        $response->assertSuccessful();
        
        // Should load in under 1 second with minimal data
        $this->assertLessThan(1.0, $loadTime, "Dashboard took {$loadTime}s to load, should be under 1s with minimal data");
    }

    /** @test */
    public function dashboard_loads_within_performance_target_with_medium_dataset()
    {
        // Create medium dataset (realistic production load)
        $this->createMediumDataset();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $loadTime = microtime(true) - $startTime;
        
        $response->assertSuccessful();
        
        // Should load in under 1.5 seconds with medium data
        $this->assertLessThan(1.5, $loadTime, "Dashboard took {$loadTime}s to load, should be under 1.5s with medium data");
    }

    /** @test */
    public function dashboard_loads_within_performance_target_with_large_dataset()
    {
        // Create large dataset (stress test)
        $this->createLargeDataset();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $loadTime = microtime(true) - $startTime;
        
        $response->assertSuccessful();
        
        // Should load in under 2 seconds even with large data
        $this->assertLessThan(2.0, $loadTime, "Dashboard took {$loadTime}s to load, should be under 2s even with large data");
    }

    /** @test */
    public function caching_provides_significant_performance_improvement()
    {
        $this->createMediumDataset();
        
        // First request - cache miss
        $startTime1 = microtime(true);
        $response1 = $this->actingAs($this->adminUser)
                         ->get('/admin/analytics/api');
        $uncachedTime = microtime(true) - $startTime1;
        
        $response1->assertSuccessful();
        
        // Second request - cache hit
        $startTime2 = microtime(true);
        $response2 = $this->actingAs($this->adminUser)
                         ->get('/admin/analytics/api');
        $cachedTime = microtime(true) - $startTime2;
        
        $response2->assertSuccessful();
        
        // Cached response should be at least 2x faster
        $improvement = $uncachedTime / max($cachedTime, 0.001); // Avoid division by zero
        
        $this->assertGreaterThan(2.0, $improvement, 
            "Cache improvement was {$improvement}x, should be at least 2x (uncached: {$uncachedTime}s, cached: {$cachedTime}s)");
        
        // Cached response should be very fast
        $this->assertLessThan(0.2, $cachedTime, "Cached response took {$cachedTime}s, should be under 0.2s");
    }

    /** @test */
    public function analytics_service_queries_are_optimized()
    {
        $this->createMediumDataset();
        
        // Count queries before
        $startQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::enableQueryLog();
        
        $stats = $this->adminAnalytics->getOverviewStats();
        
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        
        // Should not make excessive queries
        $this->assertLessThan(20, $queryCount, "Made {$queryCount} queries, should be under 20 for overview stats");
        
        // Should return proper data structure
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('racks', $stats);
        $this->assertArrayHasKey('engagement', $stats);
    }

    /** @test */
    public function time_series_data_generation_is_performant()
    {
        $this->createTimeSeriesData();
        
        foreach ([7, 30, 90] as $days) {
            $startTime = microtime(true);
            
            $timeSeriesData = $this->adminAnalytics->getTimeSeriesData($days);
            
            $generationTime = microtime(true) - $startTime;
            
            $this->assertLessThan(0.5, $generationTime, 
                "Time series generation for {$days} days took {$generationTime}s, should be under 0.5s");
            
            $this->assertCount($days, $timeSeriesData['dates']);
            $this->assertCount($days, $timeSeriesData['users']);
            $this->assertCount($days, $timeSeriesData['racks']);
        }
    }

    /** @test */
    public function concurrent_requests_handle_gracefully()
    {
        $this->createMediumDataset();
        
        $responses = [];
        $startTime = microtime(true);
        
        // Simulate 5 concurrent requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->adminUser)
                            ->get('/admin/analytics/api');
            $responses[] = $response;
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertSuccessful();
        }
        
        // Should handle concurrent requests efficiently
        $this->assertLessThan(3.0, $totalTime, "5 concurrent requests took {$totalTime}s, should be under 3s");
    }

    /** @test */
    public function memory_usage_stays_within_acceptable_limits()
    {
        $this->createLargeDataset();
        
        $startMemory = memory_get_usage(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        $response->assertSuccessful();
        
        // Should not use excessive memory
        $this->assertLessThan(64, $memoryUsed, "Used {$memoryUsed}MB of memory, should be under 64MB");
    }

    /** @test */
    public function database_connection_pooling_works_efficiently()
    {
        $this->createMediumDataset();
        
        // Make multiple requests that would require database access
        $requests = [
            '/admin/analytics/section/overview',
            '/admin/analytics/section/racks',
            '/admin/analytics/section/email',
            '/admin/analytics/section/user-analytics',
            '/admin/analytics/real-time'
        ];
        
        $totalTime = 0;
        
        foreach ($requests as $url) {
            $startTime = microtime(true);
            
            $response = $this->actingAs($this->adminUser)->get($url);
            
            $requestTime = microtime(true) - $startTime;
            $totalTime += $requestTime;
            
            $response->assertSuccessful();
            
            // Each request should be reasonably fast
            $this->assertLessThan(0.8, $requestTime, 
                "Request to {$url} took {$requestTime}s, should be under 0.8s");
        }
        
        // Total time for all requests should be reasonable
        $this->assertLessThan(3.0, $totalTime, 
            "All requests took {$totalTime}s total, should be under 3s");
    }

    /** @test */
    public function large_result_sets_are_paginated_or_limited()
    {
        // Create many items that could cause large result sets
        User::factory()->count(2000)->create();
        $racks = Rack::factory()->count(1000)->create();
        
        // Create many downloads
        foreach ($racks->random(100) as $rack) {
            RackDownload::factory()->count(50)->create(['rack_id' => $rack->id]);
        }
        
        $startTime = microtime(true);
        
        $topPerformers = $this->adminAnalytics->getTopPerformers();
        
        $queryTime = microtime(true) - $startTime;
        
        // Should complete quickly even with large datasets
        $this->assertLessThan(1.0, $queryTime, 
            "Top performers query took {$queryTime}s, should be under 1s");
        
        // Results should be limited to reasonable sizes
        $this->assertLessThanOrEqual(10, count($topPerformers['racks_by_downloads'] ?? []));
        $this->assertLessThanOrEqual(10, count($topPerformers['users_by_uploads'] ?? []));
    }

    /** @test */
    public function health_metrics_complete_quickly()
    {
        $startTime = microtime(true);
        
        $healthMetrics = $this->adminAnalytics->getHealthMetrics();
        
        $queryTime = microtime(true) - $startTime;
        
        // Health checks should be very fast
        $this->assertLessThan(0.5, $queryTime, 
            "Health metrics took {$queryTime}s, should be under 0.5s");
        
        $this->assertIsArray($healthMetrics);
        $this->assertArrayHasKey('database', $healthMetrics);
        $this->assertArrayHasKey('cache', $healthMetrics);
        $this->assertArrayHasKey('performance', $healthMetrics);
    }

    /** @test */
    public function export_functionality_handles_large_datasets_efficiently()
    {
        $this->createLargeDataset();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->post('/admin/analytics/export', [
                            'sections' => ['overview', 'racks', 'email'],
                            'format' => 'json',
                            'days' => 30
                        ]);
                        
        $exportTime = microtime(true) - $startTime;
        
        $response->assertSuccessful();
        
        // Export should complete in reasonable time
        $this->assertLessThan(3.0, $exportTime, 
            "Export took {$exportTime}s, should be under 3s");
        
        $data = $response->json('data');
        $this->assertArrayHasKey('_metadata', $data);
        $this->assertArrayHasKey('overview', $data);
    }

    /**
     * Create medium-sized dataset for realistic performance testing
     */
    protected function createMediumDataset(): void
    {
        // Users - simulate 6 months of growth
        $baseDate = Carbon::now()->subMonths(6);
        for ($i = 0; $i < 180; $i++) { // 6 months
            $date = $baseDate->copy()->addDays($i);
            $dailyUsers = rand(1, 5); // 1-5 users per day average
            
            User::factory()->count($dailyUsers)->create([
                'created_at' => $date,
                'last_login_at' => Carbon::now()->subDays(rand(0, 30))
            ]);
        }
        
        // Racks - 500 total racks
        $racks = Rack::factory()->count(500)->create([
            'created_at' => $baseDate->copy()->addDays(rand(0, 180)),
            'is_public' => true
        ]);
        
        // Downloads - realistic engagement
        foreach ($racks->random(300) as $rack) {
            $downloadCount = rand(1, 50);
            RackDownload::factory()->count($downloadCount)->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(0, 180))
            ]);
        }
        
        // Ratings
        foreach ($racks->random(200) as $rack) {
            RackRating::factory()->count(rand(1, 20))->create([
                'rack_id' => $rack->id,
                'rating' => rand(3, 5)
            ]);
        }
        
        // Comments
        foreach ($racks->random(150) as $rack) {
            Comment::factory()->count(rand(1, 10))->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(0, 180))
            ]);
        }
        
        // Blog posts
        BlogPost::factory()->count(50)->create([
            'created_at' => $baseDate->copy()->addDays(rand(0, 180)),
            'published_at' => Carbon::now()->subDays(rand(0, 60))
        ]);
    }

    /**
     * Create large dataset for stress testing
     */
    protected function createLargeDataset(): void
    {
        // Large user base - 5000 users
        User::factory()->count(5000)->create([
            'created_at' => Carbon::now()->subDays(rand(0, 365)),
            'last_login_at' => Carbon::now()->subDays(rand(0, 90))
        ]);
        
        // Large rack collection - 2000 racks
        $racks = Rack::factory()->count(2000)->create([
            'created_at' => Carbon::now()->subDays(rand(0, 365)),
            'is_public' => true
        ]);
        
        // High engagement - many downloads
        foreach ($racks->random(1000) as $rack) {
            $downloadCount = rand(1, 200);
            RackDownload::factory()->count($downloadCount)->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(0, 365))
            ]);
        }
        
        // Many ratings and comments
        foreach ($racks->random(800) as $rack) {
            RackRating::factory()->count(rand(5, 50))->create([
                'rack_id' => $rack->id,
                'rating' => rand(1, 5)
            ]);
            
            Comment::factory()->count(rand(1, 30))->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(0, 365))
            ]);
        }
        
        // Many blog posts
        BlogPost::factory()->count(200)->create([
            'created_at' => Carbon::now()->subDays(rand(0, 365)),
            'published_at' => Carbon::now()->subDays(rand(0, 180))
        ]);
    }

    /**
     * Create time series data for chart performance testing
     */
    protected function createTimeSeriesData(): void
    {
        for ($i = 90; $i >= 0; $i--) { // 90 days of data
            $date = Carbon::now()->subDays($i);
            
            // Simulate realistic daily growth
            $dailyUsers = rand(2, 10);
            $dailyRacks = rand(1, 8);
            $dailyDownloads = rand(10, 100);
            $dailyComments = rand(5, 30);
            
            User::factory()->count($dailyUsers)->create(['created_at' => $date]);
            Rack::factory()->count($dailyRacks)->create(['created_at' => $date]);
            RackDownload::factory()->count($dailyDownloads)->create(['created_at' => $date]);
            Comment::factory()->count($dailyComments)->create(['created_at' => $date]);
        }
    }
}