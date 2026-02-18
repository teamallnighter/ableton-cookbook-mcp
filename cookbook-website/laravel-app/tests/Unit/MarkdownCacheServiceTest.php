<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\MarkdownCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarkdownCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarkdownCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new MarkdownCacheService();
        Cache::flush(); // Start with clean cache
    }

    protected function tearDown(): void
    {
        Cache::flush(); // Clean up after tests
        parent::tearDown();
    }

    /** @test */
    public function it_can_cache_and_retrieve_content()
    {
        $markdown = '# Test Content';
        $html = '<h1>Test Content</h1>';
        $cacheKey = $this->cacheService->getCacheKey($markdown);
        
        $this->cacheService->put($cacheKey, $html);
        $retrieved = $this->cacheService->get($cacheKey);
        
        $this->assertEquals($html, $retrieved);
    }

    /** @test */
    public function it_generates_consistent_cache_keys()
    {
        $markdown = '# Same Content';
        $options = ['test' => true];
        
        $key1 = $this->cacheService->getCacheKey($markdown, $options);
        $key2 = $this->cacheService->getCacheKey($markdown, $options);
        
        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_keys_for_different_content()
    {
        $markdown1 = '# Content 1';
        $markdown2 = '# Content 2';
        
        $key1 = $this->cacheService->getCacheKey($markdown1);
        $key2 = $this->cacheService->getCacheKey($markdown2);
        
        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_keys_for_different_options()
    {
        $markdown = '# Same Content';
        $options1 = ['test' => true];
        $options2 = ['test' => false];
        
        $key1 = $this->cacheService->getCacheKey($markdown, $options1);
        $key2 = $this->cacheService->getCacheKey($markdown, $options2);
        
        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_can_cache_with_metadata()
    {
        $markdown = '# Test Content';
        $html = '<h1>Test Content</h1>';
        $metadata = [
            'reading_time' => 1,
            'word_count' => 2,
            'headings' => [['text' => 'Test Content', 'level' => 1]]
        ];
        
        $this->cacheService->cacheWithMetadata($markdown, $html, $metadata);
        
        $retrievedMetadata = $this->cacheService->getMetadata($markdown);
        $this->assertEquals($metadata, $retrievedMetadata);
    }

    /** @test */
    public function it_can_get_or_generate_content()
    {
        $markdown = '# New Content';
        $expectedHtml = '<h1>New Content</h1>';
        
        $generatorCalled = false;
        $generator = function($content) use (&$generatorCalled, $expectedHtml) {
            $generatorCalled = true;
            return $expectedHtml;
        };
        
        // First call should generate
        $html = $this->cacheService->getOrGenerate($markdown, $generator);
        $this->assertTrue($generatorCalled);
        $this->assertEquals($expectedHtml, $html);
        
        // Second call should use cache
        $generatorCalled = false;
        $html = $this->cacheService->getOrGenerate($markdown, $generator);
        $this->assertFalse($generatorCalled); // Should not call generator again
        $this->assertEquals($expectedHtml, $html);
    }

    /** @test */
    public function it_can_check_if_content_is_cached()
    {
        $markdown = '# Test Content';
        
        $this->assertFalse($this->cacheService->isCached($markdown));
        
        $this->cacheService->put($this->cacheService->getCacheKey($markdown), '<h1>Test</h1>');
        
        $this->assertTrue($this->cacheService->isCached($markdown));
    }

    /** @test */
    public function it_can_invalidate_cache()
    {
        $markdown = '# Test Content';
        $html = '<h1>Test Content</h1>';
        
        // Cache the content
        $cacheKey = $this->cacheService->getCacheKey($markdown);
        $this->cacheService->put($cacheKey, $html);
        
        // Verify it's cached
        $this->assertTrue($this->cacheService->isCached($markdown));
        
        // Invalidate
        $this->cacheService->invalidate($markdown);
        
        // Should no longer be cached
        $this->assertFalse($this->cacheService->isCached($markdown));
    }

    /** @test */
    public function it_returns_cache_statistics()
    {
        $stats = $this->cacheService->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('enabled', $stats);
        $this->assertArrayHasKey('driver', $stats);
    }

    /** @test */
    public function it_can_warm_cache_with_content_items()
    {
        $items = [
            [
                'markdown' => '# Item 1',
                'html' => '<h1>Item 1</h1>',
                'metadata' => ['word_count' => 2]
            ],
            [
                'markdown' => '# Item 2',
                'html' => '<h1>Item 2</h1>',
                'metadata' => ['word_count' => 2]
            ]
        ];
        
        $warmed = $this->cacheService->warmCache($items);
        
        $this->assertEquals(2, $warmed);
        $this->assertTrue($this->cacheService->isCached('# Item 1'));
        $this->assertTrue($this->cacheService->isCached('# Item 2'));
    }

    /** @test */
    public function it_skips_invalid_items_during_cache_warming()
    {
        $items = [
            ['markdown' => '# Valid', 'html' => '<h1>Valid</h1>'],
            ['markdown' => ''], // Invalid - no HTML
            ['html' => '<h1>No Markdown</h1>'], // Invalid - no markdown
            ['markdown' => '# Valid 2', 'html' => '<h1>Valid 2</h1>']
        ];
        
        $warmed = $this->cacheService->warmCache($items);
        
        $this->assertEquals(2, $warmed); // Only 2 valid items
    }

    /** @test */
    public function it_can_be_enabled_and_disabled()
    {
        $this->assertTrue($this->cacheService->isEnabled());
        
        $this->cacheService->setEnabled(false);
        $this->assertFalse($this->cacheService->isEnabled());
        
        $this->cacheService->setEnabled(true);
        $this->assertTrue($this->cacheService->isEnabled());
    }

    /** @test */
    public function it_bypasses_cache_when_disabled()
    {
        $this->cacheService->setEnabled(false);
        
        $markdown = '# Test Content';
        $expectedHtml = '<h1>Test Content</h1>';
        
        $generatorCallCount = 0;
        $generator = function($content) use (&$generatorCallCount, $expectedHtml) {
            $generatorCallCount++;
            return $expectedHtml;
        };
        
        // First call
        $html = $this->cacheService->getOrGenerate($markdown, $generator);
        $this->assertEquals(1, $generatorCallCount);
        $this->assertEquals($expectedHtml, $html);
        
        // Second call should also call generator (no caching)
        $html = $this->cacheService->getOrGenerate($markdown, $generator);
        $this->assertEquals(2, $generatorCallCount);
        $this->assertEquals($expectedHtml, $html);
    }

    /** @test */
    public function it_returns_null_for_missing_cache_entries()
    {
        $nonExistentKey = 'markdown:non-existent-key';
        $result = $this->cacheService->get($nonExistentKey);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_missing_metadata()
    {
        $markdown = '# Non-existent Content';
        $metadata = $this->cacheService->getMetadata($markdown);
        
        $this->assertNull($metadata);
    }

    /** @test */
    public function it_handles_cache_errors_gracefully()
    {
        // Test with malformed cache key
        $malformedKey = '';
        
        // These should not throw exceptions
        $this->assertNull($this->cacheService->get($malformedKey));
        $this->cacheService->put($malformedKey, 'content'); // Should not crash
        
        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /** @test */
    public function it_handles_large_content_appropriately()
    {
        // Create content larger than the configured max size
        $largeContent = str_repeat('x', 200000); // 200KB
        $largeHtml = '<p>' . $largeContent . '</p>';
        
        $cacheKey = $this->cacheService->getCacheKey($largeContent);
        
        // Should not crash when trying to cache large content
        $this->cacheService->put($cacheKey, $largeHtml);
        
        // Content should not be cached due to size limit
        $retrieved = $this->cacheService->get($cacheKey);
        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_can_flush_all_cache()
    {
        // Cache some items
        $this->cacheService->put($this->cacheService->getCacheKey('# Item 1'), '<h1>Item 1</h1>');
        $this->cacheService->put($this->cacheService->getCacheKey('# Item 2'), '<h1>Item 2</h1>');
        
        // Verify they're cached
        $this->assertTrue($this->cacheService->isCached('# Item 1'));
        $this->assertTrue($this->cacheService->isCached('# Item 2'));
        
        // Flush cache
        $this->cacheService->flush();
        
        // Should no longer be cached
        $this->assertFalse($this->cacheService->isCached('# Item 1'));
        $this->assertFalse($this->cacheService->isCached('# Item 2'));
    }
}