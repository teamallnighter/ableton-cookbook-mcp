<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\ChainDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class NestedChainDetailApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Rack $rack;
    protected NestedChain $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'test-rack-uuid-789',
            'title' => 'Test Rack for Chain Details',
            'enhanced_analysis_complete' => true
        ]);

        $this->chain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => null,
            'chain_name' => 'Test Chain Detail',
            'chain_type' => 'instrument',
            'depth_level' => 0,
            'device_count' => 3,
            'xml_path' => '/DeviceChain/Devices/InstrumentRack/Chains/Chain[@Id="1"]',
            'analysis_metadata' => [
                'complexity_score' => 7.5,
                'processing_type' => 'instrument',
                'macro_mappings' => 2
            ]
        ]);

        // Create devices for the chain
        ChainDevice::factory()->count(3)->create([
            'nested_chain_id' => $this->chain->id
        ]);

        // Create a child chain
        NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => $this->chain->id,
            'chain_name' => 'Child Chain',
            'depth_level' => 1
        ]);
    }

    /**
     * Test successful chain detail retrieval
     */
    public function test_can_retrieve_specific_chain_details(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'chain_id',
                'chain_name',
                'chain_type',
                'depth_level',
                'device_count',
                'is_empty',
                'xml_path',
                'parent_chain_id',
                'analysis_metadata',
                'devices' => [
                    '*' => [
                        'device_name',
                        'device_type',
                        'device_index',
                        'is_max_for_live',
                        'parameters'
                    ]
                ],
                'child_chains' => [
                    '*' => [
                        'chain_id',
                        'chain_name',
                        'chain_type',
                        'depth_level',
                        'device_count',
                        'is_empty'
                    ]
                ]
            ]);
    }

    /**
     * Test chain detail includes all expected data
     */
    public function test_chain_detail_includes_all_expected_data(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('chain_name', 'Test Chain Detail')
            ->assertJsonPath('chain_type', 'instrument')
            ->assertJsonPath('depth_level', 0)
            ->assertJsonPath('device_count', 3)
            ->assertJsonPath('xml_path', '/DeviceChain/Devices/InstrumentRack/Chains/Chain[@Id="1"]')
            ->assertJsonPath('parent_chain_id', null)
            ->assertJsonPath('analysis_metadata.complexity_score', 7.5)
            ->assertJsonPath('analysis_metadata.processing_type', 'instrument')
            ->assertJsonPath('analysis_metadata.macro_mappings', 2);
    }

    /**
     * Test device information is included in chain details
     */
    public function test_device_information_included_in_chain_details(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(3, $data['devices']);

        $device = $data['devices'][0];
        $this->assertArrayHasKey('device_name', $device);
        $this->assertArrayHasKey('device_type', $device);
        $this->assertArrayHasKey('device_index', $device);
        $this->assertArrayHasKey('is_max_for_live', $device);
        $this->assertArrayHasKey('parameters', $device);
    }

    /**
     * Test child chains are included in response
     */
    public function test_child_chains_included_in_response(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data['child_chains']);

        $childChain = $data['child_chains'][0];
        $this->assertEquals('Child Chain', $childChain['chain_name']);
        $this->assertEquals(1, $childChain['depth_level']);
    }

    /**
     * Test 404 response for non-existent rack
     */
    public function test_returns_404_for_non_existent_rack(): void
    {
        $response = $this->getJson("/api/v1/racks/non-existent-uuid/nested-chains/{$this->chain->id}");

        $response->assertNotFound();
    }

    /**
     * Test 404 response for non-existent chain
     */
    public function test_returns_404_for_non_existent_chain(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/non-existent-chain-id");

        $response->assertNotFound();
    }

    /**
     * Test 404 response for chain not belonging to rack
     */
    public function test_returns_404_for_chain_not_belonging_to_rack(): void
    {
        // Create another rack and chain
        $otherRack = Rack::factory()->create(['user_id' => $this->user->id]);
        $otherChain = NestedChain::factory()->create(['rack_id' => $otherRack->id]);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$otherChain->id}");

        $response->assertNotFound();
    }

    /**
     * Test authentication not required for public rack
     */
    public function test_authentication_not_required_for_public_rack(): void
    {
        // Make rack public
        $this->rack->is_private = false;
        $this->rack->save();

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

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

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertUnauthorized();

        // Test with authentication
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200);
    }

    /**
     * Test XML path information is preserved
     */
    public function test_xml_path_information_preserved(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('xml_path', '/DeviceChain/Devices/InstrumentRack/Chains/Chain[@Id="1"]');
    }

    /**
     * Test analysis metadata is included
     */
    public function test_analysis_metadata_included(): void
    {
        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'analysis_metadata' => [
                    'complexity_score',
                    'processing_type',
                    'macro_mappings'
                ]
            ]);

        $data = $response->json();
        $this->assertEquals(7.5, $data['analysis_metadata']['complexity_score']);
        $this->assertEquals('instrument', $data['analysis_metadata']['processing_type']);
        $this->assertEquals(2, $data['analysis_metadata']['macro_mappings']);
    }

    /**
     * Test empty chain details
     */
    public function test_empty_chain_details(): void
    {
        $emptyChain = NestedChain::factory()->create([
            'rack_id' => $this->rack->id,
            'chain_name' => 'Empty Test Chain',
            'device_count' => 0,
            'is_empty' => true
        ]);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$emptyChain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('is_empty', true)
            ->assertJsonPath('device_count', 0)
            ->assertJsonPath('devices', []);
    }

    /**
     * Test chain with Max for Live devices
     */
    public function test_chain_with_max_for_live_devices(): void
    {
        // Create Max for Live device
        $maxDevice = ChainDevice::factory()->create([
            'nested_chain_id' => $this->chain->id,
            'device_name' => 'Custom Max Device',
            'device_type' => 'MxDeviceAudioEffect',
            'is_max_for_live' => true,
            'device_parameters' => [
                'custom_params' => ['param1' => 'value1'],
                'preset_name' => 'Custom Preset'
            ]
        ]);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $response->assertStatus(200);

        $data = $response->json();
        $maxDeviceData = collect($data['devices'])
            ->firstWhere('device_name', 'Custom Max Device');

        $this->assertNotNull($maxDeviceData);
        $this->assertTrue($maxDeviceData['is_max_for_live']);
        $this->assertEquals('MxDeviceAudioEffect', $maxDeviceData['device_type']);
        $this->assertArrayHasKey('custom_params', $maxDeviceData['parameters']);
    }

    /**
     * Test performance with large chain data
     */
    public function test_performance_with_large_chain_data(): void
    {
        // Create chain with many devices
        ChainDevice::factory()->count(50)->create([
            'nested_chain_id' => $this->chain->id
        ]);

        // Create many child chains
        NestedChain::factory()->count(20)->create([
            'rack_id' => $this->rack->id,
            'parent_chain_id' => $this->chain->id,
            'depth_level' => 1
        ]);

        $startTime = microtime(true);

        $response = $this->getJson("/api/v1/racks/{$this->rack->uuid}/nested-chains/{$this->chain->id}");

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);

        // Performance assertion: should respond within 500ms even with large data
        $this->assertLessThan(500, $duration, 'Chain detail API should respond within 500ms');
    }
}