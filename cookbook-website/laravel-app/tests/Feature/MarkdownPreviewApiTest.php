<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarkdownPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_preview_basic_markdown()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '# Hello World\n\nThis is **bold** text.'
        ]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'html',
                     'reading_time',
                     'word_count',
                     'headings',
                     'meta'
                 ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('<h1>Hello World</h1>', $data['html']);
        $this->assertStringContainsString('<strong>bold</strong>', $data['html']);
        $this->assertEquals(1, $data['reading_time']);
        $this->assertGreaterThan(0, $data['word_count']);
        $this->assertCount(1, $data['headings']);
    }

    /** @test */
    public function it_can_preview_rack_embeds()
    {
        $rack = Rack::factory()->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Test Rack',
            'is_public' => true
        ]);

        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '# My Article\n\n[[rack:550e8400-e29b-41d4-a716-446655440000]]'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('rack-embed', $data['html']);
        $this->assertStringContainsString('Test Rack', $data['html']);
    }

    /** @test */
    public function it_can_preview_device_references()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => 'Use the {{device:Operator}} for synthesis.'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('device-ref', $data['html']);
        $this->assertStringContainsString('Operator', $data['html']);
    }

    /** @test */
    public function it_can_preview_parameter_controls()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => 'Set the {param:Cutoff|min:0|max:127|value:64} to taste.'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('parameter-control', $data['html']);
        $this->assertStringContainsString('Cutoff', $data['html']);
    }

    /** @test */
    public function it_can_preview_audio_players()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => ':::audio[My Demo](https://example.com/demo.mp3)'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('audio-player', $data['html']);
        $this->assertStringContainsString('My Demo', $data['html']);
    }

    /** @test */
    public function it_can_preview_youtube_embeds()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '[Tutorial Video](https://youtube.com/watch?v=dQw4w9WgXcQ)'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('youtube-embed', $data['html']);
    }

    /** @test */
    public function it_can_preview_soundcloud_embeds()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '[SoundCloud - My Track](https://soundcloud.com/user/track)'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('soundcloud-embed', $data['html']);
    }

    /** @test */
    public function it_validates_markdown_content()
    {
        $response = $this->postJson('/api/v1/markdown/validate', [
            'markdown' => '# Valid Content\n\nThis is safe.'
        ]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'valid',
                     'issues',
                     'media_issues',
                     'statistics'
                 ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['valid']);
        $this->assertEmpty($data['issues']);
    }

    /** @test */
    public function it_detects_xss_in_validation()
    {
        $response = $this->postJson('/api/v1/markdown/validate', [
            'markdown' => '<script>alert("xss")</script>'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertFalse($data['valid']);
        $this->assertNotEmpty($data['issues']);
    }

    /** @test */
    public function it_rejects_oversized_content()
    {
        $largeContent = str_repeat('word ', 50000); // Over 100KB
        
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => $largeContent
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_requires_markdown_parameter()
    {
        $response = $this->postJson('/api/v1/markdown/preview', []);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['markdown']);
    }

    /** @test */
    public function it_rate_limits_preview_requests()
    {
        // Make 61 requests to exceed the 60/minute limit
        for ($i = 0; $i < 61; $i++) {
            $response = $this->postJson('/api/v1/markdown/preview', [
                'markdown' => "# Request $i"
            ]);
            
            if ($i < 60) {
                $response->assertOk();
            } else {
                $response->assertStatus(429);
                break;
            }
        }
    }

    /** @test */
    public function it_provides_syntax_help()
    {
        $response = $this->get('/api/v1/markdown/syntax-help');
        
        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'syntax_help' => [
                         'basic_markdown',
                         'ableton_extensions',
                         'media_embeds',
                         'best_practices'
                     ]
                 ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('rack_embeds', $data['syntax_help']['ableton_extensions']);
        $this->assertArrayHasKey('device_references', $data['syntax_help']['ableton_extensions']);
    }

    /** @test */
    public function it_sanitizes_dangerous_content()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '<script>alert("xss")</script><p>Safe content</p>'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringNotContainsString('<script>', $data['html']);
        $this->assertStringContainsString('Safe content', $data['html']);
    }

    /** @test */
    public function it_handles_malformed_rack_embeds_gracefully()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '[[rack:invalid-uuid]]'
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('rack-embed-error', $data['html']);
    }

    /** @test */
    public function it_processes_complex_mixed_content()
    {
        $complexMarkdown = <<<'MD'
# Complete Tutorial

## Introduction

Welcome to this **comprehensive** guide about {{device:Operator}}.

## Rack Setup

Here's the rack we'll be using:
[[rack:550e8400-e29b-41d4-a716-446655440000]]

## Parameter Settings

Set the {param:Cutoff|min:0|max:127|value:64} to your liking.

## Video Tutorial

[Watch this video](https://youtube.com/watch?v=dQw4w9WgXcQ)

## Audio Example

:::audio[Demo Track](https://example.com/demo.mp3)

## Code Example

```javascript
// Some code
const synth = new Operator();
```

## Conclusion

That's it! Check out [this SoundCloud track](https://soundcloud.com/user/track) for more examples.
MD;

        // Create a test rack for the embed
        Rack::factory()->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Tutorial Rack',
            'is_public' => true
        ]);

        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => $complexMarkdown
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        
        // Check for all expected elements
        $html = $data['html'];
        $this->assertStringContainsString('<h1>Complete Tutorial</h1>', $html);
        $this->assertStringContainsString('device-ref', $html);
        $this->assertStringContainsString('rack-embed', $html);
        $this->assertStringContainsString('parameter-control', $html);
        $this->assertStringContainsString('youtube-embed', $html);
        $this->assertStringContainsString('audio-player', $html);
        $this->assertStringContainsString('soundcloud-embed', $html);
        $this->assertStringContainsString('<code>', $html);
        
        // Check metadata
        $this->assertGreaterThan(0, $data['reading_time']);
        $this->assertGreaterThan(0, $data['word_count']);
        $this->assertNotEmpty($data['headings']);
        $this->assertEquals('Complete Tutorial', $data['headings'][0]['text']);
    }

    /** @test */
    public function it_handles_options_parameter()
    {
        $response = $this->postJson('/api/v1/markdown/preview', [
            'markdown' => '# Test',
            'options' => [
                'enable_ableton_extensions' => false,
                'enable_media_embeds' => false
            ]
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        $this->assertFalse($data['meta']['ableton_extensions_enabled']);
        $this->assertFalse($data['meta']['media_embeds_enabled']);
    }

    /** @test */
    public function it_provides_comprehensive_statistics()
    {
        $markdown = "# Test\n\nWord count test " . str_repeat('word ', 100);
        
        $response = $this->postJson('/api/v1/markdown/validate', [
            'markdown' => $markdown
        ]);

        $response->assertOk();
        $data = $response->json();
        
        $stats = $data['statistics'];
        $this->assertArrayHasKey('character_count', $stats);
        $this->assertArrayHasKey('word_count', $stats);
        $this->assertArrayHasKey('line_count', $stats);
        $this->assertArrayHasKey('reading_time', $stats);
        $this->assertArrayHasKey('heading_count', $stats);
        
        $this->assertGreaterThan(0, $stats['character_count']);
        $this->assertGreaterThan(100, $stats['word_count']);
        $this->assertEquals(1, $stats['heading_count']);
    }
}