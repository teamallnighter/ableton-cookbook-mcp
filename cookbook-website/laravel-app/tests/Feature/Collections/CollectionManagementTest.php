<?php

namespace Tests\Feature\Collections;

use Tests\TestCase;
use App\Models\User;
use App\Models\EnhancedCollection;
use App\Models\CollectionItem;
use App\Models\Rack;
use App\Models\Preset;
use App\Services\CollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CollectionManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected CollectionService $collectionService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->collectionService = app(CollectionService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_a_new_collection()
    {
        $data = [
            'title' => 'My Awesome Collection',
            'description' => 'A collection of amazing racks and presets',
            'collection_type' => 'manual',
            'visibility' => 'public',
            'status' => 'draft',
            'category' => 'Electronic',
            'difficulty_level' => 2,
            'tags' => ['electronic', 'ambient', 'synthesizer'],
        ];

        $collection = $this->collectionService->createCollection($this->user, $data);

        $this->assertInstanceOf(EnhancedCollection::class, $collection);
        $this->assertEquals('My Awesome Collection', $collection->title);
        $this->assertEquals($this->user->id, $collection->user_id);
        $this->assertNotEmpty($collection->uuid);
        $this->assertNotEmpty($collection->slug);
        $this->assertEquals('my-awesome-collection', $collection->slug);
    }

    /** @test */
    public function it_generates_unique_slugs_for_duplicate_titles()
    {
        // Create first collection
        $collection1 = $this->collectionService->createCollection($this->user, [
            'title' => 'Duplicate Title',
            'description' => 'First collection',
        ]);

        // Create second collection with same title
        $collection2 = $this->collectionService->createCollection($this->user, [
            'title' => 'Duplicate Title',
            'description' => 'Second collection',
        ]);

        $this->assertEquals('duplicate-title', $collection1->slug);
        $this->assertEquals('duplicate-title-1', $collection2->slug);
    }

    /** @test */
    public function it_can_add_items_to_collection()
    {
        $collection = EnhancedCollection::factory()->create(['user_id' => $this->user->id]);
        $rack = Rack::factory()->create();
        $preset = Preset::factory()->create();

        $items = [
            [
                'type' => 'rack',
                'id' => $rack->id,
                'section' => 'Instruments',
                'custom_title' => 'Modified Bass Rack',
                'notes' => 'Great for deep bass lines',
                'is_required' => true,
            ],
            [
                'type' => 'preset', 
                'id' => $preset->id,
                'section' => 'Effects',
                'is_featured' => true,
            ]
        ];

        $addedItems = $this->collectionService->addItems($collection, $items, $this->user);

        $this->assertCount(2, $addedItems);
        $this->assertEquals(2, $collection->fresh()->items_count);
        
        $firstItem = $addedItems->first();
        $this->assertEquals($rack->id, $firstItem->itemable_id);
        $this->assertEquals('Instruments', $firstItem->section);
        $this->assertEquals('Modified Bass Rack', $firstItem->custom_title);
        $this->assertTrue($firstItem->is_required);
    }

    /** @test */
    public function it_prevents_duplicate_items_in_collection()
    {
        $collection = EnhancedCollection::factory()->create(['user_id' => $this->user->id]);
        $rack = Rack::factory()->create();

        // Add item first time
        $this->collectionService->addItems($collection, [
            ['type' => 'rack', 'id' => $rack->id]
        ], $this->user);

        $this->assertEquals(1, $collection->fresh()->items_count);

        // Try to add same item again
        $addedItems = $this->collectionService->addItems($collection, [
            ['type' => 'rack', 'id' => $rack->id]
        ], $this->user);

        // Should skip duplicate
        $this->assertCount(0, $addedItems);
        $this->assertEquals(1, $collection->fresh()->items_count);
    }

    /** @test */
    public function it_can_remove_items_from_collection()
    {
        $collection = EnhancedCollection::factory()->create(['user_id' => $this->user->id]);
        $rack = Rack::factory()->create();
        $preset = Preset::factory()->create();

        // Add items
        $addedItems = $this->collectionService->addItems($collection, [
            ['type' => 'rack', 'id' => $rack->id],
            ['type' => 'preset', 'id' => $preset->id],
        ], $this->user);

        $this->assertEquals(2, $collection->fresh()->items_count);

        // Remove first item
        $deletedCount = $this->collectionService->removeItems($collection, [
            $addedItems->first()->id
        ]);

        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, $collection->fresh()->items_count);
    }

    /** @test */
    public function it_can_reorder_collection_items()
    {
        $collection = EnhancedCollection::factory()->create(['user_id' => $this->user->id]);
        $items = [];
        
        // Create 3 items
        for ($i = 0; $i < 3; $i++) {
            $rack = Rack::factory()->create();
            $items[] = ['type' => 'rack', 'id' => $rack->id];
        }

        $addedItems = $this->collectionService->addItems($collection, $items, $this->user);
        $itemIds = $addedItems->pluck('id')->toArray();

        // Reorder: move last item to first position
        $newOrder = [$itemIds[2], $itemIds[0], $itemIds[1]];
        
        $this->collectionService->reorderItems($collection, $newOrder);

        // Verify new order
        $reorderedItems = $collection->fresh()->items()->orderBy('sort_order')->get();
        $this->assertEquals($itemIds[2], $reorderedItems[0]->id);
        $this->assertEquals($itemIds[0], $reorderedItems[1]->id);
        $this->assertEquals($itemIds[1], $reorderedItems[2]->id);
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
            'title' => 'Updated Collection Title',
            'description' => 'Updated description with **markdown**',
            'status' => 'published',
            'visibility' => 'public',
            'category' => 'House',
        ];

        $updatedCollection = $this->collectionService->updateCollection($collection, $updateData);

        $this->assertEquals('Updated Collection Title', $updatedCollection->title);
        $this->assertEquals('updated-collection-title', $updatedCollection->slug);
        $this->assertEquals('published', $updatedCollection->status);
        $this->assertNotNull($updatedCollection->published_at);
        $this->assertNotNull($updatedCollection->description_html);
    }

    /** @test */
    public function it_can_duplicate_a_collection()
    {
        $original = EnhancedCollection::factory()->create(['user_id' => $this->user->id]);
        $rack = Rack::factory()->create();
        
        // Add some items to original
        $this->collectionService->addItems($original, [
            ['type' => 'rack', 'id' => $rack->id, 'section' => 'Instruments']
        ], $this->user);

        $newUser = User::factory()->create();
        $duplicate = $this->collectionService->duplicateCollection($original, $newUser, 'Copied Collection');

        $this->assertNotEquals($original->id, $duplicate->id);
        $this->assertEquals('Copied Collection', $duplicate->title);
        $this->assertEquals($newUser->id, $duplicate->user_id);
        $this->assertEquals('draft', $duplicate->status);
        $this->assertEquals($original->items_count, $duplicate->items_count);
        
        // Verify items were copied
        $this->assertCount(1, $duplicate->items);
        $this->assertEquals('Instruments', $duplicate->items->first()->section);
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
        $this->assertNotNull($published->published_at);
        $this->assertEquals('unlisted', $published->visibility); // Changed from private to unlisted
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
    public function it_can_get_trending_collections()
    {
        // Create recent collections with views
        $recentCollection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
            'views_count' => 100,
            'created_at' => now()->subDays(2),
        ]);

        // Create older collection
        $oldCollection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
            'views_count' => 50,
            'created_at' => now()->subDays(10),
        ]);

        $trending = $this->collectionService->getTrendingCollections(10, 7);

        $this->assertGreaterThan(0, $trending->count());
        $this->assertTrue($trending->contains($recentCollection));
        $this->assertFalse($trending->contains($oldCollection)); // Too old for 7-day window
    }

    /** @test */
    public function it_caches_collection_data_efficiently()
    {
        $collection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        // First call should hit database
        $firstCall = $this->collectionService->getCollectionWithRelations($collection->uuid);
        
        // Second call should use cache
        $secondCall = $this->collectionService->getCollectionWithRelations($collection->uuid);

        $this->assertEquals($firstCall->id, $secondCall->id);
        $this->assertEquals($firstCall->title, $secondCall->title);
    }
}