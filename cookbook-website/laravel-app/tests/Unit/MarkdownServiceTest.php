<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MarkdownService;

class MarkdownServiceTest extends TestCase
{
    protected MarkdownService $markdownService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markdownService = new MarkdownService();
    }

    /** @test */
    public function it_can_parse_basic_markdown()
    {
        $markdown = '# Heading 1\n\nThis is **bold** and *italic* text.';
        $html = $this->markdownService->parseToHtml($markdown);
        
        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    /** @test */
    public function it_can_process_youtube_embeds()
    {
        $markdown = 'Check out this video: [My Video](https://www.youtube.com/watch?v=dQw4w9WgXcQ)';
        $html = $this->markdownService->parseToHtml($markdown);
        
        $this->assertStringContainsString('youtube-embed', $html);
        $this->assertStringContainsString('iframe', $html);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $html);
    }

    /** @test */
    public function it_can_process_soundcloud_embeds()
    {
        $markdown = '[SoundCloud](https://soundcloud.com/artist/track)';
        $html = $this->markdownService->parseToHtml($markdown);
        
        $this->assertStringContainsString('soundcloud-embed', $html);
        $this->assertStringContainsString('iframe', $html);
        $this->assertStringContainsString('w.soundcloud.com', $html);
    }

    /** @test */
    public function it_can_extract_headings()
    {
        $markdown = "# Main Title\n## Section 1\n### Subsection\n## Section 2";
        $headings = $this->markdownService->extractHeadings($markdown);
        
        $this->assertCount(4, $headings);
        $this->assertEquals('Main Title', $headings[0]['text']);
        $this->assertEquals(1, $headings[0]['level']);
        $this->assertEquals('main-title', $headings[0]['slug']);
    }

    /** @test */
    public function it_can_calculate_reading_time()
    {
        // Create content with approximately 225 words (should be 1 minute)
        $words = array_fill(0, 225, 'word');
        $markdown = implode(' ', $words);
        
        $readingTime = $this->markdownService->getReadingTime($markdown);
        
        $this->assertEquals(1, $readingTime);
    }

    /** @test */
    public function it_sanitizes_dangerous_content()
    {
        $markdown = '<script>alert("xss")</script> Normal content **bold**';
        $html = $this->markdownService->parseToHtml($markdown);
        
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    /** @test */
    public function it_validates_content_for_issues()
    {
        $suspiciousContent = 'Normal content <script>bad()</script>';
        $issues = $this->markdownService->validateContent($suspiciousContent);
        
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('Script tags', $issues[0]);
    }

    /** @test */
    public function it_validates_embedded_media()
    {
        $markdown = '<iframe src="https://malicious.com/evil"></iframe>';
        $issues = $this->markdownService->validateEmbeddedMedia($markdown);
        
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('unauthorized domains', $issues[0]);
    }

    /** @test */
    public function it_allows_authorized_embed_domains()
    {
        $markdown = '<iframe src="https://www.youtube.com/embed/test"></iframe>';
        $issues = $this->markdownService->validateEmbeddedMedia($markdown);
        
        $this->assertEmpty($issues);
    }
}