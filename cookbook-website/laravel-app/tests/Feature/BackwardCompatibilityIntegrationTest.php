<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

class BackwardCompatibilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $testRackPath;
    protected Rack $legacyRack;
    protected Rack $enhancedRack;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();

        // Use a test rack from /testRacks directory
        $this->testRackPath = '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/WIERDO.adg';

        // Verify test rack exists
        if (!file_exists($this->testRackPath)) {
            $this->markTestSkipped('Test rack WIERDO.adg not found in /testRacks directory');
        }

        // Create test racks representing different analysis states
        $this->createLegacyAndEnhancedRacks();
    }

    /**
     * Scenario 5: Backward Compatibility Integration Test
     * Test that existing racks work seamlessly with new enhanced analysis system
     */
    public function test_complete_backward_compatibility_scenario(): void
    {
        Sanctum::actingAs($this->user);

        // Step 1: Verify legacy rack state (old analysis system)
        $this->verifyLegacyRackState();

        // Step 2: Test API access to legacy rack without enhanced analysis
        $this->testLegacyRackApiAccess();

        // Step 3: Trigger enhanced analysis on legacy rack
        $this->triggerEnhancedAnalysisOnLegacyRack();

        // Step 4: Verify legacy data preservation during enhancement
        $this->verifyLegacyDataPreservation();

        // Step 5: Test mixed collection access (legacy + enhanced racks)
        $this->testMixedCollectionAccess();

        // Step 6: Verify search and filtering across both analysis types
        $this->verifySearchAndFilteringCompatibility();

        // Step 7: Test migration from legacy to enhanced analysis
        $this->testLegacyToEnhancedMigration();
    }

    /**
     * Test legacy rack API endpoints continue to work
     */
    public function test_legacy_rack_api_endpoints_continue_working(): void
    {
        Sanctum::actingAs($this->user);

        // Test basic rack retrieval
        $response = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'uuid',
                'title',
                'description',
                'analysis_complete',
                'devices',
                'created_at',
                'updated_at'
            ])
            ->assertJsonPath('analysis_complete', true)
            ->assertJsonMissing(['enhanced_analysis_complete']);

        // Test devices endpoint still works
        $devicesResponse = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}/devices");

        $devicesResponse->assertStatus(200)
            ->assertJsonStructure([
                'rack_uuid',
                'devices'
            ]);

        // Test search still includes legacy racks
        $searchResponse = $this->getJson('/api/v1/racks?search=' . urlencode($this->legacyRack->title));

        $searchResponse->assertStatus(200);
        $rackUuids = collect($searchResponse->json('data'))->pluck('uuid');
        $this->assertContains($this->legacyRack->uuid, $rackUuids);
    }

    /**
     * Test enhanced analysis endpoint gracefully handles legacy racks
     */
    public function test_enhanced_analysis_endpoint_handles_legacy_racks(): void
    {
        Sanctum::actingAs($this->user);

        // Enhanced analysis endpoint should work but indicate no enhanced data
        $response = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}/nested-chains");

        // Should return empty or trigger analysis, not error
        $this->assertContains($response->status(), [200, 202]);

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'rack_uuid',
                'total_chains',
                'max_depth',
                'root_chains'
            ])
            ->assertJsonPath('total_chains', 0)
            ->assertJsonPath('root_chains', []);
        }
    }

    /**
     * Test database schema compatibility between old and new systems
     */
    public function test_database_schema_compatibility(): void
    {
        // Verify legacy rack has expected old structure
        $this->assertDatabaseHas('racks', [
            'uuid' => $this->legacyRack->uuid,
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Verify enhanced rack has both old and new structure
        $this->assertDatabaseHas('racks', [
            'uuid' => $this->enhancedRack->uuid,
            'analysis_complete' => true,
            'enhanced_analysis_complete' => true
        ]);

        // Verify enhanced tables only have data for enhanced racks
        $legacyEnhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $this->legacyRack->id)->first();
        $this->assertNull($legacyEnhancedAnalysis);

        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $this->enhancedRack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
    }

    /**
     * Test data migration preserves existing rack information
     */
    public function test_data_migration_preserves_existing_information(): void
    {
        Sanctum::actingAs($this->user);

        // Capture original legacy rack data
        $originalData = [
            'title' => $this->legacyRack->title,
            'description' => $this->legacyRack->description,
            'file_path' => $this->legacyRack->file_path,
            'created_at' => $this->legacyRack->created_at,
            'updated_at' => $this->legacyRack->updated_at,
            'user_id' => $this->legacyRack->user_id,
            'analysis_complete' => $this->legacyRack->analysis_complete
        ];

        // Trigger enhanced analysis
        $response = $this->postJson("/api/v1/racks/{$this->legacyRack->uuid}/analyze-nested-chains");

        $response->assertStatus(200);

        // Verify all original data is preserved
        $updatedRack = Rack::where('uuid', $this->legacyRack->uuid)->first();

        $this->assertEquals($originalData['title'], $updatedRack->title);
        $this->assertEquals($originalData['description'], $updatedRack->description);
        $this->assertEquals($originalData['file_path'], $updatedRack->file_path);
        $this->assertEquals($originalData['created_at'], $updatedRack->created_at);
        $this->assertEquals($originalData['user_id'], $updatedRack->user_id);
        $this->assertTrue($updatedRack->analysis_complete); // Preserved
        $this->assertTrue($updatedRack->enhanced_analysis_complete); // Added
    }

    /**
     * Test collection API handles mixed rack types
     */
    public function test_collection_api_handles_mixed_rack_types(): void
    {
        Sanctum::actingAs($this->user);

        // Get all user racks (should include both legacy and enhanced)
        $response = $this->getJson('/api/v1/racks');

        $response->assertStatus(200);

        $racks = $response->json('data');
        $rackUuids = collect($racks)->pluck('uuid');

        $this->assertContains($this->legacyRack->uuid, $rackUuids);
        $this->assertContains($this->enhancedRack->uuid, $rackUuids);

        // Verify different racks have appropriate fields
        $legacyRackData = collect($racks)->firstWhere('uuid', $this->legacyRack->uuid);
        $enhancedRackData = collect($racks)->firstWhere('uuid', $this->enhancedRack->uuid);

        $this->assertTrue($legacyRackData['analysis_complete']);
        $this->assertFalse($legacyRackData['enhanced_analysis_complete']);

        $this->assertTrue($enhancedRackData['analysis_complete']);
        $this->assertTrue($enhancedRackData['enhanced_analysis_complete']);
    }

    /**
     * Test search and filtering works across both analysis types
     */
    public function test_search_and_filtering_across_analysis_types(): void
    {
        Sanctum::actingAs($this->user);

        // Search that should match both racks
        $searchResponse = $this->getJson('/api/v1/racks?search=Test');

        $searchResponse->assertStatus(200);
        $searchResults = $searchResponse->json('data');
        $searchUuids = collect($searchResults)->pluck('uuid');

        $this->assertContains($this->legacyRack->uuid, $searchUuids);
        $this->assertContains($this->enhancedRack->uuid, $searchUuids);

        // Filter by analysis status
        $legacyOnlyResponse = $this->getJson('/api/v1/racks?enhanced_analysis=false');
        $legacyOnlyResponse->assertStatus(200);
        $legacyResults = collect($legacyOnlyResponse->json('data'))->pluck('uuid');
        $this->assertContains($this->legacyRack->uuid, $legacyResults);

        $enhancedOnlyResponse = $this->getJson('/api/v1/racks?enhanced_analysis=true');
        $enhancedOnlyResponse->assertStatus(200);
        $enhancedResults = collect($enhancedOnlyResponse->json('data'))->pluck('uuid');
        $this->assertContains($this->enhancedRack->uuid, $enhancedResults);
    }

    /**
     * Test bulk operations work with mixed rack types
     */
    public function test_bulk_operations_work_with_mixed_rack_types(): void
    {
        Sanctum::actingAs($this->user);

        // Test batch reprocessing with mixed types
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$this->legacyRack->uuid, $this->enhancedRack->uuid],
            'priority' => 'normal'
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('queued_count', 2);

        // Both should be accepted for reprocessing
        $batchId = $response->json('batch_id');
        $this->assertNotNull($batchId);
    }

    /**
     * Test version compatibility in API responses
     */
    public function test_version_compatibility_in_api_responses(): void
    {
        Sanctum::actingAs($this->user);

        // Legacy rack response should include version info
        $legacyResponse = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}");

        $legacyResponse->assertStatus(200)
            ->assertJsonPath('analysis_complete', true)
            ->assertJsonPath('enhanced_analysis_complete', false);

        // Enhanced rack response should include both versions
        $enhancedResponse = $this->getJson("/api/v1/racks/{$this->enhancedRack->uuid}");

        $enhancedResponse->assertStatus(200)
            ->assertJsonPath('analysis_complete', true)
            ->assertJsonPath('enhanced_analysis_complete', true);

        // Nested chains endpoint should handle version differences
        $legacyChainsResponse = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}/nested-chains");
        $enhancedChainsResponse = $this->getJson("/api/v1/racks/{$this->enhancedRack->uuid}/nested-chains");

        $this->assertContains($legacyChainsResponse->status(), [200, 202]);
        $enhancedChainsResponse->assertStatus(200);
    }

    /**
     * Test performance impact of mixed collection queries
     */
    public function test_performance_impact_of_mixed_collections(): void
    {
        Sanctum::actingAs($this->user);

        // Create additional test racks to simulate larger dataset
        $this->createAdditionalTestRacks();

        $startTime = microtime(true);

        // Query all racks (mixed types)
        $response = $this->getJson('/api/v1/racks?per_page=50');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // Should still respond quickly even with mixed types
        $this->assertLessThan(500, $duration, 'Mixed collection queries should remain performant');

        // Verify response includes both types
        $racks = $response->json('data');
        $hasLegacy = collect($racks)->contains('enhanced_analysis_complete', false);
        $hasEnhanced = collect($racks)->contains('enhanced_analysis_complete', true);

        $this->assertTrue($hasLegacy, 'Response should include legacy racks');
        $this->assertTrue($hasEnhanced, 'Response should include enhanced racks');
    }

    /**
     * Test constitutional compliance reporting with mixed types
     */
    public function test_constitutional_compliance_with_mixed_types(): void
    {
        Sanctum::actingAs($this->user);

        // Get constitutional compliance report
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_racks',
                'analyzed_racks',
                'enhanced_analyzed_racks',
                'constitutionally_compliant_racks',
                'compliance_percentage',
                'enhanced_compliance_percentage'
            ]);

        $data = $response->json();

        // Should count legacy racks in total
        $this->assertGreaterThanOrEqual(2, $data['total_racks']);
        $this->assertGreaterThanOrEqual(2, $data['analyzed_racks']);
        $this->assertGreaterThanOrEqual(1, $data['enhanced_analyzed_racks']);

        // Compliance percentages should be realistic
        $this->assertGreaterThanOrEqual(0, $data['compliance_percentage']);
        $this->assertLessThanOrEqual(100, $data['compliance_percentage']);
    }

    /**
     * Helper method to create legacy and enhanced racks
     */
    private function createLegacyAndEnhancedRacks(): void
    {
        // Create legacy rack (old analysis system)
        $this->legacyRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'legacy-test-' . uniqid(),
            'title' => 'Legacy Test Rack',
            'description' => 'Test rack with legacy analysis only',
            'file_path' => 'legacy-rack.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Create enhanced rack (new analysis system)
        $this->enhancedRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'enhanced-test-' . uniqid(),
            'title' => 'Enhanced Test Rack',
            'description' => 'Test rack with enhanced analysis',
            'file_path' => 'enhanced-rack.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => true
        ]);

        // Copy test rack content to both
        Storage::put($this->legacyRack->file_path, file_get_contents($this->testRackPath));
        Storage::put($this->enhancedRack->file_path, file_get_contents($this->testRackPath));

        // Create enhanced analysis data for enhanced rack
        EnhancedRackAnalysis::create([
            'rack_id' => $this->enhancedRack->id,
            'constitutional_compliant' => true,
            'has_nested_chains' => true,
            'total_chains_detected' => 5,
            'max_nesting_depth' => 3,
            'total_devices' => 15,
            'analysis_duration_ms' => 1200,
            'processed_at' => now()
        ]);

        // Create sample nested chains for enhanced rack
        for ($i = 0; $i < 5; $i++) {
            NestedChain::create([
                'rack_id' => $this->enhancedRack->id,
                'chain_identifier' => "enhanced_chain_{$i}",
                'xml_path' => "/Ableton/LiveSet/Tracks/Track[{$i}]/DeviceChain",
                'parent_chain_id' => null,
                'depth_level' => $i % 3,
                'device_count' => rand(1, 4),
                'is_empty' => false,
                'chain_type' => ['instrument', 'audio_effect', 'drum_pad'][array_rand(['instrument', 'audio_effect', 'drum_pad'])]
            ]);
        }
    }

    /**
     * Helper method to verify legacy rack state
     */
    private function verifyLegacyRackState(): void
    {
        $this->assertTrue($this->legacyRack->analysis_complete);
        $this->assertFalse($this->legacyRack->enhanced_analysis_complete);

        // Should have no enhanced analysis data
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $this->legacyRack->id)->first();
        $this->assertNull($enhancedAnalysis);

        $nestedChains = NestedChain::where('rack_id', $this->legacyRack->id)->get();
        $this->assertCount(0, $nestedChains);
    }

    /**
     * Helper method to test legacy rack API access
     */
    private function testLegacyRackApiAccess(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->legacyRack->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('analysis_complete', true)
            ->assertJsonPath('enhanced_analysis_complete', false);
    }

    /**
     * Helper method to trigger enhanced analysis on legacy rack
     */
    private function triggerEnhancedAnalysisOnLegacyRack(): void
    {
        $response = $this->postJson("/api/v1/racks/{$this->legacyRack->uuid}/analyze-nested-chains");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rack_uuid',
                'analysis_complete',
                'constitutional_compliant'
            ]);
    }

    /**
     * Helper method to verify legacy data preservation
     */
    private function verifyLegacyDataPreservation(): void
    {
        $updatedRack = Rack::where('uuid', $this->legacyRack->uuid)->first();

        // Original analysis should be preserved
        $this->assertTrue($updatedRack->analysis_complete);

        // Enhanced analysis should now be complete
        $this->assertTrue($updatedRack->enhanced_analysis_complete);

        // Should now have enhanced analysis data
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $updatedRack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
    }

    /**
     * Helper method to test mixed collection access
     */
    private function testMixedCollectionAccess(): void
    {
        $response = $this->getJson('/api/v1/racks');

        $response->assertStatus(200);

        $racks = $response->json('data');
        $uuids = collect($racks)->pluck('uuid');

        $this->assertContains($this->legacyRack->uuid, $uuids);
        $this->assertContains($this->enhancedRack->uuid, $uuids);
    }

    /**
     * Helper method to verify search and filtering compatibility
     */
    private function verifySearchAndFilteringCompatibility(): void
    {
        // Test search works across both types
        $searchResponse = $this->getJson('/api/v1/racks?search=Test');

        $searchResponse->assertStatus(200);
        $results = collect($searchResponse->json('data'))->pluck('uuid');

        $this->assertContains($this->legacyRack->uuid, $results);
        $this->assertContains($this->enhancedRack->uuid, $results);
    }

    /**
     * Helper method to test legacy to enhanced migration
     */
    private function testLegacyToEnhancedMigration(): void
    {
        // Legacy rack should now be enhanced after previous steps
        $migratedRack = Rack::where('uuid', $this->legacyRack->uuid)->first();

        $this->assertTrue($migratedRack->analysis_complete);
        $this->assertTrue($migratedRack->enhanced_analysis_complete);

        // Should have enhanced data
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $migratedRack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
        $this->assertTrue($enhancedAnalysis->constitutional_compliant);
    }

    /**
     * Helper method to create additional test racks
     */
    private function createAdditionalTestRacks(): void
    {
        // Create mix of legacy and enhanced racks for performance testing
        for ($i = 0; $i < 10; $i++) {
            $isEnhanced = $i % 2 === 0;

            $rack = Rack::factory()->create([
                'user_id' => $this->user->id,
                'uuid' => "perf-test-{$i}-" . uniqid(),
                'title' => "Performance Test Rack {$i}",
                'file_path' => "perf-test-{$i}.adg",
                'analysis_complete' => true,
                'enhanced_analysis_complete' => $isEnhanced
            ]);

            Storage::put($rack->file_path, file_get_contents($this->testRackPath));

            if ($isEnhanced) {
                EnhancedRackAnalysis::create([
                    'rack_id' => $rack->id,
                    'constitutional_compliant' => true,
                    'has_nested_chains' => true,
                    'total_chains_detected' => rand(2, 6),
                    'max_nesting_depth' => rand(1, 3),
                    'total_devices' => rand(5, 20),
                    'analysis_duration_ms' => rand(500, 2000),
                    'processed_at' => now()
                ]);
            }
        }
    }
}