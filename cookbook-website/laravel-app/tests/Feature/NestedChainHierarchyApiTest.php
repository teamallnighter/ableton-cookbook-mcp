<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\ChainDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class NestedChainHierarchyApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Rack $rack;
    protected NestedChain $rootChain;
    protected NestedChain $nestedChain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-rack-uuid-456',
            'title' => 'Test Rack with Hierarchy',
            'enhanced_analysis_complete' => true
        ]);

        // Create test chain hierarchy
        $this->rootChain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => null,
            'chain_name' => 'Root Chain',
            'depth_level' => 0,
            'chain_type' => 'instrument',
            'device_count' => 2
        ]);

        $this->nestedChain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => $this->rootChain->id,
            'chain_name' => 'Nested Chain',
            'depth_level' => 1,
            'chain_type' => 'audio_effect',
            'device_count' => 1
        ]);

        // Create test devices
        ChainDevice::factory()->count(2)->create([
            'nested_chain_id' => $this->rootChain->id
        ]);

        ChainDevice::factory()->create([
            'nested_chain_id' => $this->nestedChain->id
        ]);
    }

    /**
     * Test successful nested chain hierarchy retrieval
     */
    public function test_can_retrieve_nested_chain_hierarchy(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rack_uuid',
                'total_chains',
                'max_depth',
                'root_chains' => [
                    '*' => [
                        'chain_id',
                        'chain_name',
                        'chain_type',
                        'depth_level',
                        'device_count',
                        'is_empty',
                        'devices' => [
                            '*' => [
                                'device_name',
                                'device_type',
                                'device_index',
                                'is_max_for_live',
                                'parameters'
                            ]
                        ],
                        'child_chains'
                    ]
                ]
            ]);
    }

    /**
     * Test include_devices query parameter
     */
    public function test_include_devices_parameter_controls_device_data(): void
    {
        // Test with devices included (default)
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains?include_devices=true");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'root_chains' => [
                    '*' => ['devices']
                ]
            ]);

        // Test with devices excluded
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains?include_devices=false");

        $response->assertStatus(200)
            ->assertJsonMissing(['devices']);
    }

    /**
     * Test max_depth query parameter
     */
    public function test_max_depth_parameter_limits_hierarchy_depth(): void
    {
        // Create deeper nesting
        $deepChain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => $this->nestedChain->id,
            'depth_level' => 2,
            'chain_name' => 'Deep Chain'
        ]);

        // Test depth limit of 1
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains?max_depth=1");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['max_depth']);

        // Verify no chains deeper than level 1 are returned
        $this->assertLessThanOrEqual(1, $this->getMaxDepthFromResponse($data['root_chains']));
    }

    /**
     * Test 404 response for non-existent rack
     */
    public function test_returns_404_for_non_existent_rack(): void
    {
        $response = $this->getJson('/api/v1/racks/non-existent-uuid/nested-chains');

        $response->assertNotFound();
    }

    /**
     * Test 404 response for rack without nested chain analysis
     */
    public function test_returns_404_for_rack_without_nested_analysis(): void
    {
        $rackWithoutAnalysis = Rack::factory()->create([
            'user_id' => $this->user->id,
            'enhanced_analysis_complete' => false
        ]);

        $response = $this->getJson("/api/v1/racks/{$rackWithoutAnalysis->uuid}/nested-chains");

        $response->assertNotFound()
            ->assertJsonPath('message', 'No nested chain analysis available for this rack');
    }

    /**
     * Test hierarchy structure with multiple nesting levels
     */
    public function test_correctly_structures_multi_level_hierarchy(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();

        // Verify hierarchy structure
        $this->assertEquals(2, $data['total_chains']);
        $this->assertEquals(1, $data['max_depth']);
        $this->assertCount(1, $data['root_chains']); // One root chain

        $rootChain = $data['root_chains'][0];
        $this->assertEquals('Root Chain', $rootChain['chain_name']);
        $this->assertEquals(0, $rootChain['depth_level']);
        $this->assertCount(1, $rootChain['child_chains']); // One nested chain

        $nestedChain = $rootChain['child_chains'][0];
        $this->assertEquals('Nested Chain', $nestedChain['chain_name']);
        $this->assertEquals(1, $nestedChain['depth_level']);
    }

    /**
     * Test device information is included correctly
     */
    public function test_device_information_included_correctly(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains?include_devices=true");

        $response->assertStatus(200);

        $data = $response->json();
        $rootChain = $data['root_chains'][0];

        $this->assertEquals(2, $rootChain['device_count']);
        $this->assertCount(2, $rootChain['devices']);

        $device = $rootChain['devices'][0];
        $this->assertArrayHasKey('device_name', $device);
        $this->assertArrayHasKey('device_type', $device);
        $this->assertArrayHasKey('device_index', $device);
        $this->assertArrayHasKey('is_max_for_live', $device);
        $this->assertArrayHasKey('parameters', $device);
    }

    /**
     * Test empty chains are handled correctly
     */
    public function test_empty_chains_handled_correctly(): void
    {
        // Create an empty chain
        $emptyChain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => null,
            'chain_name' => 'Empty Chain',
            'depth_level' => 0,
            'device_count' => 0,
            'is_empty' => true
        ]);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(3, $data['total_chains']); // Now includes empty chain

        // Find the empty chain in response
        $emptyChainData = collect($data['root_chains'])
            ->firstWhere('chain_name', 'Empty Chain');

        $this->assertNotNull($emptyChainData);
        $this->assertTrue($emptyChainData['is_empty']);
        $this->assertEquals(0, $emptyChainData['device_count']);
    }

    /**
     * Test pagination for large chain hierarchies
     */
    public function test_pagination_for_large_hierarchies(): void
    {
        // Create many root chains to test pagination
        NestedChain::factory()->count(25)->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => null,
            'depth_level' => 0
        ]);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains?limit=10&offset=0");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertLessThanOrEqual(10, count($data['root_chains']));
        $this->assertArrayHasKey('pagination', $data);
    }

    /**
     * Test authentication is not required for public endpoint
     */
    public function test_authentication_not_required_for_public_rack(): void
    {
        // Make rack public
        $this->rack->is_private = false;
        $this->rack->save();

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertStatus(200);
    }

    /**
     * Test authentication required for private rack
     */
    public function test_authentication_required_for_private_rack(): void
    {
        // Make rack private
        $this->rack->is_private = true;
        $this->rack->save();

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertUnauthorized();

        // Test with authentication
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains");

        $response->assertStatus(200);
    }

    /**
     * Helper method to get max depth from response hierarchy
     */
    private function getMaxDepthFromResponse(array $chains, int $currentMaxDepth = 0): int
    {
        foreach ($chains as $chain) {
            $currentMaxDepth = max($currentMaxDepth, $chain['depth_level']);

            if (!empty($chain['child_chains'])) {
                $currentMaxDepth = max(
                    $currentMaxDepth,
                    $this->getMaxDepthFromResponse($chain['child_chains'], $currentMaxDepth)
                );
            }
        }

        return $currentMaxDepth;
    }
}