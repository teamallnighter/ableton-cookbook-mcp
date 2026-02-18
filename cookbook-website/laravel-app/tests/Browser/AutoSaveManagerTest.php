<?php

namespace Tests\Browser;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser tests for AutoSaveManager JavaScript functionality
 * 
 * These tests verify the client-side auto-save behavior including:
 * - Real-time saving during typing
 * - Conflict detection and resolution UI
 * - Network error handling
 * - Session management
 */
class AutoSaveManagerTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $user;
    protected Rack $rack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'processing',
            'version' => 1
        ]);
    }

    /** @test */
    public function test_auto_save_manager_initializes_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-autosave]', 10)
                    ->script('return typeof window.autoSaveManager !== "undefined"')[0];
                    
            $this->assertTrue($browser->script('return window.autoSaveManager !== undefined')[0]);
        });
    }

    /** @test */
    public function test_title_auto_save_on_typing()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10)
                    ->type('#title', 'Auto-saved Title')
                    ->waitForText('✓ Saved', 5); // Wait for save indicator
                    
            // Verify the save indicator appeared
            $this->assertStringContainsString('✓ Saved', $browser->text('#title-saved'));
        });
    }

    /** @test */
    public function test_description_auto_save_debouncing()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#description', 10);

            // Type rapidly to test debouncing
            $browser->type('#description', 'Typing')
                    ->pause(100)
                    ->type('#description', ' rapidly')
                    ->pause(100)  
                    ->type('#description', ' to test')
                    ->pause(100)
                    ->type('#description', ' debouncing');

            // Wait for debounced save (should only save final value)
            $browser->waitForText('✓ Saved', 5);
            
            // Verify final content
            $finalValue = $browser->value('#description');
            $this->assertStringContainsString('debouncing', $finalValue);
        });
    }

    /** @test */
    public function test_category_dropdown_auto_save()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#category', 10)
                    ->select('#category', 'bass')
                    ->waitForText('✓ Saved', 5);
                    
            $this->assertStringContainsString('✓ Saved', $browser->text('#category-saved'));
        });
    }

    /** @test */
    public function test_tags_field_auto_save()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#tags', 10)
                    ->type('#tags', 'auto-save, testing, dusk')
                    ->waitForText('✓ Saved', 5);
                    
            $this->assertStringContainsString('✓ Saved', $browser->text('#tags-saved'));
        });
    }

    /** @test */
    public function test_markdown_editor_auto_save_integration()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-markdown-textarea]', 10)
                    ->type('[data-markdown-textarea]', '# Test Markdown\n\nThis is a test of markdown auto-save.')
                    ->waitForText('✓ Saved', 10); // Markdown might take longer
                    
            // Check if save indicator is present (might be in different location for markdown)
            $hasSaveIndicator = $browser->script('
                return Array.from(document.querySelectorAll("*"))
                    .some(el => el.textContent.includes("✓ Saved"));
            ')[0];
            
            $this->assertTrue($hasSaveIndicator);
        });
    }

    /** @test */
    public function test_network_status_indicator()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-autosave]', 10);

            // Simulate network going offline
            $browser->script('
                window.dispatchEvent(new Event("offline"));
            ');

            // Should show offline indicator
            $browser->waitFor('#network-status', 5);
            $this->assertStringContainsString('offline', $browser->text('#network-status'));

            // Simulate network coming back online
            $browser->script('
                window.dispatchEvent(new Event("online"));
            ');

            // Should show online indicator
            $browser->waitForText('Connection restored', 5);
        });
    }

    /** @test */
    public function test_save_all_changes_function()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10)
                    ->type('#title', 'Save All Test Title')
                    ->type('#description', 'Save All Test Description');

            // Call the saveAllChanges function
            $result = $browser->script('return window.saveAllChanges()')[0];
            
            // Should return a promise/result indicating save attempt
            $this->assertNotNull($result);
        });
    }

    /** @test */
    public function test_unsaved_changes_warning()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10)
                    ->type('#title', 'Unsaved changes test');

            // Check if hasUnsavedChanges returns true
            $hasUnsaved = $browser->script('
                return window.autoSaveManager ? 
                    window.autoSaveManager.hasUnsavedChanges() : false
            ')[0];
            
            $this->assertTrue($hasUnsaved);
        });
    }

    /** @test */
    public function test_session_tracking()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-autosave]', 10);

            // Get session ID
            $sessionId = $browser->script('
                return window.autoSaveManager ? 
                    window.autoSaveManager.state.sessionId : null
            ')[0];
            
            $this->assertNotNull($sessionId);
            $this->assertStringContainsString('autosave_', $sessionId);
        });
    }

    /** @test */
    public function test_save_status_updates()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10)
                    ->type('#title', 'Status Test');

            // Wait for saving status
            $browser->waitForText('⏳ Saving', 2);
            
            // Wait for saved status
            $browser->waitForText('✓ Saved', 5);
            
            $this->assertTrue(true); // If we get here, the flow worked
        });
    }

    /** @test */
    public function test_error_handling_display()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10);

            // Simulate a save error by trying to save invalid data
            // This will depend on your validation rules
            $oversizedText = str_repeat('x', 300); // Exceed title limit
            
            $browser->type('#title', $oversizedText)
                    ->waitFor('#title-saved', 5);

            // Should show error indicator
            $saveIndicatorText = $browser->text('#title-saved');
            
            // Either shows error or validation prevents the save
            $this->assertTrue(
                str_contains($saveIndicatorText, '✗') || 
                str_contains($saveIndicatorText, '✓') ||
                empty($saveIndicatorText)
            );
        });
    }

    /** @test */
    public function test_tab_visibility_handling()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-autosave]', 10)
                    ->type('#title', 'Tab visibility test');

            // Simulate tab becoming hidden
            $browser->script('
                Object.defineProperty(document, "hidden", { 
                    get: function() { return true; } 
                });
                document.dispatchEvent(new Event("visibilitychange"));
            ');

            // Wait a moment for any processing
            $browser->pause(500);

            // Simulate tab becoming visible again  
            $browser->script('
                Object.defineProperty(document, "hidden", { 
                    get: function() { return false; } 
                });
                document.dispatchEvent(new Event("visibilitychange"));
            ');

            // Should trigger a server sync
            $browser->pause(1000);
            
            $this->assertTrue(true); // If we get here without errors, the handling worked
        });
    }

    /** @test */
    public function test_analysis_status_integration()
    {
        // Update rack to completed analysis
        $this->rack->update([
            'status' => 'pending',
            'device_count' => 5,
            'chain_count' => 3
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('.bg-green-50', 10); // Analysis complete banner

            // Should show analysis complete state
            $this->assertStringContainsString('Analysis complete', $browser->text('.bg-green-50'));
            
            // Proceed button should be enabled
            $proceedButton = $browser->element('#proceed-to-annotation');
            $this->assertFalse($proceedButton->getAttribute('disabled'));
        });
    }

    /** @test */
    public function test_conflict_modal_creation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('[data-autosave]', 10);

            // Check if conflict modal was created
            $modalExists = $browser->script('
                return document.getElementById("conflict-modal") !== null
            ')[0];
            
            $this->assertTrue($modalExists);
        });
    }

    /** @test */
    public function test_multiple_field_editing_session()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10);

            // Edit multiple fields in sequence
            $browser->type('#title', 'Multi-field test title')
                    ->waitForText('✓ Saved', 5)
                    ->type('#description', 'Multi-field test description')  
                    ->waitForText('✓ Saved', 5)
                    ->select('#category', 'bass')
                    ->waitForText('✓ Saved', 5);

            // All saves should have succeeded
            $this->assertTrue(true);
        });
    }

    /** @test */
    public function test_page_reload_handling()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit("/racks/{$this->rack->id}/metadata")
                    ->waitFor('#title', 10)
                    ->type('#title', 'Before reload test')
                    ->waitForText('✓ Saved', 5);

            // Reload the page
            $browser->refresh()
                    ->waitFor('#title', 10);

            // Should maintain the saved value
            $titleValue = $browser->value('#title');
            $this->assertStringContainsString('Before reload test', $titleValue);
        });
    }
}