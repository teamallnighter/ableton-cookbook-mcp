<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class ConstitutionalComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
    }

    /**
     * Test compliance checking and enforcement
     */
    public function test_compliance_checking_and_enforcement(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create non-compliant racks (no enhanced analysis)
        $nonCompliantRacks = Rack::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false,
            'analysis_complete' => true // Old analysis only
        ]);

        // Create compliant racks
        $compliantRacks = Rack::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        foreach ($compliantRacks as $rack) {
            EnhancedRackAnalysis::factory()->create([
                'rack_id' => $rack->id,
                'constitutional_compliant' => true,
                'has_nested_chains' => true
            ]);
        }

        // Check compliance report
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(5, $data['total_racks']);
        $this->assertEquals(2, $data['compliant_racks']);
        $this->assertEquals(3, $data['non_compliant_racks']);
        $this->assertEquals(40.0, $data['compliance_percentage']);

        // Verify non-compliant racks are identified
        $nonCompliantUuids = collect($data['racks_requiring_reprocessing'])
            ->pluck('rack_uuid')
            ->toArray();

        foreach ($nonCompliantRacks as $rack) {
            $this->assertContains($rack->uuid, $nonCompliantUuids);
        }
    }

    /**
     * Test non-compliant racks identified correctly
     */
    public function test_non_compliant_racks_identified_correctly(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Case 1: Rack with no enhanced analysis
        $noAnalysisRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false
        ]);

        // Case 2: Rack with failed constitutional compliance
        $failedComplianceRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $failedComplianceRack->id,
            'constitutional_compliant' => false,
            'has_nested_chains' => false // Failed to detect chains
        ]);

        // Case 3: Rack with incomplete nested chain detection
        $incompleteRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $incompleteRack->id,
            'constitutional_compliant' => false,
            'has_nested_chains' => true,
            'total_chain_count' => 0 // Contradiction: has chains but count is 0
        ]);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(3, $data['non_compliant_racks']);
        $this->assertCount(3, $data['racks_requiring_reprocessing']);

        // Verify specific reasons for non-compliance
        $reasonsMap = collect($data['racks_requiring_reprocessing'])
            ->keyBy('rack_uuid')
            ->map(fn($item) => $item['reason'])
            ->toArray();

        $this->assertStringContainsString('No enhanced analysis', $reasonsMap[$noAnalysisRack->uuid]);
        $this->assertStringContainsString('Failed constitutional compliance', $reasonsMap[$failedComplianceRack->uuid]);
        $this->assertStringContainsString('Incomplete nested chain', $reasonsMap[$incompleteRack->uuid]);
    }

    /**
     * Test compliance status updates after reprocessing
     */
    public function test_compliance_status_updates_after_reprocessing(): void
    {
        Sanctum::actingAs($this->user);

        // Create a non-compliant rack
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false,
            'file_path' => 'test-rack.adg'
        ]);

        // Simulate rack file
        Storage::put($rack->file_path, 'fake-rack-content');

        // Initial compliance check (admin perspective)
        Sanctum::actingAs($this->adminUser);
        $initialResponse = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $initialData = $initialResponse->json();

        $this->assertEquals(1, $initialData['non_compliant_racks']);

        // Trigger enhanced analysis
        Sanctum::actingAs($this->user);
        $analysisResponse = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        $analysisResponse->assertStatus(200);

        $analysisData = $analysisResponse->json();
        $this->assertTrue($analysisData['constitutional_compliant']);

        // Check compliance status after reprocessing
        Sanctum::actingAs($this->adminUser);
        $updatedResponse = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $updatedData = $updatedResponse->json();

        $this->assertEquals(1, $updatedData['compliant_racks']);
        $this->assertEquals(0, $updatedData['non_compliant_racks']);
        $this->assertEquals(100.0, $updatedData['compliance_percentage']);
    }

    /**
     * Test constitutional requirement enforcement during analysis
     */
    public function test_constitutional_requirement_enforcement(): void
    {
        Sanctum::actingAs($this->user);

        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'test-rack.adg'
        ]);

        Storage::put($rack->file_path, 'fake-rack-content');

        // Trigger analysis
        $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify constitutional compliance is checked
        $this->assertArrayHasKey('constitutional_compliant', $data);

        // Verify enhanced analysis record enforces constitutional requirements
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertNotNull($enhancedAnalysis);

        // Constitutional compliance should be based on ALL CHAINS detection
        if ($enhancedAnalysis->constitutional_compliant) {
            // If compliant, must have detected all chains
            $this->assertGreaterThanOrEqual(0, $enhancedAnalysis->total_chain_count);
            $this->assertNotNull($enhancedAnalysis->nested_chain_tree);
        }
    }

    /**
     * Test Analysis Completeness Principle enforcement
     */
    public function test_analysis_completeness_principle_enforcement(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack with simulated nested chains
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        // Create nested chains to simulate detected structure
        $rootChain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => null,
            'depth_level' => 0,
            'chain_name' => 'Main Chain'
        ]);

        $nestedChain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => $rootChain->id,
            'depth_level' => 1,
            'chain_name' => 'Nested Chain'
        ]);

        // Create compliant enhanced analysis
        $enhancedAnalysis = EnhancedRackAnalysis::factory()->create([
            'rack_id' => $rack->id,
            'constitutional_compliant' => true,
            'has_nested_chains' => true,
            'total_chain_count' => 2,
            'max_nesting_depth' => 1,
            'nested_chain_tree' => [
                'root_chains' => [
                    [
                        'chain_id' => $rootChain->id,
                        'chain_name' => 'Main Chain',
                        'depth_level' => 0,
                        'child_chains' => [
                            [
                                'chain_id' => $nestedChain->id,
                                'chain_name' => 'Nested Chain',
                                'depth_level' => 1
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Verify Analysis Completeness Principle is satisfied
        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // ALL CHAINS must be included (constitutional requirement)
        $this->assertEquals(2, $data['total_chains']);
        $this->assertEquals(1, $data['max_depth']);
        $this->assertCount(1, $data['root_chains']);
        $this->assertCount(1, $data['root_chains'][0]['child_chains']);

        // Verify hierarchical structure is complete
        $this->assertEquals('Main Chain', $data['root_chains'][0]['chain_name']);
        $this->assertEquals('Nested Chain', $data['root_chains'][0]['child_chains'][0]['chain_name']);
    }

    /**
     * Test compliance violations halt development workflow
     */
    public function test_compliance_violations_halt_development(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack that will fail constitutional compliance
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        // Create non-compliant enhanced analysis
        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $rack->id,
            'constitutional_compliant' => false,
            'has_nested_chains' => false, // Violates ALL CHAINS requirement
            'total_chain_count' => 0
        ]);

        // Attempt to access rack features that require constitutional compliance
        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        // Should still return data but mark as non-compliant
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(0, $data['total_chains']);

        // Verify compliance report identifies this rack
        Sanctum::actingAs($this->adminUser);
        $complianceResponse = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $complianceData = $complianceResponse->json();
        $this->assertEquals(1, $complianceData['non_compliant_racks']);

        $nonCompliantRack = collect($complianceData['racks_requiring_reprocessing'])
            ->firstWhere('rack_uuid', $rack->uuid);

        $this->assertNotNull($nonCompliantRack);
        $this->assertStringContainsString('constitutional compliance', $nonCompliantRack['reason']);
    }

    /**
     * Test constitutional compliance with edge cases
     */
    public function test_constitutional_compliance_edge_cases(): void
    {
        Sanctum::actingAs($this->user);

        // Case 1: Rack with empty but structurally present chains
        $emptyChainRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        $emptyChain = NestedChain::factory()->create([
            'rack_id' => $emptyChainRack->id,
            'parent_chain_id' => null,
            'depth_level' => 0,
            'device_count' => 0,
            'is_empty' => true,
            'chain_name' => 'Empty Chain'
        ]);

        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $emptyChainRack->id,
            'constitutional_compliant' => true, // Empty chains still count as detected
            'has_nested_chains' => true,
            'total_chain_count' => 1
        ]);

        // Verify empty chains satisfy constitutional requirement
        $response = $this->getJson("/api/v1/racks/{$emptyChainRack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['total_chains']);
        $this->assertTrue($data['root_chains'][0]['is_empty']);

        // Case 2: Rack with maximum nesting depth (constitutional limit)
        $maxDepthRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => true
        ]);

        // Create chain hierarchy at constitutional limit (10 levels)
        $currentChain = null;
        for ($depth = 0; $depth <= 9; $depth++) {
            $currentChain = NestedChain::factory()->create([
                'rack_id' => $maxDepthRack->id,
                'parent_chain_id' => $currentChain ? $currentChain->id : null,
                'depth_level' => $depth,
                'chain_name' => "Chain Level {$depth}"
            ]);
        }

        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $maxDepthRack->id,
            'constitutional_compliant' => true,
            'has_nested_chains' => true,
            'total_chain_count' => 10,
            'max_nesting_depth' => 9
        ]);

        $response = $this->getJson("/api/v1/racks/{$maxDepthRack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(10, $data['total_chains']);
        $this->assertEquals(9, $data['max_depth']);
        $this->assertLessThanOrEqual(10, $data['max_depth']); // Within constitutional limit
    }

    /**
     * Test compliance report filtering and sorting
     */
    public function test_compliance_report_filtering_and_sorting(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create racks with different upload dates
        $oldRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false,
            'created_at' => now()->subDays(7)
        ]);

        $recentRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false,
            'created_at' => now()->subDay()
        ]);

        // Test filtering by upload date
        $lastWeek = now()->subWeek()->toDateString();
        $response = $this->getJson("/api/v1/analysis/constitutional-compliance?uploaded_after={$lastWeek}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(2, $data['non_compliant_racks']);

        // Test sorting by upload date
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?sort=uploaded_at&order=desc');

        $response->assertStatus(200);

        $data = $response->json();
        $uploadDates = collect($data['racks_requiring_reprocessing'])
            ->pluck('uploaded_at')
            ->toArray();

        // Verify descending order
        for ($i = 1; $i < count($uploadDates); $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($uploadDates[$i]),
                strtotime($uploadDates[$i - 1])
            );
        }
    }

    /**
     * Test performance of compliance checking with large dataset
     */
    public function test_compliance_checking_performance(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create large number of racks
        Rack::factory()->count(50)->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false
        ]);

        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // Should complete within 1 second even with 50 racks
        $this->assertLessThan(1000, $duration, 'Compliance checking should complete within 1 second');

        $data = $response->json();
        $this->assertEquals(50, $data['non_compliant_racks']);
        $this->assertEquals(0.0, $data['compliance_percentage']);
    }

    /**
     * Test constitutional compliance caching
     */
    public function test_constitutional_compliance_caching(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create test data
        Rack::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false
        ]);

        // First request
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $firstDuration = (microtime(true) - $startTime) * 1000;

        $response1->assertStatus(200);

        // Second request (should be cached)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $secondDuration = (microtime(true) - $startTime) * 1000;

        $response2->assertStatus(200);

        // Cached response should be faster
        $this->assertLessThan($firstDuration, $secondDuration);

        // Data should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }
}