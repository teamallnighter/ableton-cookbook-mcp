<?php

namespace Tests\Feature\Api\Collections;

use Tests\TestCase;
use App\Models\User;
use App\Models\EnhancedCollection;
use App\Models\Rack;
use App\Models\Preset;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class EnhancedCollectionApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    /** @test */
    public function it_can_list_collections()
    {
        // Create test collections
        EnhancedCollection::factory()->count(3)->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->getJson('/api/v1/collections');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'title',
                        'description',
                        'slug',
                        'collection_type',
                        'visibility',
                        'status',
                        'user' => ['id', 'name'],
                        'items_count',
                        'views_count',
                        'downloads_count',
                        'saves_count',
                        'average_rating',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function it_can_create_a_new_collection()
    {
        $data = [
            'title' => 'My New Collection',
            'description' => 'A comprehensive collection of electronic music tools',
            'collection_type' => 'manual',
            'visibility' => 'public',
            'category' => 'Electronic',
            'difficulty_level' => 2,
            'tags' => ['electronic', 'synthesizer', 'ambient'],
        ];

        $response = $this->postJson('/api/v1/collections', $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'title',
                    'slug',
                    'collection_type',
                    'visibility',
                    'status',
                    'user',
                ]
            ])
            ->assertJsonPath('data.title', 'My New Collection')
            ->assertJsonPath('data.slug', 'my-new-collection')
            ->assertJsonPath('data.collection_type', 'manual')
            ->assertJsonPath('data.user.id', $this->user->id);
    }

    /** @test */
    public function it_validates_collection_creation_data()
    {
        $response = $this->postJson('/api/v1/collections', [
            'title' => '', // Empty title should fail
            'collection_type' => 'invalid_type', // Invalid type should fail
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'collection_type']);
    }

    /** @test */
    public function it_can_show_a_specific_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->getJson("/api/v1/collections/{$collection->uuid}");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'title',
                    'description',
                    'slug',
                    'collection_type',
                    'user',
                    'items' => [
                        '*' => [
                            'id',
                            'itemable_type',
                            'itemable_id',
                            'section',
                            'sort_order',
                            'custom_title',
                        ]
                    ],
                ]
            ])
            ->assertJsonPath('data.uuid', $collection->uuid);
    }

    /** @test */
    public function it_can_update_own_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
        ]);

        $updateData = [
            'title' => 'Updated Collection Title',
            'description' => 'Updated description',
            'visibility' => 'unlisted',
        ];

        $response = $this->putJson("/api/v1/collections/{$collection->uuid}", $updateData);

        $response->assertSuccessful()
            ->assertJsonPath('data.title', 'Updated Collection Title')
            ->assertJsonPath('data.slug', 'updated-collection-title')
            ->assertJsonPath('data.visibility', 'unlisted');
    }

    /** @test */
    public function it_prevents_updating_others_collections()
    {
        $otherUser = User::factory()->create();
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->putJson("/api/v1/collections/{$collection->uuid}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function it_can_add_items_to_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
        ]);
        
        $rack = Rack::factory()->create();
        $preset = Preset::factory()->create();

        $data = [
            'items' => [
                [
                    'type' => 'rack',
                    'id' => $rack->id,
                    'section' => 'Instruments',
                    'custom_title' => 'My Custom Bass Rack',
                    'notes' => 'Great for deep bass lines',
                ],
                [
                    'type' => 'preset',
                    'id' => $preset->id,
                    'section' => 'Effects',
                    'is_featured' => true,
                ]
            ]
        ];

        $response = $this->postJson("/api/v1/collections/{$collection->uuid}/items", $data);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'itemable_type',
                        'itemable_id',
                        'section',
                        'custom_title',
                        'notes',
                        'sort_order',
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $collection->fresh()->items_count);
    }

    /** @test */
    public function it_can_remove_items_from_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
        ]);
        
        $rack = Rack::factory()->create();
        
        // Add item first
        $this->postJson("/api/v1/collections/{$collection->uuid}/items", [
            'items' => [['type' => 'rack', 'id' => $rack->id]]
        ]);

        $item = $collection->fresh()->items->first();

        $response = $this->deleteJson("/api/v1/collections/{$collection->uuid}/items/{$item->id}");

        $response->assertSuccessful();
        $this->assertEquals(0, $collection->fresh()->items_count);
    }

    /** @test */
    public function it_can_reorder_collection_items()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
        ]);
        
        $racks = Rack::factory()->count(3)->create();
        
        // Add items
        $this->postJson("/api/v1/collections/{$collection->uuid}/items", [
            'items' => $racks->map(fn($rack) => ['type' => 'rack', 'id' => $rack->id])->toArray()
        ]);

        $items = $collection->fresh()->items;
        $newOrder = [$items[2]->id, $items[0]->id, $items[1]->id];

        $response = $this->postJson("/api/v1/collections/{$collection->uuid}/reorder", [
            'item_order' => $newOrder
        ]);

        $response->assertSuccessful();
        
        // Verify new order
        $reorderedItems = $collection->fresh()->items()->orderBy('sort_order')->get();
        $this->assertEquals($newOrder[0], $reorderedItems[0]->id);
        $this->assertEquals($newOrder[1], $reorderedItems[1]->id);
        $this->assertEquals($newOrder[2], $reorderedItems[2]->id);
    }

    /** @test */
    public function it_can_duplicate_collection()
    {
        $original = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Collection',
        ]);

        $response = $this->postJson("/api/v1/collections/{$original->uuid}/duplicate", [
            'title' => 'My Duplicated Collection'
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'My Duplicated Collection')
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.status', 'draft');

        $this->assertNotEquals($original->uuid, $response->json('data.uuid'));
    }

    /** @test */
    public function it_can_save_and_unsave_collections()
    {
        $collection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        // Save collection
        $response = $this->postJson("/api/v1/collections/{$collection->uuid}/save");
        $response->assertSuccessful()
            ->assertJsonPath('data.saved', true);

        // Unsave collection
        $response = $this->postJson("/api/v1/collections/{$collection->uuid}/save");
        $response->assertSuccessful()
            ->assertJsonPath('data.saved', false);
    }

    /** @test */
    public function it_can_publish_collection()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
            'items_count' => 3, // Need items to publish
        ]);

        $response = $this->postJson("/api/v1/collections/{$collection->uuid}/publish");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'review'); // Goes to review first
    }

    /** @test */
    public function it_can_get_collection_statistics()
    {
        $collection = EnhancedCollection::factory()->create([
            'user_id' => $this->user->id,
            'views_count' => 150,
            'downloads_count' => 25,
            'saves_count' => 12,
        ]);

        $response = $this->getJson("/api/v1/collections/{$collection->uuid}/statistics");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'views' => ['total', 'unique_today', 'trend'],
                    'engagement' => ['downloads', 'saves', 'comments', 'likes', 'rating'],
                    'progress' => ['enrollments', 'completions', 'completion_rate'],
                    'content' => ['items_count', 'sections', 'estimated_time'],
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_protected_endpoints()
    {
        auth()->logout();

        $collection = EnhancedCollection::factory()->create();

        // Creating collections requires auth
        $response = $this->postJson('/api/v1/collections', [
            'title' => 'Test Collection'
        ]);
        $response->assertUnauthorized();

        // Updating collections requires auth
        $response = $this->putJson("/api/v1/collections/{$collection->uuid}", [
            'title' => 'Updated'
        ]);
        $response->assertUnauthorized();

        // But viewing public collections doesn't
        $publicCollection = EnhancedCollection::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->getJson("/api/v1/collections/{$publicCollection->uuid}");
        $response->assertSuccessful();
    }

    /** @test */
    public function it_can_filter_collections_by_type()
    {
        EnhancedCollection::factory()->create([
            'collection_type' => 'genre_cookbook',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        EnhancedCollection::factory()->create([
            'collection_type' => 'quick_start_pack',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->getJson('/api/v1/collections?filter[collection_type]=genre_cookbook');

        $response->assertSuccessful();
        
        $collections = $response->json('data');
        $this->assertCount(1, $collections);
        $this->assertEquals('genre_cookbook', $collections[0]['collection_type']);
    }

    /** @test */
    public function it_can_search_collections()
    {
        $collection = EnhancedCollection::factory()->create([
            'title' => 'Electronic Music Production',
            'description' => 'Learn synthesizer techniques',
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->getJson('/api/v1/collections?search=electronic');

        $response->assertSuccessful();
        
        $collections = $response->json('data');
        $this->assertGreaterThan(0, count($collections));
        $this->assertTrue(
            collect($collections)->contains('uuid', $collection->uuid)
        );
    }
}