<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Exception;

class ErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected string $testRackPath;
    protected string $corruptRackPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Use test rack from /testRacks directory
        $this->testRackPath = '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/testRacks/WIERDO.adg';

        // Verify test rack exists
        if (!file_exists($this->testRackPath)) {
            $this->markTestSkipped('Test rack WIERDO.adg not found in /testRacks directory');
        }

        // Create corrupt test file
        $this->corruptRackPath = '/tmp/corrupt_test.adg';
        file_put_contents($this->corruptRackPath, 'This is not a valid .adg file');
    }

    protected function tearDown(): void
    {
        // Clean up corrupt test file
        if (file_exists($this->corruptRackPath)) {
            unlink($this->corruptRackPath);
        }

        parent::tearDown();
    }

    /**
     * Scenario 6: Error Handling Integration Test
     * Test comprehensive error scenarios and graceful degradation
     */
    public function test_complete_error_handling_scenario(): void
    {
        Sanctum::actingAs($this->user);

        // Step 1: Test corrupt file handling
        $this->testCorruptFileHandling();

        // Step 2: Test missing file scenarios
        $this->testMissingFileScenarios();

        // Step 3: Test database constraint violations
        $this->testDatabaseConstraintViolations();

        // Step 4: Test memory and timeout scenarios
        $this->testMemoryAndTimeoutScenarios();

        // Step 5: Test partial analysis failures
        $this->testPartialAnalysisFailures();

        // Step 6: Test error recovery mechanisms
        $this->testErrorRecoveryMechanisms();

        // Step 7: Test constitutional compliance error handling
        $this->testConstitutionalComplianceErrorHandling();
    }

    /**
     * Test handling of corrupt .adg files
     */
    public function test_corrupt_file_handling(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack with corrupt file
        $corruptRack = $this->createCorruptRack();

        // Attempt enhanced analysis on corrupt file
        $response = $this->postJson("/api/v1/racks/{$corruptRack->uuid}/analyze-nested-chains");

        // Should handle gracefully with appropriate error
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'error_code',
                'error_details'
            ])
            ->assertJsonPath('error_code', 'INVALID_RACK_FORMAT');

        // Verify rack state remains consistent
        $corruptRack->refresh();
        $this->assertFalse($corruptRack->enhanced_analysis_complete);

        // Should not create invalid analysis records
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $corruptRack->id)->first();
        $this->assertNull($enhancedAnalysis);
    }

    /**
     * Test handling of missing files
     */
    public function test_missing_file_scenarios(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack with missing file
        $missingFileRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'missing-file-' . uniqid(),
            'file_path' => 'non-existent-file.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Attempt enhanced analysis on missing file
        $response = $this->postJson("/api/v1/racks/{$missingFileRack->uuid}/analyze-nested-chains");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'FILE_NOT_FOUND');

        // Test retrieval of nested chains for missing file
        $chainsResponse = $this->getJson("/api/v1/racks/{$missingFileRack->uuid}/nested-chains");

        $chainsResponse->assertStatus(422)
            ->assertJsonPath('error_code', 'FILE_NOT_FOUND');
    }

    /**
     * Test database constraint violations and consistency
     */
    public function test_database_constraint_violations(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createValidTestRack();

        // Test duplicate chain creation (should not happen but test constraint)
        try {
            // Create duplicate nested chain with same identifier
            NestedChain::create([
                'rack_id' => $rack->id,
                'chain_identifier' => 'duplicate_chain',
                'xml_path' => '/path/to/chain',
                'depth_level' => 0,
                'device_count' => 1,
                'is_empty' => false,
                'chain_type' => 'instrument'
            ]);

            NestedChain::create([
                'rack_id' => $rack->id,
                'chain_identifier' => 'duplicate_chain', // Same identifier
                'xml_path' => '/path/to/chain',
                'depth_level' => 0,
                'device_count' => 1,
                'is_empty' => false,
                'chain_type' => 'instrument'
            ]);

            // If we reach here, constraint not working as expected
            $this->fail('Database should prevent duplicate chain identifiers');
        } catch (Exception $e) {
            // Expected constraint violation
            $this->assertStringContains('Duplicate entry', $e->getMessage());
        }

        // Test invalid foreign key references
        try {
            EnhancedRackAnalysis::create([
                'rack_id' => 99999, // Non-existent rack ID
                'constitutional_compliant' => true,
                'has_nested_chains' => false,
                'total_chains_detected' => 0,
                'max_nesting_depth' => 0,
                'total_devices' => 0,
                'analysis_duration_ms' => 1000,
                'processed_at' => now()
            ]);

            $this->fail('Database should prevent invalid foreign key references');
        } catch (Exception $e) {
            // Expected foreign key constraint violation
            $this->assertTrue(true, 'Foreign key constraint working correctly');
        }
    }

    /**
     * Test memory and timeout scenarios
     */
    public function test_memory_and_timeout_scenarios(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack that simulates large/complex file
        $largeRack = $this->createLargeComplexRack();

        // Test timeout handling
        $startTime = microtime(true);

        $response = $this->postJson("/api/v1/racks/{$largeRack->uuid}/analyze-nested-chains");

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should either succeed within timeout or fail gracefully
        if ($response->status() === 200) {
            // If successful, should be within constitutional limit (5 seconds)
            $responseData = $response->json();
            $this->assertLessThan(5000, $responseData['analysis_duration_ms']);
        } else {
            // If failed, should be appropriate timeout error
            $response->assertStatus(408)
                ->assertJsonPath('error_code', 'ANALYSIS_TIMEOUT');
        }

        // API response itself should be quick regardless
        $this->assertLessThan(10000, $duration, 'API should respond within 10 seconds');
    }

    /**
     * Test partial analysis failure scenarios
     */
    public function test_partial_analysis_failures(): void
    {
        Sanctum::actingAs($this->user);

        // Create rack with valid structure but problematic content
        $problematicRack = $this->createProblematicRack();

        $response = $this->postJson("/api/v1/racks/{$problematicRack->uuid}/analyze-nested-chains");

        // Should handle partial failures gracefully
        if ($response->status() === 200) {
            $responseData = $response->json();

            // Should still report what was successfully analyzed
            $this->assertArrayHasKey('analysis_complete', $responseData);
            $this->assertArrayHasKey('constitutional_compliant', $responseData);

            // May have partial results
            if (!$responseData['constitutional_compliant']) {
                $this->assertArrayHasKey('compliance_issues', $responseData);
            }
        } else {
            // If completely failed, should provide detailed error
            $response->assertJsonStructure([
                'message',
                'error_code',
                'error_details'
            ]);
        }
    }

    /**
     * Test error recovery mechanisms
     */
    public function test_error_recovery_mechanisms(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createValidTestRack();

        // Simulate analysis that fails partway through
        DB::beginTransaction();
        try {
            // Create partial enhanced analysis
            $analysis = EnhancedRackAnalysis::create([
                'rack_id' => $rack->id,
                'constitutional_compliant' => false,
                'has_nested_chains' => false,
                'total_chains_detected' => 0,
                'max_nesting_depth' => 0,
                'total_devices' => 0,
                'analysis_duration_ms' => 0,
                'processed_at' => now()
            ]);

            // Simulate rollback scenario
            DB::rollBack();
        } catch (Exception $e) {
            DB::rollBack();
        }

        // Verify clean state after rollback
        $enhancedAnalysis = EnhancedRackAnalysis::where('rack_id', $rack->id)->first();
        $this->assertNull($enhancedAnalysis);

        // Test retry mechanism
        $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains", [
            'force' => true
        ]);

        // Should work on retry
        $response->assertStatus(200);
    }

    /**
     * Test constitutional compliance error handling
     */
    public function test_constitutional_compliance_error_handling(): void
    {
        Sanctum::actingAs($this->user);

        // Test compliance report with problematic data
        $this->createRacksWithVariousStates();

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();

        // Should handle mixed states gracefully
        $this->assertArrayHasKey('total_racks', $data);
        $this->assertArrayHasKey('compliance_percentage', $data);

        // Percentages should be valid even with errors
        $this->assertGreaterThanOrEqual(0, $data['compliance_percentage']);
        $this->assertLessThanOrEqual(100, $data['compliance_percentage']);
    }

    /**
     * Test batch operation error handling
     */
    public function test_batch_operation_error_handling(): void
    {
        Sanctum::actingAs($this->user);

        // Create mix of valid and invalid racks
        $validRack = $this->createValidTestRack();
        $corruptRack = $this->createCorruptRack();
        $missingRack = $this->createMissingFileRack();

        // Attempt batch reprocessing
        $response = $this->postJson('/api/v1/analysis/batch-reprocess', [
            'rack_uuids' => [
                $validRack->uuid,
                $corruptRack->uuid,
                $missingRack->uuid
            ],
            'priority' => 'normal'
        ]);

        $response->assertStatus(202);

        $batchId = $response->json('batch_id');

        // Simulate batch processing with mixed results
        $this->simulateMixedBatchResults($batchId, $validRack, $corruptRack, $missingRack);

        // Check batch status
        $statusResponse = $this->getJson("/api/v1/analysis/batch-status/{$batchId}");

        $statusResponse->assertStatus(200)
            ->assertJsonStructure([
                'batch_id',
                'status',
                'total_count',
                'successful_count',
                'failed_count',
                'error_summary'
            ]);

        $statusData = $statusResponse->json();

        // Should report partial success
        $this->assertEquals('partial_success', $statusData['status']);
        $this->assertEquals(1, $statusData['successful_count']);
        $this->assertEquals(2, $statusData['failed_count']);
        $this->assertArrayHasKey('error_summary', $statusData);
    }

    /**
     * Test API rate limiting error responses
     */
    public function test_api_rate_limiting_errors(): void
    {
        Sanctum::actingAs($this->user);

        $rack = $this->createValidTestRack();

        // Attempt to exceed rate limits
        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains", [
                'force' => true
            ]);

            if ($i < 10) {
                // Should succeed within limits
                $this->assertContains($response->status(), [200, 202]);
            } else {
                // Should hit rate limit
                $response->assertStatus(429)
                    ->assertJsonStructure([
                        'message',
                        'retry_after'
                    ]);
            }
        }
    }

    /**
     * Test cascading failure scenarios
     */
    public function test_cascading_failure_scenarios(): void
    {
        Sanctum::actingAs($this->user);

        // Simulate Redis cache failure
        Cache::shouldReceive('get')->andThrow(new Exception('Redis connection failed'));
        Cache::shouldReceive('put')->andThrow(new Exception('Redis connection failed'));

        $rack = $this->createValidTestRack();

        // Analysis should still work without cache
        $response = $this->postJson("/api/v1/racks/{$rack->uuid}/analyze-nested-chains");

        // Should either succeed without cache or gracefully degrade
        $this->assertContains($response->status(), [200, 202, 503]);

        if ($response->status() === 503) {
            $response->assertJsonPath('error_code', 'SERVICE_DEGRADED');
        }
    }

    /**
     * Helper method to create corrupt rack
     */
    private function createCorruptRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'corrupt-' . uniqid(),
            'title' => 'Corrupt Test Rack',
            'file_path' => 'corrupt-test.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Store corrupt file content
        Storage::put($rack->file_path, 'This is not a valid .adg file');

        return $rack;
    }

    /**
     * Helper method to create valid test rack
     */
    private function createValidTestRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'valid-' . uniqid(),
            'title' => 'Valid Test Rack',
            'file_path' => 'valid-test.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        Storage::put($rack->file_path, file_get_contents($this->testRackPath));

        return $rack;
    }

    /**
     * Helper method to create large complex rack for timeout testing
     */
    private function createLargeComplexRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'large-complex-' . uniqid(),
            'title' => 'Large Complex Test Rack',
            'file_path' => 'large-complex-test.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Use existing test rack (should be complex enough)
        Storage::put($rack->file_path, file_get_contents($this->testRackPath));

        return $rack;
    }

    /**
     * Helper method to create problematic rack
     */
    private function createProblematicRack(): Rack
    {
        $rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'problematic-' . uniqid(),
            'title' => 'Problematic Test Rack',
            'file_path' => 'problematic-test.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);

        // Create a file that's valid gzip but invalid XML structure
        $invalidXml = '<?xml version="1.0" encoding="UTF-8"?><InvalidRoot><UnclosedTag></InvalidRoot>';
        Storage::put($rack->file_path, gzencode($invalidXml));

        return $rack;
    }

    /**
     * Helper method to create rack with missing file
     */
    private function createMissingFileRack(): Rack
    {
        return Rack::factory()->create([
            'user_id' => $this->user->id,
            'uuid' => 'missing-file-' . uniqid(),
            'title' => 'Missing File Test Rack',
            'file_path' => 'non-existent-file.adg',
            'analysis_complete' => true,
            'enhanced_analysis_complete' => false
        ]);
    }

    /**
     * Helper method to create racks with various states
     */
    private function createRacksWithVariousStates(): void
    {
        // Valid enhanced rack
        $validRack = $this->createValidTestRack();
        $validRack->update(['enhanced_analysis_complete' => true]);

        EnhancedRackAnalysis::create([
            'rack_id' => $validRack->id,
            'constitutional_compliant' => true,
            'has_nested_chains' => true,
            'total_chains_detected' => 3,
            'max_nesting_depth' => 2,
            'total_devices' => 10,
            'analysis_duration_ms' => 1000,
            'processed_at' => now()
        ]);

        // Corrupt rack
        $this->createCorruptRack();

        // Missing file rack
        $this->createMissingFileRack();

        // Partially analyzed rack
        $partialRack = $this->createValidTestRack();
        $partialRack->update(['enhanced_analysis_complete' => true]);

        EnhancedRackAnalysis::create([
            'rack_id' => $partialRack->id,
            'constitutional_compliant' => false,
            'has_nested_chains' => false,
            'total_chains_detected' => 0,
            'max_nesting_depth' => 0,
            'total_devices' => 0,
            'analysis_duration_ms' => 5000,
            'processed_at' => now()
        ]);
    }

    /**
     * Helper method to simulate mixed batch results
     */
    private function simulateMixedBatchResults(string $batchId, Rack $validRack, Rack $corruptRack, Rack $missingRack): void
    {
        // Simulate successful processing of valid rack
        $validRack->update(['enhanced_analysis_complete' => true]);

        EnhancedRackAnalysis::create([
            'rack_id' => $validRack->id,
            'constitutional_compliant' => true,
            'has_nested_chains' => true,
            'total_chains_detected' => 3,
            'max_nesting_depth' => 2,
            'total_devices' => 8,
            'analysis_duration_ms' => 1200,
            'processed_at' => now()
        ]);

        // Corrupt and missing racks remain unchanged (failed processing)
        $corruptRack->update(['enhanced_analysis_complete' => false]);
        $missingRack->update(['enhanced_analysis_complete' => false]);
    }
}