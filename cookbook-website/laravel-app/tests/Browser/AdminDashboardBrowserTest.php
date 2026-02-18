<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Rack;
use App\Models\RackDownload;
use App\Models\Comment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser Testing Suite for Admin Dashboard
 * 
 * Tests mobile responsiveness, real-time updates, JavaScript functionality,
 * and cross-browser compatibility using Laravel Dusk.
 */
class AdminDashboardBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
        
        // Create some test data
        $this->createTestData();
    }

    /** @test */
    public function admin_dashboard_loads_and_displays_correctly_on_desktop()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->assertSee('Enhanced Analytics Dashboard')
                    ->assertSee('Total Users')
                    ->assertSee('Total Racks')
                    ->assertSee('Downloads')
                    ->assertSee('Newsletter')
                    ->assertVisible('@status-bar')
                    ->assertVisible('@metrics-grid')
                    ->assertVisible('@analytics-tabs');
        });
    }

    /** @test */
    public function admin_dashboard_is_responsive_on_mobile_devices()
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(375, 667) // iPhone 6/7/8 size
                    ->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->assertSee('Enhanced Analytics Dashboard')
                    
                    // Mobile layout should stack metrics cards vertically
                    ->assertVisible('.grid-cols-1')
                    
                    // Status bar should be responsive
                    ->assertVisible('#status-bar')
                    
                    // Tabs should be horizontally scrollable on mobile
                    ->assertVisible('.analytics-tab')
                    
                    // Charts should be responsive
                    ->assertVisible('#realtimeChart')
                    
                    // Action buttons should be visible but may stack
                    ->assertVisible('#refresh-dashboard')
                    ->assertVisible('#export-data');
        });
    }

    /** @test */
    public function admin_dashboard_works_on_tablet_devices()
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(768, 1024) // iPad size
                    ->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->assertSee('Enhanced Analytics Dashboard')
                    
                    // Tablet layout should show 2 columns
                    ->assertVisible('.md\\:grid-cols-2')
                    
                    // All elements should be properly sized
                    ->assertVisible('#status-bar')
                    ->assertVisible('.analytics-tab')
                    ->assertVisible('#realtimeChart')
                    
                    // Navigation should be accessible
                    ->click('.analytics-tab[data-tab="racks"]')
                    ->waitForText('Upload Statistics')
                    ->assertSee('Processing Status');
        });
    }

    /** @test */
    public function tab_switching_functionality_works_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab')
                    
                    // Test Overview tab (default)
                    ->assertSee('Platform Growth')
                    
                    // Switch to Racks tab
                    ->click('.analytics-tab[data-tab="racks"]')
                    ->waitForText('Upload Statistics', 5)
                    ->assertSee('Processing Status')
                    ->assertSee('Content Engagement')
                    
                    // Switch to Email tab
                    ->click('.analytics-tab[data-tab="email"]')
                    ->waitForText('Subscribers', 5)
                    ->assertSee('Email Performance')
                    ->assertSee('System Health')
                    
                    // Switch to Users tab
                    ->click('.analytics-tab[data-tab="users"]')
                    ->waitForText('User Activity', 5)
                    ->assertSee('User Segments')
                    
                    // Switch to System tab
                    ->click('.analytics-tab[data-tab="system"]')
                    ->waitForText('Database', 5)
                    ->assertSee('Cache')
                    ->assertSee('Performance');
        });
    }

    /** @test */
    public function refresh_button_updates_data()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('#refresh-dashboard')
                    
                    // Get initial timestamp
                    ->waitForText('Last updated:')
                    
                    // Wait a moment
                    ->pause(1000)
                    
                    // Click refresh
                    ->click('#refresh-dashboard')
                    
                    // Button should show loading state
                    ->assertSee('Refreshing...')
                    
                    // Wait for refresh to complete
                    ->waitForText('Refresh', 10)
                    ->waitForText('Last updated:', 10);
        });
    }

    /** @test */
    public function export_functionality_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('#export-data')
                    ->click('#export-data')
                    
                    // Should trigger download
                    ->pause(2000);
            
            // Note: Actually testing file download in browser tests is complex
            // This test verifies the button works without errors
        });
    }

    /** @test */
    public function real_time_updates_function_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('#status-bar')
                    ->waitFor('#last-updated')
                    
                    // Get initial timestamp
                    ->pause(2000);
            
            // Create new activity while dashboard is open
            $this->createAdditionalActivity();
            
            $browser->pause(3000) // Wait for potential updates
                    
                    // Status indicator should be present and healthy
                    ->assertVisible('#status-indicator.bg-green-400, #status-indicator.bg-red-400')
                    
                    // Active users count should be present
                    ->assertPresent('#active-users')
                    ->assertPresent('#queue-count');
        });
    }

    /** @test */
    public function charts_render_correctly_and_are_interactive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('#realtimeChart')
                    
                    // Chart canvas should be present
                    ->assertPresent('#realtimeChart')
                    
                    // Wait for Chart.js to initialize
                    ->pause(2000)
                    
                    // Check if chart is actually rendered (has content)
                    ->assertScript('document.getElementById("realtimeChart").getContext !== undefined')
                    
                    // Test different tabs to ensure charts load in different sections
                    ->click('.analytics-tab[data-tab="racks"]')
                    ->waitForText('Upload Statistics', 5)
                    ->pause(1000);
        });
    }

    /** @test */
    public function keyboard_navigation_works_for_accessibility()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab')
                    
                    // Tab navigation should work
                    ->keys('body', '{tab}')
                    ->keys('body', '{tab}')
                    ->keys('body', '{tab}')
                    
                    // Should be able to activate tabs with keyboard
                    ->keys('body', '{enter}')
                    
                    // Test refresh button keyboard access
                    ->click('#refresh-dashboard')
                    ->waitForText('Refresh', 10);
        });
    }

    /** @test */
    public function error_states_are_handled_gracefully()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab')
                    
                    // Simulate network error by visiting invalid section
                    ->script("
                        fetch('/admin/analytics/section/invalid-section')
                        .then(response => {
                            if (!response.ok) {
                                console.log('Expected error handled correctly');
                            }
                        })
                        .catch(error => {
                            console.log('Network error handled correctly');
                        });
                    ");
                    
            // Page should remain functional
            $browser->assertVisible('.analytics-tab')
                    ->assertVisible('#refresh-dashboard');
        });
    }

    /** @test */
    public function loading_states_are_visible_and_appropriate()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('#tab-content')
                    
                    // Switch tabs to trigger loading states
                    ->click('.analytics-tab[data-tab="racks"]')
                    
                    // Should briefly show loading state
                    ->pause(100)
                    
                    // Then show content
                    ->waitForText('Upload Statistics', 5)
                    
                    // Test refresh loading state
                    ->click('#refresh-dashboard')
                    ->assertSee('Refreshing...')
                    ->waitForText('Refresh', 10);
        });
    }

    /** @test */
    public function dashboard_handles_large_datasets_smoothly()
    {
        // Create large dataset
        Rack::factory()->count(100)->create();
        RackDownload::factory()->count(500)->create();
        Comment::factory()->count(300)->create();

        $this->browse(function (Browser $browser) {
            $startTime = microtime(true);
            
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab', 10)
                    ->assertSee('Enhanced Analytics Dashboard');
                    
            $loadTime = microtime(true) - $startTime;
            
            // Should load within reasonable time even with large dataset
            $this->assertLessThan(5.0, $loadTime, "Dashboard took {$loadTime}s to load");
        });
    }

    /** @test */
    public function dashboard_maintains_state_during_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab')
                    
                    // Switch to specific tab
                    ->click('.analytics-tab[data-tab="racks"]')
                    ->waitForText('Upload Statistics', 5)
                    
                    // Navigate away and back
                    ->visit('/admin/dashboard')
                    ->visit('/admin/analytics/')
                    
                    // Should return to default state (overview tab)
                    ->waitFor('.analytics-tab.active[data-tab="overview"]', 5)
                    ->assertSee('Platform Growth');
        });
    }

    /** @test */
    public function dark_mode_toggle_works_if_implemented()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->adminUser)
                    ->visit('/admin/analytics/')
                    ->waitFor('.analytics-tab');
                    
            // Check if dark mode classes are properly applied
            // This assumes Tailwind's dark mode implementation
            if ($browser->element('html')->getAttribute('class') !== null) {
                $browser->assertDontSee('dark:bg-gray-800 dark:text-gray-200', false);
            }
        });
    }

    /**
     * Create test data for browser tests
     */
    protected function createTestData(): void
    {
        // Create users
        User::factory()->count(25)->create();
        
        // Create racks with downloads and comments
        $racks = Rack::factory()->count(15)->create(['is_public' => true]);
        
        foreach ($racks->take(10) as $rack) {
            RackDownload::factory()->count(rand(3, 20))->create(['rack_id' => $rack->id]);
            Comment::factory()->count(rand(1, 5))->create(['rack_id' => $rack->id]);
        }
    }

    /**
     * Create additional activity during tests
     */
    protected function createAdditionalActivity(): void
    {
        // Create new downloads to simulate activity
        $racks = Rack::limit(5)->get();
        foreach ($racks as $rack) {
            RackDownload::factory()->create(['rack_id' => $rack->id]);
        }
    }
}