<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Rack;
use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\RackDownload;
use App\Models\RackRating;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Services\AdminAnalyticsService;
use App\Services\RackAnalyticsService;
use App\Services\EmailAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

/**
 * Comprehensive Integration Tests for Admin Dashboard System
 * 
 * Tests the full integration between backend analytics services, 
 * controllers, frontend interface, and security middleware.
 */
class AdminDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $regularUser;
    protected AdminAnalyticsService $adminAnalytics;
    protected RackAnalyticsService $rackAnalytics;
    protected EmailAnalyticsService $emailAnalytics;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        $this->regularUser = User::factory()->create();
        
        // Initialize services
        $this->adminAnalytics = app(AdminAnalyticsService::class);
        $this->rackAnalytics = app(RackAnalyticsService::class);
        $this->emailAnalytics = app(EmailAnalyticsService::class);
        
        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function admin_middleware_properly_restricts_access()
    {
        // Test unauthenticated access
        $response = $this->get('/admin/analytics/');
        $response->assertRedirect('/login');
        
        // Test regular user access
        $response = $this->actingAs($this->regularUser)
                        ->get('/admin/analytics/');
        $response->assertStatus(Response::HTTP_FORBIDDEN);
        
        // Test admin user access
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
        $response->assertSuccessful();
    }

    /** @test */
    public function enhanced_dashboard_loads_with_proper_data_structure()
    {
        // Create test data
        $this->createTestData();
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $response->assertSuccessful();
        $response->assertViewHas([
            'stats',
            'charts', 
            'recentActivity',
            'topPerformers',
            'issues',
            'healthMetrics',
            'rackStats',
            'emailStats'
        ]);
        
        // Verify data structure integrity
        $stats = $response->viewData('stats');
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('total_racks', $stats);
        $this->assertArrayHasKey('total_downloads', $stats);
        $this->assertArrayHasKey('user_growth_rate', $stats);
        $this->assertArrayHasKey('engagement_score', $stats);
    }

    /** @test */
    public function dashboard_api_endpoint_returns_proper_json_structure()
    {
        $this->createTestData();
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/api');
                        
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'overview' => [
                    'users',
                    'racks',
                    'engagement',
                    'content',
                    'system'
                ],
                'racks',
                'email',
                'health',
                'timestamp'
            ]
        ]);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data['overview']['users']['total']);
        $this->assertNotEmpty($data['timestamp']);
    }

    /** @test */
    public function analytics_sections_load_with_proper_data()
    {
        $this->createTestData();
        
        $sections = ['overview', 'racks', 'email', 'user-analytics', 'content-analytics', 'health'];
        
        foreach ($sections as $section) {
            $response = $this->actingAs($this->adminUser)
                            ->get("/admin/analytics/section/{$section}");
                            
            $response->assertSuccessful();
            $response->assertJsonStructure([
                'success',
                'data',
                'section',
                'timestamp'
            ]);
            
            $this->assertEquals($section, $response->json('section'));
        }
    }

    /** @test */
    public function real_time_endpoint_provides_live_metrics()
    {
        $this->createTestData();
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/real-time');
                        
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'active_users',
                'processing_queue',
                'system_health',
                'recent_activities',
                'alerts',
                'performance',
                'timestamp'
            ]
        ]);
        
        $data = $response->json('data');
        $this->assertIsInt($data['active_users']);
        $this->assertIsArray($data['processing_queue']);
        $this->assertNotEmpty($data['timestamp']);
    }

    /** @test */
    public function admin_analytics_service_calculates_accurate_statistics()
    {
        // Create specific test data with known values
        $startDate = Carbon::now()->subDays(30);
        
        // Create users
        User::factory()->count(10)->create(['created_at' => $startDate->copy()->addDays(10)]);
        User::factory()->count(5)->create(['created_at' => Carbon::now()->subDays(7)]);
        
        // Create racks
        $racks = Rack::factory()->count(15)->create(['created_at' => $startDate->copy()->addDays(15)]);
        Rack::factory()->count(8)->create(['created_at' => Carbon::now()->subDays(5)]);
        
        // Create downloads
        foreach ($racks->take(10) as $rack) {
            RackDownload::factory()->count(3)->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(1, 25))
            ]);
        }
        
        $stats = $this->adminAnalytics->getOverviewStats();
        
        // Verify calculations
        $this->assertEquals(17, $stats['users']['total']); // 10 + 5 + 2 from setup
        $this->assertEquals(25, $stats['racks']['total']); // 15 + 8 + 2 from setup
        $this->assertGreaterThan(0, $stats['engagement']['total_downloads']);
        $this->assertIsFloat($stats['users']['growth_rate']);
    }

    /** @test */
    public function dashboard_handles_large_dataset_performance()
    {
        // Create large dataset
        User::factory()->count(1000)->create();
        $racks = Rack::factory()->count(500)->create();
        BlogPost::factory()->count(100)->create();
        
        // Create many downloads and ratings
        foreach ($racks->take(100) as $rack) {
            RackDownload::factory()->count(rand(1, 50))->create(['rack_id' => $rack->id]);
            RackRating::factory()->count(rand(1, 10))->create(['rack_id' => $rack->id]);
        }
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $loadTime = microtime(true) - $startTime;
        
        $response->assertSuccessful();
        
        // Dashboard should load in under 2 seconds even with large dataset
        $this->assertLessThan(2.0, $loadTime, "Dashboard took {$loadTime}s to load, should be under 2s");
    }

    /** @test */
    public function dashboard_caching_works_correctly()
    {
        $this->createTestData();
        
        // First request should hit database
        $response1 = $this->actingAs($this->adminUser)
                         ->get('/admin/analytics/api');
        $response1->assertSuccessful();
        
        // Verify cache was populated
        $this->assertTrue(Cache::has('admin.analytics.overview'));
        
        // Second request should use cache
        $startTime = microtime(true);
        $response2 = $this->actingAs($this->adminUser)
                         ->get('/admin/analytics/api');
        $cacheLoadTime = microtime(true) - $startTime;
        
        $response2->assertSuccessful();
        
        // Cached response should be much faster
        $this->assertLessThan(0.1, $cacheLoadTime, "Cached response took {$cacheLoadTime}s, should be under 0.1s");
        
        // Data should be identical
        $this->assertEquals(
            $response1->json('data.overview.users.total'),
            $response2->json('data.overview.users.total')
        );
    }

    /** @test */
    public function export_functionality_works_correctly()
    {
        $this->createTestData();
        
        $response = $this->actingAs($this->adminUser)
                        ->post('/admin/analytics/export', [
                            'sections' => ['overview', 'racks', 'email'],
                            'format' => 'json',
                            'days' => 30
                        ]);
                        
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'overview',
                'racks',
                'email',
                '_metadata' => [
                    'generated_at',
                    'period_days',
                    'format',
                    'sections'
                ]
            ]
        ]);
        
        $metadata = $response->json('data._metadata');
        $this->assertEquals(30, $metadata['period_days']);
        $this->assertEquals('json', $metadata['format']);
        $this->assertContains('overview', $metadata['sections']);
    }

    /** @test */
    public function error_handling_works_gracefully()
    {
        // Test invalid section
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/section/invalid-section');
                        
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
        $this->assertFalse($response->json('success'));
        
        // Test database connection failure scenario
        DB::disconnect();
        
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/api');
                        
        // Should handle gracefully (may succeed or fail depending on implementation)
        $this->assertTrue($response->status() < 500, 'Should not return server error');
    }

    /** @test */
    public function security_headers_are_present()
    {
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/');
                        
        $response->assertSuccessful();
        
        // Should have basic security headers
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
    }

    /** @test */
    public function csrf_protection_is_enforced()
    {
        $response = $this->actingAs($this->adminUser)
                        ->post('/admin/analytics/export', [], [
                            'Accept' => 'application/json'
                        ]);
                        
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function time_series_data_structure_is_correct()
    {
        $this->createTimeSeriesTestData();
        
        $charts = $this->adminAnalytics->getTimeSeriesData(7);
        
        $this->assertIsArray($charts);
        $this->assertArrayHasKey('dates', $charts);
        $this->assertArrayHasKey('users', $charts);
        $this->assertArrayHasKey('racks', $charts);
        $this->assertArrayHasKey('downloads', $charts);
        $this->assertArrayHasKey('comments', $charts);
        
        // Should have 7 days of data
        $this->assertCount(7, $charts['dates']);
        $this->assertCount(7, $charts['users']);
        $this->assertCount(7, $charts['racks']);
    }

    /** @test */
    public function health_metrics_detect_system_issues()
    {
        $healthMetrics = $this->adminAnalytics->getHealthMetrics();
        
        $this->assertArrayHasKey('database', $healthMetrics);
        $this->assertArrayHasKey('queue', $healthMetrics);
        $this->assertArrayHasKey('cache', $healthMetrics);
        $this->assertArrayHasKey('storage', $healthMetrics);
        $this->assertArrayHasKey('performance', $healthMetrics);
        
        // Database health should be measurable
        $this->assertArrayHasKey('status', $healthMetrics['database']);
        $this->assertContains($healthMetrics['database']['status'], ['healthy', 'unhealthy']);
    }

    /** @test */
    public function alerts_system_functions_correctly()
    {
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/alerts');
                        
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'critical',
                'warnings',
                'info'
            ]
        ]);
        
        $alerts = $response->json('data');
        $this->assertIsArray($alerts['critical']);
        $this->assertIsArray($alerts['warnings']);
        $this->assertIsArray($alerts['info']);
    }

    /** @test */
    public function benchmarks_provide_performance_insights()
    {
        $response = $this->actingAs($this->adminUser)
                        ->get('/admin/analytics/benchmarks');
                        
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'response_times',
                'database_performance',
                'cache_performance',
                'queue_performance',
                'recommendations'
            ]
        ]);
        
        $benchmarks = $response->json('data');
        $this->assertIsArray($benchmarks['recommendations']);
        $this->assertArrayHasKey('current', $benchmarks['response_times']);
    }

    /** @test */
    public function mobile_responsive_design_elements_load()
    {
        // Test with mobile user agent
        $response = $this->actingAs($this->adminUser)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1'
                        ])
                        ->get('/admin/analytics/');
                        
        $response->assertSuccessful();
        
        // Should contain responsive grid classes
        $response->assertSee('grid-cols-1');
        $response->assertSee('md:grid-cols-2');
        $response->assertSee('lg:grid-cols-4');
        
        // Should have mobile-friendly navigation
        $response->assertSee('space-x-8');
    }

    /**
     * Create comprehensive test data for analytics testing
     */
    protected function createTestData(): void
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        // Create users across different time periods
        User::factory()->count(5)->create(['created_at' => $thirtyDaysAgo, 'last_login_at' => Carbon::now()]);
        User::factory()->count(3)->create(['created_at' => $sevenDaysAgo, 'last_login_at' => Carbon::now()->subHours(2)]);
        
        // Create racks with various states
        $racks = Rack::factory()->count(10)->create([
            'created_at' => $thirtyDaysAgo,
            'is_public' => true,
            'approved_at' => $thirtyDaysAgo
        ]);
        
        Rack::factory()->count(5)->create([
            'created_at' => $sevenDaysAgo,
            'is_public' => true,
            'approved_at' => $sevenDaysAgo
        ]);
        
        Rack::factory()->count(3)->create([
            'created_at' => Carbon::now()->subDays(2),
            'is_public' => false,
            'approved_at' => null
        ]);
        
        // Create engagement data
        foreach ($racks->take(8) as $rack) {
            RackDownload::factory()->count(rand(5, 20))->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(1, 25))
            ]);
            
            RackRating::factory()->count(rand(1, 8))->create([
                'rack_id' => $rack->id,
                'rating' => rand(3, 5)
            ]);
            
            Comment::factory()->count(rand(0, 5))->create([
                'rack_id' => $rack->id,
                'created_at' => Carbon::now()->subDays(rand(1, 20))
            ]);
        }
        
        // Create blog posts
        BlogPost::factory()->count(5)->create([
            'published_at' => $thirtyDaysAgo,
            'is_published' => true
        ]);
        
        BlogPost::factory()->count(3)->create([
            'published_at' => null,
            'is_published' => false
        ]);
        
        // Create issues
        Issue::factory()->count(3)->create([
            'status' => 'pending',
            'priority' => 'normal'
        ]);
        
        Issue::factory()->create([
            'status' => 'pending',
            'priority' => 'urgent'
        ]);
        
        Issue::factory()->count(2)->create([
            'status' => 'resolved'
        ]);
        
        // Create newsletter subscribers
        Newsletter::factory()->count(50)->create([
            'status' => 'active'
        ]);
        
        Newsletter::factory()->count(5)->create([
            'status' => 'unsubscribed'
        ]);
    }

    /**
     * Create time series test data for chart testing
     */
    protected function createTimeSeriesTestData(): void
    {
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // Create users on each day
            User::factory()->count(rand(1, 5))->create(['created_at' => $date]);
            
            // Create racks on each day
            Rack::factory()->count(rand(0, 3))->create(['created_at' => $date]);
            
            // Create downloads on each day
            if ($i < 5) { // Only for recent days
                RackDownload::factory()->count(rand(5, 15))->create(['created_at' => $date]);
            }
            
            // Create comments on each day
            if ($i < 4) { // Only for very recent days
                Comment::factory()->count(rand(1, 8))->create(['created_at' => $date]);
            }
        }
    }
}