<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class NestedChainAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Rack $rack;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-rack-uuid-123',
            'title' => 'Test Rack with Nested Chains',
            'file_path' => 'racks/test-rack.adg',
            'enhanced_analysis_complete' => false
        ]);
    }

    /**
     * Test that endpoint requires authentication
     */
    public function test_analyze_nested_chains_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertUnauthorized();
    }

    /**
     * Test successful nested chain analysis trigger
     */
    public function test_can_trigger_nested_chain_analysis(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rack_uuid',
                'analysis_complete',
                'constitutional_compliant',
                'nested_chains_detected',
                'max_nesting_depth',
                'total_devices',
                'analysis_duration_ms',
                'processed_at',
                'hierarchy_preview'
            ]);
    }

    /**
     * Test force reanalysis parameter
     */
    public function test_can_force_reanalysis_of_rack(): void
    {
        Sanctum::actingAs($this->user);

        // Mark rack as already analyzed
        $this->rack->enhanced_analysis_complete = true;
        $this->rack->save();

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains", [
            'force_reanalysis' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('analysis_complete', true);
    }

    /**
     * Test that analysis can be queued for background processing
     */
    public function test_analysis_can_be_queued_for_background_processing(): void
    {
        Sanctum::actingAs($this->user);

        // Simulate a large rack that would be queued
        config(['nested_chains.queue_threshold_size' => 1]); // Force queueing

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'job_id'
            ])
            ->assertJsonPath('message', 'Analysis queued for processing');
    }

    /**
     * Test 404 response for non-existent rack
     */
    public function test_returns_404_for_non_existent_rack(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/racks/non-existent-uuid/analyze-nested-chains');

        $response->assertNotFound();
    }

    /**
     * Test rate limiting for batch operations
     */
    public function test_rate_limiting_enforced_for_batch_operations(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple racks
        $racks = Rack::factory()->count(11)->create(['user_id' => $this->user->id]);

        // Try to analyze more than 10 racks per minute (rate limit)
        foreach ($racks as $index => $rack) {
            $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

            if ($index < 10) {
                $this->assertIn($response->status(), [200, 202]);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    /**
     * Test that only rack owner can trigger analysis
     */
    public function test_only_rack_owner_can_trigger_analysis(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertForbidden();
    }

    /**
     * Test constitutional compliance is checked
     */
    public function test_constitutional_compliance_is_verified(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertStatus(200)
            ->assertJsonStructure(['constitutional_compliant'])
            ->assertJson(function ($json) {
                return $json->has('constitutional_compliant');
            });
    }

    /**
     * Test response includes nested chain hierarchy preview
     */
    public function test_response_includes_hierarchy_preview(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'hierarchy_preview' => [
                    'chain_id',
                    'chain_name',
                    'chain_type',
                    'depth_level',
                    'device_count',
                    'is_empty',
                    'child_chains'
                ]
            ]);
    }

    /**
     * Test analysis duration is tracked
     */
    public function test_analysis_duration_is_tracked(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/racks/{$this->rack->uuid}/analyze-nested-chains");

        $response->assertStatus(200)
            ->assertJson(function ($json) {
                return $json->has('analysis_duration_ms') &&
                       $json['analysis_duration_ms'] >= 0;
            });
    }
}