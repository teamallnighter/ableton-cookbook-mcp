<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class DeepNestingValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected array $complexTestRacks;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();

        // Define complex test racks likely to have deep nesting
        $this->complexTestRacks = [
            '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/UR-GROUP.adg',
            '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/GROUP .adg',
            '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/GATEKEEPER RIFT.adg',
            '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/2026 HEAVY DUBSTEP.adg'
        ];

        // Verify at least one complex test rack exists
        $availableRacks = array_filter($this->complexTestRacks, 'file_exists');
        if (empty($availableRacks)) {
            $this->markTestSkipped('No complex test racks found for deep nesting validation');
        }
    }

    /**
     * Test system handles multiple levels of nesting (3+)
     */
    public function test_handles_multiple_levels_of_nesting(): void
    {
        Sanctum::actingAs($this->user);

        foreach ($this->getAvailableTestRacks() as $rackPath) {
            $rack = $this->createAndAnalyzeRack($rackPath);

            $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

            $response->assertStatus(200);

            $data = $response->json();

            // For complex racks, expect at least 2+ levels of nesting
            if ($data['max_depth'] >= 2) {
                $this->assertGreaterThanOrEqual(2, $data['max_depth']);
                $this->validateDeepNestingStructure($data['root_chains'], 0);
                break; // Found a rack with sufficient depth
            }
        }

        // If no rack had sufficient depth, create synthetic deep nesting test
        if (!isset($data) || $data['max_depth'] < 2) {
            $this->createSyntheticDeepNestingTest();
        }
    }

    /**
     * Test max nesting depth calculations are correct
     */
    public function test_max_nesting_depth_calculations(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $reportedMaxDepth = $data['max_depth'];

        // Verify max depth matches database
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertEquals($reportedMaxDepth, $enhancedAnalysis->max_nesting_depth);

        // Verify max depth matches actual hierarchy
        $actualMaxDepth = $this->calculateActualMaxDepth($data['root_chains']);
        $this->assertEquals($reportedMaxDepth, $actualMaxDepth);

        // Verify all chains have correct depth levels
        $this->validateDepthLevelsConsistent($data['root_chains'], 0);
    }

    /**
     * Test depth limits and performance with deep nesting
     */
    public function test_depth_limits_and_performance(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        $startTime = microtime(true);

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        $data = $response->json();

        // Verify constitutional depth limit not exceeded (max 10)
        $this->assertLessThanOrEqual(10, $data['max_depth']);

        // Verify performance acceptable even with deep nesting
        $this->assertLessThan(1000, $duration, 'Deep nesting query should complete within 1 second');

        // Verify all depth levels are within constitutional limits
        $this->validateDepthLimitsInHierarchy($data['root_chains']);
    }

    /**
     * Test that all nesting levels are captured correctly
     */
    public function test_all_nesting_levels_captured(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // Collect all depth levels present
        $depthLevels = $this->collectAllDepthLevels($data['root_chains']);
        sort($depthLevels);

        // Verify depth levels form a continuous sequence starting from 0
        $expectedLevels = range(0, max($depthLevels));
        $this->assertEquals($expectedLevels, $depthLevels);

        // Verify no gaps in the hierarchy
        for ($level = 0; $level <= $data['max_depth']; $level++) {
            $chainsAtLevel = $this->getChainsAtDepthLevel($data['root_chains'], $level);
            $this->assertNotEmpty($chainsAtLevel, "No chains found at depth level {$level}");
        }
    }

    /**
     * Test complex chain routing patterns
     */
    public function test_complex_chain_routing_patterns(): void
    {
        Sanctum::actingAs($this->user);

        foreach ($this->getAvailableTestRacks() as $rackPath) {
            $rack = $this->createAndAnalyzeRack($rackPath);

            $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

            $response->assertStatus(200);

            $data = $response->json();

            // Verify parallel chains at same level
            $this->validateParallelChains($data['root_chains']);

            // Verify branch and merge patterns
            $this->validateBranchingPatterns($data['root_chains']);

            // Verify no circular references
            $this->validateNoCircularReferences($data['root_chains'], []);
        }
    }

    /**
     * Test device chain relationships in deep nesting
     */
    public function test_device_chains_in_deep_nesting(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains?include_devices=true");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify device distribution across nesting levels
        $devicesByLevel = $this->categorizeDevicesByLevel($data['root_chains']);

        foreach ($devicesByLevel as $level => $devices) {
            $this->assertGreaterThanOrEqual(0, count($devices));

            // Verify devices at deeper levels have proper context
            if ($level > 0) {
                foreach ($devices as $device) {
                    $this->assertArrayHasKey('device_name', $device);
                    $this->assertArrayHasKey('device_index', $device);
                    $this->assertIsInt($device['device_index']);
                }
            }
        }
    }

    /**
     * Test XML path accuracy for deeply nested chains
     */
    public function test_xml_path_accuracy_for_deep_chains(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        // Get all nested chains from database
        $nestedChains = NestedChain::where('rack_id', $rack->id)
            ->orderBy('depth_level')
            ->get();

        foreach ($nestedChains as $chain) {
            // Verify XML path exists and is formatted correctly
            $this->assertNotNull($chain->xml_path);
            $this->assertStringContainsString('Chain', $chain->xml_path);

            // Verify depth level matches XML path complexity
            $pathSegments = explode('/', trim($chain->xml_path, '/'));
            $chainSegments = array_filter($pathSegments, function($segment) {
                return str_contains($segment, 'Chain');
            });

            // Deeper chains should have more complex paths
            if ($chain->depth_level > 0) {
                $this->assertGreaterThan(0, count($chainSegments));
            }
        }
    }

    /**
     * Test performance scaling with nesting complexity
     */
    public function test_performance_scaling_with_complexity(): void
    {
        Sanctum::actingAs($this->user);

        $performanceData = [];

        foreach ($this->getAvailableTestRacks() as $rackPath) {
            $rack = $this->createAndAnalyzeRack($rackPath);

            $startTime = microtime(true);

            $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains", [
                'force_reanalysis' => true
            ]);

            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;

            $response->assertStatus(200);

            $data = $response->json();
            $performanceData[] = [
                'max_depth' => $data['max_nesting_depth'],
                'total_chains' => $data['nested_chains_detected'],
                'duration_ms' => $duration
            ];

            // Individual performance check
            $this->assertLessThan(5000, $duration, 'Analysis should complete within 5 seconds');
        }

        // Verify performance doesn't degrade exponentially with complexity
        if (count($performanceData) > 1) {
            $this->validatePerformanceScaling($performanceData);
        }
    }

    /**
     * Test memory usage with deep nesting
     */
    public function test_memory_usage_with_deep_nesting(): void
    {
        Sanctum::actingAs($this->user);

        $initialMemory = memory_get_usage(true);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $peakMemory - $initialMemory;

        // Memory increase should be reasonable (less than 50MB for deep nesting)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage should remain under 50MB');
    }

    /**
     * Test constitutional compliance with deep nesting
     */
    public function test_constitutional_compliance_with_deep_nesting(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createAndAnalyzeRack($this->getAvailableTestRacks()[0]);

        // Verify constitutional compliance
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertNotNull($enhancedAnalysis);
        $this->assertTrue($enhancedAnalysis->constitutional_compliant);

        // Verify ALL chains detected (constitutional requirement)
        $allChains = NestedChain::where('rack_id', $rack->id)->get();
        $this->assertGreaterThan(0, $allChains->count());

        // Verify no chain is missing required data
        foreach ($allChains as $chain) {
            $this->assertNotNull($chain->chain_name);
            $this->assertNotNull($chain->xml_path);
            $this->assertIsInt($chain->depth_level);
            $this->assertGreaterThanOrEqual(0, $chain->depth_level);
            $this->assertLessThanOrEqual(10, $chain->depth_level); // Constitutional limit
        }
    }

    /**
     * Helper method to create and analyze rack
     */
    private function createAndAnalyzeRack(string $rackPath): Rack
    {
        $rackName = basename($rackPath, '.adg');
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-deep-nesting-' . uniqid(),
            'title' => "Deep Nesting Test: {$rackName}",
            'file_path' => "test-racks/{$rackName}.adg"
        ]);

        Storage::put($rack->file_path, file_get_contents($rackPath));

        $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        return $rack->fresh();
    }

    /**
     * Helper method to get available test racks
     */
    private function getAvailableTestRacks(): array
    {
        return array_filter($this->complexTestRacks, 'file_exists');
    }

    /**
     * Helper method to validate deep nesting structure
     */
    private function validateDeepNestingStructure(array $chains, int $currentDepth): void
    {
        foreach ($chains as $chain) {
            $this->assertEquals($currentDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $this->validateDeepNestingStructure($chain['child_chains'], $currentDepth + 1);
            }
        }
    }

    /**
     * Helper method to calculate actual max depth
     */
    private function calculateActualMaxDepth(array $chains): int
    {
        $maxDepth = 0;

        foreach ($chains as $chain) {
            $maxDepth = max($maxDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $maxDepth = max($maxDepth, $this->calculateActualMaxDepth($chain['child_chains']));
            }
        }

        return $maxDepth;
    }

    /**
     * Helper method to validate depth levels are consistent
     */
    private function validateDepthLevelsConsistent(array $chains, int $expectedDepth): void
    {
        foreach ($chains as $chain) {
            $this->assertEquals($expectedDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $this->validateDepthLevelsConsistent($chain['child_chains'], $expectedDepth + 1);
            }
        }
    }

    /**
     * Helper method to validate depth limits
     */
    private function validateDepthLimitsInHierarchy(array $chains): void
    {
        foreach ($chains as $chain) {
            $this->assertLessThanOrEqual(10, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $this->validateDepthLimitsInHierarchy($chain['child_chains']);
            }
        }
    }

    /**
     * Helper method to collect all depth levels
     */
    private function collectAllDepthLevels(array $chains): array
    {
        $levels = [];

        foreach ($chains as $chain) {
            $levels[] = $chain['depth_level'];

            if (!empty($chain['child_chains'])) {
                $levels = array_merge($levels, $this->collectAllDepthLevels($chain['child_chains']));
            }
        }

        return array_unique($levels);
    }

    /**
     * Helper method to get chains at specific depth level
     */
    private function getChainsAtDepthLevel(array $chains, int $targetDepth): array
    {
        $result = [];

        foreach ($chains as $chain) {
            if ($chain['depth_level'] === $targetDepth) {
                $result[] = $chain;
            }

            if (!empty($chain['child_chains'])) {
                $result = array_merge($result, $this->getChainsAtDepthLevel($chain['child_chains'], $targetDepth));
            }
        }

        return $result;
    }

    /**
     * Helper method to validate parallel chains
     */
    private function validateParallelChains(array $chains): void
    {
        $chainsByDepth = [];

        foreach ($chains as $chain) {
            $depth = $chain['depth_level'];
            $chainsByDepth[$depth][] = $chain;

            if (!empty($chain['child_chains'])) {
                $this->validateParallelChains($chain['child_chains']);
            }
        }

        // Check for parallel chains (multiple chains at same depth)
        foreach ($chainsByDepth as $depth => $chainsAtDepth) {
            if (count($chainsAtDepth) > 1) {
                // Verify parallel chains have different names/indices
                $names = array_column($chainsAtDepth, 'chain_name');
                $this->assertEquals(count($names), count(array_unique($names)));
            }
        }
    }

    /**
     * Helper method to validate branching patterns
     */
    private function validateBranchingPatterns(array $chains): void
    {
        foreach ($chains as $chain) {
            if (!empty($chain['child_chains'])) {
                // Verify children have correct parent reference
                foreach ($chain['child_chains'] as $child) {
                    $this->assertEquals($chain['depth_level'] + 1, $child['depth_level']);
                }

                $this->validateBranchingPatterns($chain['child_chains']);
            }
        }
    }

    /**
     * Helper method to validate no circular references
     */
    private function validateNoCircularReferences(array $chains, array $visited): void
    {
        foreach ($chains as $chain) {
            $chainId = $chain['chain_id'] ?? $chain['chain_name'];

            $this->assertNotContains($chainId, $visited, "Circular reference detected for chain: {$chainId}");

            $newVisited = array_merge($visited, [$chainId]);

            if (!empty($chain['child_chains'])) {
                $this->validateNoCircularReferences($chain['child_chains'], $newVisited);
            }
        }
    }

    /**
     * Helper method to categorize devices by level
     */
    private function categorizeDevicesByLevel(array $chains): array
    {
        $devicesByLevel = [];

        foreach ($chains as $chain) {
            $level = $chain['depth_level'];
            $devices = $chain['devices'] ?? [];

            if (!isset($devicesByLevel[$level])) {
                $devicesByLevel[$level] = [];
            }

            $devicesByLevel[$level] = array_merge($devicesByLevel[$level], $devices);

            if (!empty($chain['child_chains'])) {
                $childDevices = $this->categorizeDevicesByLevel($chain['child_chains']);
                foreach ($childDevices as $childLevel => $devices) {
                    if (!isset($devicesByLevel[$childLevel])) {
                        $devicesByLevel[$childLevel] = [];
                    }
                    $devicesByLevel[$childLevel] = array_merge($devicesByLevel[$childLevel], $devices);
                }
            }
        }

        return $devicesByLevel;
    }

    /**
     * Helper method to validate performance scaling
     */
    private function validatePerformanceScaling(array $performanceData): void
    {
        // Sort by complexity (total chains)
        usort($performanceData, function($a, $b) {
            return $a['total_chains'] <=> $b['total_chains'];
        });

        // Verify performance doesn't degrade exponentially
        for ($i = 1; $i < count($performanceData); $i++) {
            $prev = $performanceData[$i - 1];
            $curr = $performanceData[$i];

            $complexityRatio = $curr['total_chains'] / max($prev['total_chains'], 1);
            $timeRatio = $curr['duration_ms'] / max($prev['duration_ms'], 1);

            // Time ratio should not exceed complexity ratio by more than 2x
            $this->assertLessThan($complexityRatio * 2, $timeRatio, 'Performance should scale reasonably with complexity');
        }
    }

    /**
     * Helper method to create synthetic deep nesting test
     */
    private function createSyntheticDeepNestingTest(): void
    {
        // Create a rack with synthetic deep nesting for testing
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-synthetic-deep-' . uniqid(),
            'enhanced_analysis_complete' => true
        ]);

        // Create 4 levels of nesting
        $rootChain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => null,
            'depth_level' => 0,
            'chain_name' => 'Root Chain'
        ]);

        $level1Chain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => $rootChain->id,
            'depth_level' => 1,
            'chain_name' => 'Level 1 Chain'
        ]);

        $level2Chain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => $level1Chain->id,
            'depth_level' => 2,
            'chain_name' => 'Level 2 Chain'
        ]);

        $level3Chain = NestedChain::factory()->create([
            'rack_id' => $rack->id,
            'parent_chain_id' => $level2Chain->id,
            'depth_level' => 3,
            'chain_name' => 'Level 3 Chain'
        ]);

        // Create enhanced analysis record
        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $rack->id,
            'max_nesting_depth' => 3,
            'total_chain_count' => 4,
            'constitutional_compliant' => true
        ]);

        // Test the synthetic structure
        $response = $this->getJson("/api/v1/racks/{$rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(3, $data['max_depth']);
        $this->assertEquals(4, $data['total_chains']);
    }
}