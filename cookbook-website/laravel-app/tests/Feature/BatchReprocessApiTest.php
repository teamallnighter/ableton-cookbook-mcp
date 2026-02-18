<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

class BatchReprocessApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin'); // Assuming Spatie permissions

        // Create test racks for batch processing
        $this->createTestRacks();

        Queue::fake();
    }

    /**
     * Test successful batch reprocessing request
     */
    public function test_can_submit_batch_reprocess_request(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(3)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'batch_id',
                'queued_count',
                'estimated_completion'
            ])
            ->assertJsonPath('queued_count', 3);
    }

    /**
     * Test batch processing with different priority levels
     */
    public function test_batch_processing_with_priority_levels(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        // Test high priority
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'high'
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('priority', 'high');

        // Test low priority
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'low'
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('priority', 'low');

        // Test default priority
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('priority', 'normal');
    }

    /**
     * Test maximum batch size limit (10 racks)
     */
    public function test_maximum_batch_size_limit(): void
    {
        Sanctum::actingAs($this->user);

        // Create more racks
        Rack::factory()->count(15)->create(['user_id' => $this->user->id]);

        $allRacks = Rack::take(12)->get();
        $tooManyUuids = $allRacks->pluck('uuid')->toArray();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $tooManyUuids
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['rack_uuids'])
            ->assertJsonPath('message', 'The rack uuids must not have more than 10 items.');
    }

    /**
     * Test validation for invalid UUIDs
     */
    public function test_validation_for_invalid_uuids(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => ['invalid-uuid', 'another-invalid-uuid']
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['rack_uuids.0', 'rack_uuids.1']);
    }

    /**
     * Test validation for non-existent rack UUIDs
     */
    public function test_validation_for_non_existent_racks(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [
                'b8c41c35-3b8a-4b5d-9e7a-2c6f8d4e5a2b', // Valid UUID format but doesn't exist
                'a7b32d14-2a7b-3c4e-8d6c-1b5f7c3e4a1c'  // Valid UUID format but doesn't exist
            ]
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'One or more rack UUIDs do not exist or are not accessible');
    }

    /**
     * Test authentication requirement
     */
    public function test_requires_authentication(): void
    {
        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test rate limiting (10 batch operations per minute)
     */
    public function test_rate_limiting_for_batch_operations(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        // Submit 11 batch requests to exceed limit
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
                'rack_uuids' => $rackUuids
            ]);

            if ($i < 10) {
                $response->assertStatus(202);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    /**
     * Test user can only reprocess their own racks
     */
    public function test_user_can_only_reprocess_own_racks(): void
    {
        $otherUser = User::factory()->create();
        $otherUserRack = Rack::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$otherUserRack->uuid]
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You can only reprocess your own racks');
    }

    /**
     * Test admin can reprocess any racks
     */
    public function test_admin_can_reprocess_any_racks(): void
    {
        $anyUserRack = Rack::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$anyUserRack->uuid],
            'priority' => 'high'
        ]);

        $response->assertStatus(202);
    }

    /**
     * Test batch ID generation and uniqueness
     */
    public function test_batch_id_generation_and_uniqueness(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $response1 = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids
        ]);

        $response2 = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids
        ]);

        $response1->assertStatus(202);
        $response2->assertStatus(202);

        $batchId1 = $response1->json('batch_id');
        $batchId2 = $response2->json('batch_id');

        $this->assertNotEquals($batchId1, $batchId2);
        $this->assertIsString($batchId1);
        $this->assertIsString($batchId2);
    }

    /**
     * Test estimated completion time calculation
     */
    public function test_estimated_completion_time_calculation(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(5)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $beforeRequest = now();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $afterRequest = now();

        $response->assertStatus(202);

        $estimatedCompletion = $response->json('estimated_completion');
        $estimatedTime = \Carbon\Carbon::parse($estimatedCompletion);

        // Estimated completion should be in the future
        $this->assertTrue($estimatedTime->isAfter($beforeRequest));

        // Should be reasonable estimate (not more than 1 hour for 5 racks)
        $this->assertTrue($estimatedTime->isBefore($afterRequest->addHour()));
    }

    /**
     * Test empty rack list validation
     */
    public function test_empty_rack_list_validation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => []
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['rack_uuids'])
            ->assertJsonPath('message', 'The rack uuids field is required.');
    }

    /**
     * Test invalid priority validation
     */
    public function test_invalid_priority_validation(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'invalid_priority'
        ]);

        $response->assertStatus(400)
            ->assertJsonValidationErrors(['priority'])
            ->assertJsonPath('errors.priority.0', 'The selected priority is invalid.');
    }

    /**
     * Test job queue integration
     */
    public function test_job_queue_integration(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(3)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'high'
        ]);

        $response->assertStatus(202);

        // Verify jobs were queued
        Queue::assertPushed(\App\Jobs\BatchReprocessJob::class, function ($job) use ($rackUuids) {
            return count($job->rackUuids) === 3 && $job->priority === 'high';
        });
    }

    /**
     * Test concurrent batch processing limits
     */
    public function test_concurrent_batch_processing_limits(): void
    {
        Sanctum::actingAs($this->user);

        // Simulate system under heavy load
        config(['batch_processing.max_concurrent_batches' => 2]);

        $racks = Rack::take(2)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        // First two batches should succeed
        for ($i = 0; $i < 2; $i++) {
            $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
                'rack_uuids' => $rackUuids
            ]);
            $response->assertStatus(202);
        }

        // Third batch should be rejected or queued with delay
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids
        ]);

        $this->assertContains($response->status(), [202, 429, 503]);

        if ($response->status() === 503) {
            $response->assertJsonPath('message', 'System is currently processing maximum concurrent batches. Please try again later.');
        }
    }

    /**
     * Test performance with maximum batch size
     */
    public function test_performance_with_maximum_batch_size(): void
    {
        Sanctum::actingAs($this->user);

        $racks = Rack::take(10)->get();
        $rackUuids = $racks->pluck('uuid')->toArray();

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(202);

        // Should respond within 500ms even for maximum batch size
        $this->assertLessThan(500, $duration, 'Batch reprocess API should respond within 500ms');
    }

    /**
     * Create test racks for batch processing
     */
    private function createTestRacks(): void
    {
        Rack::factory()->count(12)->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false
        ]);
    }
}