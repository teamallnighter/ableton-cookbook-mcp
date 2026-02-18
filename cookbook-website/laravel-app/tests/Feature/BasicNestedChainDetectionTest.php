<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use App\Services\AbletonRackAnalyzer;
use App\Services\NestedChainAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class BasicNestedChainDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $testRackPath;

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
    }

    /**
     * Test complete workflow: upload → analyze → retrieve → validate
     */
    public function test_complete_nested_chain_detection_workflow(): void
    {
        Sanctum::actingAs($this->user);

        // Step 1: Upload rack file
        $uploadedFile = new UploadedFile(
            $this->testRackPath,
            'WIERDO.adg',
            'application/octet-stream',
            null,
            true
        );

        $uploadResponse = $this->postJson('/api/v1/racks', [
            'title' => 'WIERDO Test Rack',
            'description' => 'Test rack for nested chain detection',
            'file' => $uploadedFile,
            'tags' => ['test', 'nested-chains']
        ]);

        $uploadResponse->assertStatus(201);
        $rackUuid = $uploadResponse->json('uuid');

        // Step 2: Trigger enhanced nested chain analysis
        $analysisResponse = $this->postJson("/api/v1/racks/{$rackUuid}/analyze-nested-chains");

        $analysisResponse->assertStatus(200)
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

        $analysisData = $analysisResponse->json();

        // Step 3: Verify analysis results
        $this->assertTrue($analysisData['analysis_complete']);
        $this->assertTrue($analysisData['constitutional_compliant']);
        $this->assertGreaterThan(0, $analysisData['nested_chains_detected']);
        $this->assertGreaterThan(0, $analysisData['total_devices']);
        $this->assertLessThan(5000, $analysisData['analysis_duration_ms']); // < 5 seconds

        // Step 4: Retrieve full nested chain hierarchy
        $hierarchyResponse = $this->getJson("/api/v1/racks/{$rackUuid}/nested-chains");

        $hierarchyResponse->assertStatus(200)
            ->assertJsonStructure([
                'rack_uuid',
                'total_chains',
                'max_depth',
                'root_chains'
            ]);

        $hierarchyData = $hierarchyResponse->json();

        // Step 5: Validate hierarchy structure
        $this->assertEquals($rackUuid, $hierarchyData['rack_uuid']);
        $this->assertEquals($analysisData['nested_chains_detected'], $hierarchyData['total_chains']);
        $this->assertEquals($analysisData['max_nesting_depth'], $hierarchyData['max_depth']);
        $this->assertNotEmpty($hierarchyData['root_chains']);

        // Step 6: Verify database state
        $rack = Rack::where('uuid', $rackUuid)->first();
        $this->assertNotNull($rack);
        $this->assertTrue($rack->enhanced_analysis_complete);

        // Check NestedChain records exist
        $nestedChains = NestedChain::where('rack_id', $rack->id)->get();
        $this->assertCount($analysisData['nested_chains_detected'], $nestedChains);

        // Check EnhancedRackAnalysis record
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
        $this->assertTrue($enhancedAnalysis->constitutional_compliant);
        $this->assertTrue($enhancedAnalysis->has_nested_chains);
    }

    /**
     * Test that hierarchy correctly represents parent-child relationships
     */
    public function test_hierarchy_represents_parent_child_relationships(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack and analyze
        $rack = $this->createAndAnalyzeTestRack();

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $this->validateHierarchyStructure($data['root_chains']);
    }

    /**
     * Test that all chains are detected per constitutional requirement
     */
    public function test_all_chains_detected_constitutional_requirement(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeTestRack();

        // Verify constitutional compliance
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
        $this->assertTrue($enhancedAnalysis->constitutional_compliant);

        // Verify all chains detected
        $nestedChains = NestedChain::where('rack_id', $rack->id)->get();
        $this->assertGreaterThan(0, $nestedChains->count());

        // Verify no chain is marked as incomplete
        foreach ($nestedChains as $chain) {
            $this->assertNotNull($chain->xml_path);
            $this->assertIsInt($chain->depth_level);
            $this->assertGreaterThanOrEqual(0, $chain->device_count);
        }
    }

    /**
     * Test analysis performance meets sub-5 second requirement
     */
    public function test_analysis_performance_under_five_seconds(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'test-rack.adg'
        ]);

        // Copy test rack to storage
        Storage::put($rack->file_path, file_get_contents($this->testRackPath));

        $startTime = microtime(true);

        $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        $endTime = microtime(true);
        $actualDuration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);

        $responseData = $response->json();
        $reportedDuration = $responseData['analysis_duration_ms'];

        // Both actual and reported duration should be under 5 seconds
        $this->assertLessThan(5000, $actualDuration);
        $this->assertLessThan(5000, $reportedDuration);
    }

    /**
     * Test device detection within chains
     */
    public function test_device_detection_within_chains(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeTestRack();

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains?include_devices=true");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify devices are included
        foreach ($data['root_chains'] as $chain) {
            if ($chain['device_count'] > 0) {
                $this->assertArrayHasKey('devices', $chain);
                $this->assertCount($chain['device_count'], $chain['devices']);

                // Verify device structure
                foreach ($chain['devices'] as $device) {
                    $this->assertArrayHasKey('device_name', $device);
                    $this->assertArrayHasKey('device_type', $device);
                    $this->assertArrayHasKey('device_index', $device);
                    $this->assertArrayHasKey('is_max_for_live', $device);
                    $this->assertArrayHasKey('parameters', $device);
                }
            }

            // Recursively check child chains
            if (!empty($chain['child_chains'])) {
                $this->validateDevicesInChildChains($chain['child_chains']);
            }
        }
    }

    /**
     * Test empty chains are handled correctly
     */
    public function test_empty_chains_handled_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeTestRack();

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // Find any empty chains
        $emptyChains = $this->findEmptyChains($data['root_chains']);

        foreach ($emptyChains as $emptyChain) {
            $this->assertTrue($emptyChain['is_empty']);
            $this->assertEquals(0, $emptyChain['device_count']);
            $this->assertEmpty($emptyChain['devices'] ?? []);
        }
    }

    /**
     * Test max depth calculation is accurate
     */
    public function test_max_depth_calculation_accurate(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeTestRack();

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $reportedMaxDepth = $data['max_depth'];

        // Calculate actual max depth from hierarchy
        $actualMaxDepth = $this->calculateMaxDepth($data['root_chains']);

        $this->assertEquals($reportedMaxDepth, $actualMaxDepth);
    }

    /**
     * Test chain type classification
     */
    public function test_chain_type_classification(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeTestRack();

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify chain types are classified
        $validChainTypes = ['instrument', 'audio_effect', 'drum_pad', 'midi_effect', 'unknown'];

        foreach ($data['root_chains'] as $chain) {
            $this->assertContains($chain['chain_type'], $validChainTypes);
            $this->validateChainTypesInChildren($chain['child_chains'] ?? [], $validChainTypes);
        }
    }

    /**
     * Test backward compatibility with existing racks
     */
    public function test_backward_compatibility_with_existing_racks(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack with existing analysis (without enhanced analysis)
        $existingRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'existing-rack.adg',
            'enhanced_analysis_complete' => false,
            'analysis_complete' => true // Old analysis
        ]);

        Storage::put($existingRack->file_path, file_get_contents($this->testRackPath));

        // Trigger enhanced analysis
        $response = $this->postJson("/api/v1/racks/{$existingRack->uuid}/analyze-nested-chains");

        $response->assertStatus(200);

        // Verify existing data preserved
        $rack = Rack::where('uuid', $existingRack->uuid)->first();
        $this->assertTrue($rack->analysis_complete); // Original analysis preserved
        $this->assertTrue($rack->enhanced_analysis_complete); // Enhanced analysis added
    }

    /**
     * Helper method to create and analyze test rack
     */
    private function createAndAnalyzeTestRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-basic-detection-' . uniqid(),
            'file_path' => 'test-rack.adg'
        ]);

        Storage::put($rack->file_path, file_get_contents($this->testRackPath));

        $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        return $rack->fresh();
    }

    /**
     * Helper method to validate hierarchy structure
     */
    private function validateHierarchyStructure(array $chains, int $expectedDepth = 0): void
    {
        foreach ($chains as $chain) {
            $this->assertEquals($expectedDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $this->validateHierarchyStructure($chain['child_chains'], $expectedDepth + 1);
            }
        }
    }

    /**
     * Helper method to validate devices in child chains
     */
    private function validateDevicesInChildChains(array $childChains): void
    {
        foreach ($childChains as $childChain) {
            if ($childChain['device_count'] > 0) {
                $this->assertArrayHasKey('devices', $childChain);
                $this->assertCount($childChain['device_count'], $childChain['devices']);
            }

            if (!empty($childChain['child_chains'])) {
                $this->validateDevicesInChildChains($childChain['child_chains']);
            }
        }
    }

    /**
     * Helper method to find empty chains
     */
    private function findEmptyChains(array $chains): array
    {
        $emptyChains = [];

        foreach ($chains as $chain) {
            if ($chain['is_empty']) {
                $emptyChains[] = $chain;
            }

            if (!empty($chain['child_chains'])) {
                $emptyChains = array_merge($emptyChains, $this->findEmptyChains($chain['child_chains']));
            }
        }

        return $emptyChains;
    }

    /**
     * Helper method to calculate max depth
     */
    private function calculateMaxDepth(array $chains): int
    {
        $maxDepth = 0;

        foreach ($chains as $chain) {
            $maxDepth = max($maxDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $maxDepth = max($maxDepth, $this->calculateMaxDepth($chain['child_chains']));
            }
        }

        return $maxDepth;
    }

    /**
     * Helper method to validate chain types in children
     */
    private function validateChainTypesInChildren(array $children, array $validTypes): void
    {
        foreach ($children as $child) {
            $this->assertContains($child['chain_type'], $validTypes);

            if (!empty($child['child_chains'])) {
                $this->validateChainTypesInChildren($child['child_chains'], $validTypes);
            }
        }
    }
}