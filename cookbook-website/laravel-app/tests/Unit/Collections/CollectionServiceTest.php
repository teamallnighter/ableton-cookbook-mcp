<?php

namespace Tests\Unit\Collections;

use Tests\TestCase;
use App\Models\User;
use App\Models\EnhancedCollection;
use App\Services\CollectionService;
use App\Services\ContentIntegrationService;
use App\Services\CollectionAnalyticsService;
use App\Services\MarkdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CollectionService $collectionService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the dependencies to avoid complex setup
        $contentIntegration = Mockery::mock(ContentIntegrationService::class);
        $analytics = Mockery::mock(CollectionAnalyticsService::class);
        $markdownService = Mockery::mock(MarkdownService::class);
        
        $analytics->shouldReceive('trackCollectionCreated')->andReturn(true);
        $analytics->shouldReceive('trackItemsAdded')->andReturn(true);
        $analytics->shouldReceive('trackItemsRemoved')->andReturn(true);
        $analytics->shouldReceive('trackCollectionUpdated')->andReturn(true);
        
        $markdownService->shouldReceive('parseToHtml')->andReturn('<p>HTML content</p>');
        
        $this->collectionService = new CollectionService(
            $contentIntegration,
            $analytics,
            $markdownService
        );
        
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_a_collection()
    {
        $data = [
            'title' => 'Test Collection',
            'description' => 'A test collection',
            'collection_type' => 'manual',
            'visibility' => 'public',
            'category' => 'Electronic',
        ];

        $collection = $this->collectionService->createCollection($this->user, $data);

        $this->assertInstanceOf(EnhancedCollection::class, $collection);
        $this->assertEquals('Test Collection', $collection->title);
        $this->assertEquals($this->user->id, $collection->user_id);
        $this->assertEquals('test-collection', $collection->slug);
        $this->assertNotNull($collection->uuid);
    }

    /** @test */
    public function it_generates_unique_slugs()
    {
        // Create first collection
        $collection1 = $this->collectionService->createCollection($this->user, [
            'title' => 'Same Title',
            'description' => 'First collection',
        ]);

        // Create second collection with same title
        $collection2 = $this->collectionService->createCollection($this->user, [
            'title' => 'Same Title',
            'description' => 'Second collection',
        ]);

        $this->assertEquals('same-title', $collection1->slug);
        $this->assertEquals('same-title-1', $collection2->slug);
    }

    /** @test */
    public function it_can_update_collection_metadata()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'status' => 'draft',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'status' => 'published',
        ];

        $updatedCollection = $this->collectionService->updateCollection($collection, $updateData);

        $this->assertEquals('Updated Title', $updatedCollection->title);
        $this->assertEquals('updated-title', $updatedCollection->slug);
        $this->assertEquals('published', $updatedCollection->status);
        $this->assertNotNull($updatedCollection->published_at);
    }

    /** @test */
    public function it_can_publish_a_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
            'visibility' => 'private',
        ]);

        $published = $this->collectionService->publishCollection($collection);

        $this->assertEquals('published', $published->status);
        $this->assertEquals('unlisted', $published->visibility); // Changed from private
        $this->assertNotNull($published->published_at);
    }

    /** @test */
    public function it_can_archive_a_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $archived = $this->collectionService->archiveCollection($collection);

        $this->assertEquals('archived', $archived->status);
        $this->assertEquals('private', $archived->visibility);
    }

    /** @test */
    public function it_can_get_featured_collections()
    {
        // Create some featured collections
        EnhancedCollection::factory()->count(3)->create([
            'is_featured' => true,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        // Create non-featured collections
        EnhancedCollection::factory()->count(2)->create([
            'is_featured' => false,
            'status' => 'published', 
            'visibility' => 'public',
        ]);

        $featured = $this->collectionService->getFeaturedCollections(5);

        $this->assertCount(3, $featured);
        $featured->each(function ($collection) {
            $this->assertTrue($collection->is_featured);
            $this->assertEquals('published', $collection->status);
        });
    }

    /** @test */
    public function it_validates_slug_uniqueness()
    {
        $collection1 = EnhancedCollection::factory()->create(['slug' => 'existing-slug']);
        
        // Test slug generation avoids existing slugs
        $newCollection = $this->collectionService->createCollection($this->user, [
            'title' => 'Existing Slug', // This would normally generate 'existing-slug'
            'description' => 'Test collection',
        ]);

        $this->assertEquals('existing-slug-1', $newCollection->slug);
    }

    /** @test */
    public function it_handles_empty_title_gracefully()
    {
        $this->expectException(\Exception::class);
        
        $this->collectionService->createCollection($this->user, [
            'title' => '', // Empty title should cause error
            'description' => 'Test collection',
        ]);
    }

    /** @test */
    public function it_processes_markdown_description()
    {
        $collection = $this->collectionService->createCollection($this->user, [
            'title' => 'Test Collection',
            'description' => '**Bold text** and *italic text*',
        ]);

        // Our mock should have been called and returned HTML
        $this->assertEquals('<p>HTML content</p>', $collection->description_html);
    }
}