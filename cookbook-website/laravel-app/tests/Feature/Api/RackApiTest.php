<?php

namespace Tests\Feature\Api;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RackApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_can_list_published_racks(): void
    {
        // Create published racks
        Rack::factory()->count(5)->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
        ]);
        
        // Create unpublished racks (should not appear)
        Rack::factory()->count(3)->create(['status' => 'pending']);

        $response = $this->getJson('/api/v1/racks');

        $response->assertOk()
                 ->assertJsonCount(5, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'title',
                             'description',
                             'rack_type',
                             'user',
                             'tags',
                         ]
                     ],
                     'current_page',
                     'per_page',
                     'total'
                 ]);
    }

    public function test_can_upload_rack_file(): void
    {
        $user = User::factory()->create();

        // Create a rack that the mocked service will return
        $mockRack = Rack::factory()->make([
            'id' => 1,
            'uuid' => 'test-uuid',
            'user_id' => $user->id,
            'title' => 'Test Rack',
            'description' => 'A test rack for unit testing',
            'slug' => 'test-rack',
            'status' => 'approved',
            'is_public' => true,
        ]);

        // Mock the RackProcessingService
        $this->mock(\App\Services\RackProcessingService::class, function ($mock) use ($mockRack) {
            $mock->shouldReceive('isDuplicate')->andReturn(null); // No duplicate found
            $mock->shouldReceive('processRack')->andReturn($mockRack);
        });

        // Create a mock ADG file with proper gzip header for testing
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?><Ableton><LiveSet></LiveSet></Ableton>';
        $gzippedContent = gzencode($xmlContent);
        
        // Use Laravel's fake file upload with proper content
        $uploadedFile = UploadedFile::fake()->createWithContent(
            'test-rack.adg',
            $gzippedContent
        );

        $response = $this->actingAs($user, 'sanctum')
                        ->postJson('/api/v1/racks', [
                            'file' => $uploadedFile,
                            'title' => 'Test Rack',
                            'description' => 'A test rack for unit testing',
                            'tags' => ['bass', 'electronic'],
                            'is_public' => true,
                        ]);

        $response->assertCreated()
                 ->assertJsonStructure([
                     'message',
                     'rack' => [
                         'title',
                         'description',
                     ]
                 ]);
    }

    public function test_upload_validation_rejects_invalid_files(): void
    {
        $user = User::factory()->create();

        $invalidFile = UploadedFile::fake()->create('invalid.txt', 100, 'text/plain');

        $response = $this->actingAs($user, 'sanctum')
                        ->postJson('/api/v1/racks', [
                            'file' => $invalidFile,
                            'title' => 'Test Rack',
                        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['file']);
    }

    public function test_can_view_published_rack(): void
    {
        $rack = Rack::factory()->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/racks/{$rack->id}");

        $response->assertOk()
                 ->assertJsonStructure([
                     'id',
                     'title',
                     'description',
                     'rack_type',
                     'user',
                     'tags',
                     'comments',
                 ]);
    }

    public function test_cannot_view_private_rack_without_permission(): void
    {
        $rack = Rack::factory()->create([
            'is_public' => false,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/racks/{$rack->id}");

        $response->assertNotFound();
    }

    public function test_rack_owner_can_view_private_rack(): void
    {
        $user = User::factory()->create();
        $rack = Rack::factory()->create([
            'user_id' => $user->id,
            'is_public' => false,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
                        ->getJson("/api/v1/racks/{$rack->id}");

        $response->assertOk();
    }

    public function test_can_update_own_rack(): void
    {
        $user = User::factory()->create();
        $rack = Rack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
                        ->putJson("/api/v1/racks/{$rack->id}", [
                            'title' => 'Updated Title',
                            'description' => 'Updated description',
                        ]);

        $response->assertOk();
        $this->assertDatabaseHas('racks', [
            'id' => $rack->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_cannot_update_others_rack(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $rack = Rack::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
                        ->putJson("/api/v1/racks/{$rack->id}", [
                            'title' => 'Hacked Title',
                        ]);

        $response->assertForbidden();
    }

    public function test_can_delete_own_rack(): void
    {
        $user = User::factory()->create();
        $rack = Rack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
                        ->deleteJson("/api/v1/racks/{$rack->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('racks', ['id' => $rack->id]);
    }

    public function test_trending_endpoint_returns_recent_popular_racks(): void
    {
        // Create old racks (should not appear in trending)
        Rack::factory()->count(3)->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
            'created_at' => now()->subWeeks(3),
            'downloads_count' => 100,
        ]);

        // Create recent popular racks
        Rack::factory()->count(5)->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
            'created_at' => now()->subWeek(),
            'downloads_count' => 50,
            'average_rating' => 4.5,
            'ratings_count' => 10,
        ]);

        $response = $this->getJson('/api/v1/racks/trending?limit=10');

        $response->assertOk()
                 ->assertJsonCount(5);
    }

    public function test_featured_endpoint_returns_featured_racks(): void
    {
        // Create regular racks
        Rack::factory()->count(3)->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
            'is_featured' => false,
        ]);

        // Create featured racks
        Rack::factory()->count(2)->create([
            'status' => 'approved',
            'is_public' => true,
            'published_at' => now(),
            'is_featured' => true,
        ]);

        $response = $this->getJson('/api/v1/racks/featured');

        $response->assertOk()
                 ->assertJsonCount(2);
    }

    public function test_rate_limiting_prevents_excessive_requests(): void
    {
        // Make requests up to the limit
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/v1/racks');
            
            if ($i < 60) {
                $response->assertOk();
            } else {
                // 61st request should be rate limited
                $response->assertStatus(429);
            }
        }
    }
}