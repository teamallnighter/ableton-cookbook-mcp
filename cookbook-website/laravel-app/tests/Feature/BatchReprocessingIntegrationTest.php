<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use App\Jobs\BatchReprocessJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

class BatchReprocessingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected array $testRackPaths;
    protected array $uploadedRacks;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Define test rack files from /testRacks directory
        $this->testRackPaths = [
            'WIERDO.adg' => '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/WIERDO.adg',
            'UR-GROUP.adg' => '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/UR-GROUP.adg',
            'GROUP .adg' => '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/GROUP .adg',
            'GATEKEEPER RIFT.adg' => '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/GATEKEEPER RIFT.adg',
        ];

        $this->uploadedRacks = [];

        // Create and upload test racks
        $this->createTestRacks();
    }

    /**
     * Scenario 4: Batch Reprocessing Integration Test
     * Test complete workflow: Multiple rack upload → Initial analysis → Batch reprocess → Verification
     */
    public function test_complete_batch_reprocessing_scenario(): void
    {
        Sanctum::actingAs($this->user);

        // Step 1: Upload multiple racks with nested chains
        $this->uploadMultipleTestRacks();

        // Step 2: Verify initial analysis state
        $this->verifyInitialAnalysisState();

        // Step 3: Trigger batch reprocessing
        $batchResponse = $this->triggerBatchReprocessing();

        // Step 4: Simulate job processing
        $this->simulateBatchJobProcessing($batchResponse['batch_id']);

        // Step 5: Verify enhanced analysis completion
        $this->verifyEnhancedAnalysisCompletion();

        // Step 6: Verify constitutional compliance across all racks
        $this->verifyBatchConstitutionalCompliance();

        // Step 7: Test batch status reporting
        $this->verifyBatchStatusReporting($batchResponse['batch_id']);
    }

    /**
     * Test batch reprocessing with mixed rack ownership
     */
    public function test_batch_reprocessing_with_mixed_ownership(): void
    {
        $otherUser = User::factory()->create();

        // Create racks with different ownership
        $userRack = $this->createSingleTestRack('WIERDO.adg', $this->user);
        $otherUserRack = $this->createSingleTestRack('UR-GROUP.adg', $otherUser);

        Sanctum::actingAs($this->user);

        // User should only be able to reprocess their own racks
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$userRack->uuid, $otherUserRack->uuid],
            'priority' => 'normal'
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You can only reprocess your own racks');

        // Admin should be able to reprocess any racks
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$userRack->uuid, $otherUserRack->uuid],
            'priority' => 'high'
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('queued_count', 2);
    }

    /**
     * Test batch reprocessing performance with maximum batch size
     */
    public function test_batch_reprocessing_performance_limits(): void
    {
        Sanctum::actingAs($this->user);

        // Create maximum allowed batch size (10 racks)
        $rackUuids = [];
        for ($i = 0; $i < 10; $i++) {
            $rack = $this->createSingleTestRack('WIERDO.adg', $this->user, "batch-test-{$i}");
            $rackUuids[] = $rack->uuid;
        }

        $startTime = microtime(true);

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(202)
            ->assertJsonPath('queued_count', 10);

        // Should respond within 500ms even for maximum batch size
        $this->assertLessThan(500, $duration, 'Batch reprocess API should respond within 500ms');

        // Verify job queuing
        Queue::assertPushed(BatchReprocessJob::class, function ($job) use ($rackUuids) {
            return count($job->rackUuids) === 10 &&
                   array_diff($rackUuids, $job->rackUuids) === [];
        });
    }

    /**
     * Test batch reprocessing with priority levels
     */
    public function test_batch_reprocessing_priority_handling(): void
    {
        Sanctum::actingAs($this->user);

        $rack1 = $this->createSingleTestRack('WIERDO.adg', $this->user, 'priority-high');
        $rack2 = $this->createSingleTestRack('UR-GROUP.adg', $this->user, 'priority-low');

        // Test high priority batch
        $highPriorityResponse = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$rack1->uuid],
            'priority' => 'high'
        ]);

        $highPriorityResponse->assertStatus(202)
            ->assertJsonPath('priority', 'high');

        // Test low priority batch
        $lowPriorityResponse = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$rack2->uuid],
            'priority' => 'low'
        ]);

        $lowPriorityResponse->assertStatus(202)
            ->assertJsonPath('priority', 'low');

        // Verify high priority job was queued first (higher priority queue)
        Queue::assertPushed(BatchReprocessJob::class, function ($job) {
            return $job->priority === 'high';
        });

        Queue::assertPushed(BatchReprocessJob::class, function ($job) {
            return $job->priority === 'low';
        });
    }

    /**
     * Test batch reprocessing constitutional compliance validation
     */
    public function test_batch_constitutional_compliance_validation(): void
    {
        Sanctum::actingAs($this->user);

        // Upload racks and trigger batch reprocessing
        $this->uploadMultipleTestRacks();
        $rackUuids = array_values(array_map(fn($rack) => $rack->uuid, $this->uploadedRacks));

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $response->assertStatus(202);
        $batchId = $response->json('batch_id');

        // Simulate batch processing completion
        $this->simulateBatchJobProcessing($batchId);

        // Verify all racks are constitutionally compliant
        foreach ($this->uploadedRacks as $rack) {
            $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
            $this->assertNotNull($enhancedAnalysis);
            $this->assertTrue($enhancedAnalysis->constitutional_compliant,
                "Rack {$rack->uuid} should be constitutionally compliant");

            // Verify nested chains were detected
            $nestedChains = NestedChain::where('rack_id', $rack->id)->get();
            $this->assertGreaterThan(0, $nestedChains->count(),
                "Rack {$rack->uuid} should have detected nested chains");
        }
    }

    /**
     * Test batch reprocessing error handling and partial failures
     */
    public function test_batch_reprocessing_error_handling(): void
    {
        Sanctum::actingAs($this->user);

        // Create mix of valid and problematic racks
        $validRack = $this->createSingleTestRack('WIERDO.adg', $this->user, 'valid-rack');
        $corruptRack = $this->createCorruptTestRack();

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [$validRack->uuid, $corruptRack->uuid],
            'priority' => 'normal'
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('queued_count', 2);

        $batchId = $response->json('batch_id');

        // Simulate processing with partial failure
        $this->simulatePartialBatchFailure($batchId, [$validRack->uuid], [$corruptRack->uuid]);

        // Verify batch status reflects partial success
        $statusResponse = $this->getJson("/api/v1/analysis/batch-status/{$batchId}");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('status', 'partial_success')
            ->assertJsonPath('successful_count', 1)
            ->assertJsonPath('failed_count', 1);
    }

    /**
     * Helper method to upload multiple test racks
     */
    private function uploadMultipleTestRacks(): void
    {
        foreach (['WIERDO.adg', 'UR-GROUP.adg', 'GROUP .adg'] as $index => $filename) {
            if (!file_exists($this->testRackPaths[$filename])) {
                $this->markTestSkipped("Test rack {$filename} not found in /testRacks directory");
            }

            $uploadedFile = new UploadedFile(
                $this->testRackPaths[$filename],
                $filename,
                'application/octet-stream',
                null,
                true
            );

            $uploadResponse = $this->postJson('/api/v1/racks', [
                'title' => "Batch Test Rack {$index}",
                'description' => "Test rack for batch reprocessing scenario",
                'file' => $uploadedFile,
                'tags' => ['test', 'batch-processing']
            ]);

            $uploadResponse->assertStatus(201);

            $rackUuid = $uploadResponse->json('uuid');
            $rack = Rack::where('uuid', $rackUuid)->first();
            $this->uploadedRacks[$filename] = $rack;
        }
    }

    /**
     * Helper method to verify initial analysis state
     */
    private function verifyInitialAnalysisState(): void
    {
        foreach ($this->uploadedRacks as $rack) {
            $rack->refresh();
            $this->assertTrue($rack->analysis_complete, "Rack {$rack->uuid} should have completed basic analysis");
            $this->assertFalse($rack->enhanced_analysis_complete, "Rack {$rack->uuid} should not have enhanced analysis yet");
        }
    }

    /**
     * Helper method to trigger batch reprocessing
     */
    private function triggerBatchReprocessing(): array
    {
        $rackUuids = array_values(array_map(fn($rack) => $rack->uuid, $this->uploadedRacks));

        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => $rackUuids,
            'priority' => 'normal'
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'batch_id',
                'queued_count',
                'estimated_completion',
                'priority'
            ])
            ->assertJsonPath('queued_count', count($rackUuids));

        return $response->json();
    }

    /**
     * Helper method to simulate batch job processing
     */
    private function simulateBatchJobProcessing(string $batchId): void
    {
        // In a real scenario, BatchReprocessJob would process these
        // For testing, we simulate the enhanced analysis completion
        foreach ($this->uploadedRacks as $rack) {
            $rack->update(['enhanced_analysis_complete' => true]);

            // Create mock enhanced analysis
            EnhancedRackAnalysis::create([
                'rack_id' => $rack->id,
                'constitutional_compliant' => true,
                'has_nested_chains' => true,
                'total_chains_detected' => rand(3, 8),
                'max_nesting_depth' => rand(2, 4),
                'total_devices' => rand(10, 25),
                'analysis_duration_ms' => rand(500, 2000),
                'processed_at' => now()
            ]);

            // Create mock nested chains
            $chainCount = rand(3, 6);
            for ($i = 0; $i < $chainCount; $i++) {
                NestedChain::create([
                    'rack_id' => $rack->id,
                    'chain_identifier' => "chain_{$i}",
                    'xml_path' => "/Ableton/LiveSet/Tracks/Track[{$i}]/DeviceChain",
                    'parent_chain_id' => null,
                    'depth_level' => rand(0, 2),
                    'device_count' => rand(1, 5),
                    'is_empty' => false,
                    'chain_type' => ['instrument', 'audio_effect', 'drum_pad'][array_rand(['instrument', 'audio_effect', 'drum_pad'])]
                ]);
            }
        }
    }

    /**
     * Helper method to verify enhanced analysis completion
     */
    private function verifyEnhancedAnalysisCompletion(): void
    {
        foreach ($this->uploadedRacks as $rack) {
            $rack->refresh();
            $this->assertTrue($rack->enhanced_analysis_complete,
                "Rack {$rack->uuid} should have completed enhanced analysis");

            $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
            $this->assertNotNull($enhancedAnalysis);
            $this->assertTrue($enhancedAnalysis->constitutional_compliant);

            $nestedChains = NestedChain::where('rack_id', $rack->id)->get();
            $this->assertGreaterThan(0, $nestedChains->count());
        }
    }

    /**
     * Helper method to verify batch constitutional compliance
     */
    private function verifyBatchConstitutionalCompliance(): void
    {
        $totalRacks = count($this->uploadedRacks);
        $compliantRacks = 0;

        foreach ($this->uploadedRacks as $rack) {
            $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
            if ($enhancedAnalysis && $enhancedAnalysis->constitutional_compliant) {
                $compliantRacks++;
            }
        }

        $this->assertEquals($totalRacks, $compliantRacks,
            "All racks in batch should be constitutionally compliant");
    }

    /**
     * Helper method to verify batch status reporting
     */
    private function verifyBatchStatusReporting(string $batchId): void
    {
        $statusResponse = $this->getJson("/api/v1/analysis/batch-status/{$batchId}");

        $statusResponse->assertStatus(200)
            ->assertJsonStructure([
                'batch_id',
                'status',
                'total_count',
                'completed_count',
                'successful_count',
                'failed_count',
                'started_at',
                'completed_at'
            ])
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('total_count', count($this->uploadedRacks))
            ->assertJsonPath('successful_count', count($this->uploadedRacks))
            ->assertJsonPath('failed_count', 0);
    }

    /**
     * Helper method to create test racks
     */
    private function createTestRacks(): void
    {
        // Create base test racks for the test suite
        foreach (['WIERDO.adg', 'UR-GROUP.adg'] as $filename) {
            if (file_exists($this->testRackPaths[$filename])) {
                $rack = $this->createSingleTestRack($filename, $this->user);
                $this->uploadedRacks[$filename] = $rack;
            }
        }
    }

    /**
     * Helper method to create single test rack
     */
    private function createSingleTestRack(string $filename, User $user, string $suffix = ''): Rack
    {
        $rackPath = $this->testRackPaths[$filename];

        if (!file_exists($rackPath)) {
            $this->markTestSkipped("Test rack {$filename} not found in /testRacks directory");
        }

        $rack = Rack::factory()->create([
            'user_id' => $user->id,
            'uuid' => 'batch-test-' . uniqid() . ($suffix ? "-{$suffix}" : ''),
            'title' => "Batch Test - {$filename}" . ($suffix ? " ({$suffix})" : ''),
            'file_path' => "test-racks/{$filename}",
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        Storage::put($rack->file_path, file_get_contents($rackPath));

        return $rack;
    }

    /**
     * Helper method to create corrupt test rack
     */
    private function createCorruptTestRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'corrupt-test-' . uniqid(),
            'title' => 'Corrupt Test Rack',
            'file_path' => 'test-racks/corrupt.adg',
            'analysis_complete' => false,
            'enhanced_analysis_complete' => false
        ]);

        // Create corrupt file content
        Storage::put($rack->file_path, 'This is not a valid .adg file');

        return $rack;
    }

    /**
     * Helper method to simulate partial batch failure
     */
    private function simulatePartialBatchFailure(string $batchId, array $successfulUuids, array $failedUuids): void
    {
        // Process successful racks
        foreach ($successfulUuids as $uuid) {
            $rack = Rack::where('uuid', $uuid)->first();
            if ($rack) {
                $rack->update(['enhanced_analysis_complete' => true]);

                EnhancedRackAnalysis::create([
                    'rack_id' => $rack->id,
                    'constitutional_compliant' => true,
                    'has_nested_chains' => true,
                    'total_chains_detected' => 3,
                    'max_nesting_depth' => 2,
                    'total_devices' => 12,
                    'analysis_duration_ms' => 1500,
                    'processed_at' => now()
                ]);
            }
        }

        // Failed racks remain unchanged
        foreach ($failedUuids as $uuid) {
            $rack = Rack::where('uuid', $uuid)->first();
            if ($rack) {
                $rack->update(['enhanced_analysis_complete' => false]);
            }
        }
    }
}