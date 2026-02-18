<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Rack;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Rack $rack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
            'devices' => json_encode([
                'chains' => [
                    ['name' => 'Chain 1', 'devices' => [
                        ['name' => 'Operator', 'type' => 'Instrument'],
                        ['name' => 'Reverb', 'type' => 'AudioEffect'],
                    ]],
                    ['name' => 'Chain 2', 'devices' => [
                        ['name' => 'Bass', 'type' => 'Instrument'],
                        ['name' => 'EQ', 'type' => 'AudioEffect'],
                    ]],
                ]
            ])
        ]);
    }

    public function test_rack_show_has_proper_semantic_structure()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test semantic HTML structure
            ->assertSee('<main', false)
            ->assertSee('role="main"', false)
            ->assertSee('<header', false)
            ->assertSee('role="banner"', false)
            ->assertSee('<nav', false)
            ->assertSee('role="navigation"', false)
            ->assertSee('<section', false)
            ->assertSee('role="article"', false)
            ->assertSee('<aside', false)
            ->assertSee('role="complementary"', false);
    }

    public function test_rack_show_has_proper_aria_labels()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test ARIA labels
            ->assertSee('aria-labelledby="page-title"', false)
            ->assertSee('aria-label="Breadcrumb navigation"', false)
            ->assertSee('aria-labelledby="rack-structure-heading"', false)
            ->assertSee('aria-label="View mode selection"', false)
            ->assertSee('aria-pressed', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_rack_show_has_keyboard_navigation_support()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test keyboard navigation elements
            ->assertSee('tabindex="0"', false)
            ->assertSee('role="button"', false)
            ->assertSee('aria-expanded', false)
            ->assertSee('Skip to main content', false);
    }

    public function test_tree_view_has_proper_aria_structure()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test tree view ARIA attributes
            ->assertSee('role="tree"', false)
            ->assertSee('role="treeitem"', false)
            ->assertSee('role="group"', false)
            ->assertSee('aria-level', false)
            ->assertSee('aria-expanded', false);
    }

    public function test_buttons_have_proper_accessibility_attributes()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test button accessibility
            ->assertSee('type="button"', false)
            ->assertSee('aria-label', false)
            ->assertSee('title=', false);
    }

    public function test_forms_have_proper_labels_and_descriptions()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test form accessibility
            ->assertSee('aria-describedby', false)
            ->assertSee('autocomplete="off"', false)
            ->assertSee('role="searchbox"', false);
    }

    public function test_images_and_icons_have_alt_text_or_aria_labels()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Icons should have titles or aria-labels for screen readers
            ->assertSee('title="', false);
    }

    public function test_color_contrast_classes_are_applied()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test that text uses high-contrast color classes
            ->assertSee('text-gray-600', false)
            ->assertSee('text-gray-500', false)
            ->assertSee('text-black', false);
    }

    public function test_focus_indicators_are_present()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Check for focus ring classes in CSS
            ->assertSee('focus:ring-2', false)
            ->assertSee('focus:border-blue-500', false);
    }

    public function test_reduced_motion_support()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Check for reduced motion CSS rules
            ->assertSee('@media (prefers-reduced-motion: reduce)', false);
    }

    public function test_screen_reader_only_content()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test screen reader only content
            ->assertSee('sr-only', false)
            ->assertSee('Use Ctrl+F or Cmd+F to quickly access search', false);
    }

    public function test_skip_link_functionality()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test skip link
            ->assertSee('Skip to main content', false)
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false);
    }

    public function test_status_announcements_for_dynamic_content()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test live regions for status announcements
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-atomic="true"', false);
    }

    public function test_headings_hierarchy_is_proper()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $content = $response->getContent();

        // Test proper heading hierarchy (h1 -> h2 -> h3)
        $this->assertStringContainsString('<h1', $content);
        $this->assertStringContainsString('<h2', $content);
        $this->assertStringContainsString('<h3', $content);
        
        // Ensure h1 comes before h2, h2 before h3
        $h1Pos = strpos($content, '<h1');
        $h2Pos = strpos($content, '<h2');
        $h3Pos = strpos($content, '<h3');
        
        $this->assertLessThan($h2Pos, $h1Pos);
        $this->assertLessThan($h3Pos, $h2Pos);
    }

    public function test_large_tree_virtualization()
    {
        // Create a rack with many devices to test virtualization
        $largeRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
            'devices' => json_encode([
                'chains' => array_fill(0, 150, [ // Create 150 chains to trigger virtualization
                    'name' => 'Chain Test',
                    'devices' => [
                        ['name' => 'Device 1', 'type' => 'Instrument'],
                        ['name' => 'Device 2', 'type' => 'AudioEffect'],
                        ['name' => 'Device 3', 'type' => 'AudioEffect'],
                    ]
                ])
            ])
        ]);

        $response = $this->get(route('racks.show', $largeRack));

        $response->assertStatus(200)
            // Test virtualization announcement
            ->assertSee('Large device tree detected', false)
            ->assertSee('Virtualization enabled', false);
    }

    public function test_keyboard_shortcuts_are_documented()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test keyboard shortcut documentation
            ->assertSee('Ctrl/Cmd+F', false)
            ->assertSee('Press Escape to clear search', false)
            ->assertSee('Enter to jump to first result', false);
    }

    public function test_error_states_are_accessible()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $response->assertStatus(200)
            // Test accessible error states
            ->assertSee('No device structure available', false)
            ->assertSee('role="status"', false)
            ->assertSee('Try a different search term', false);
    }

    /**
     * Test that the page meets basic WCAG 2.1 AA requirements
     */
    public function test_wcag_compliance_basics()
    {
        $response = $this->get(route('racks.show', $this->rack));

        $content = $response->getContent();

        // 1. Images must have alt text or proper ARIA labels
        preg_match_all('/<img[^>]*>/i', $content, $images);
        foreach ($images[0] as $img) {
            $this->assertTrue(
                strpos($img, 'alt=') !== false || strpos($img, 'aria-label=') !== false || strpos($img, 'role="presentation"') !== false,
                "Image without proper accessibility attributes: $img"
            );
        }

        // 2. Form inputs must have labels
        preg_match_all('/<input[^>]*>/i', $content, $inputs);
        foreach ($inputs[0] as $input) {
            $this->assertTrue(
                strpos($input, 'aria-label=') !== false || strpos($input, 'aria-labelledby=') !== false || strpos($input, 'title=') !== false,
                "Input without proper label: $input"
            );
        }

        // 3. Interactive elements must be keyboard accessible
        preg_match_all('/<button[^>]*>/i', $content, $buttons);
        foreach ($buttons[0] as $button) {
            // Buttons should have type="button" or type="submit"
            $this->assertTrue(
                strpos($button, 'type=') !== false || strpos($button, 'wire:') !== false,
                "Button without proper type: $button"
            );
        }
    }
}