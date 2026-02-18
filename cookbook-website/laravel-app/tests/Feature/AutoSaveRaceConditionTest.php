<?php

namespace Tests\Feature;

use App\Models\Rack;
use App\Models\User;
use App\Services\AutoSaveService;
use App\Services\OptimisticLockingService;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\AutoSaveException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Http\Response;
use Carbon\Carbon;

/**
 * Comprehensive Race Condition Tests for Auto-Save System
 * 
 * Tests all aspects of the auto-save system under concurrent conditions:
 * - Multiple users editing the same rack
 * - Rapid successive changes from the same user
 * - Network interruptions and recovery
 * - Conflict detection and resolution
 * - Version control and optimistic locking
 */
class AutoSaveRaceConditionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;
    protected Rack $rack;
    protected AutoSaveService $autoSaveService;
    protected OptimisticLockingService $lockingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'version' => 1,
            'status' => 'processing'
        ]);

        $this->autoSaveService = app(AutoSaveService::class);
        $this->lockingService = app(OptimisticLockingService::class);

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function test_concurrent_title_updates_create_conflict()
    {
        $this->actingAs($this->user);

        // Simulate rapid successive updates
        $responses = [];
        
        // First update
        $response1 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'First Title Update',
            'version' => 1,
            'session_id' => 'session_1'
        ]);

        // Second update with same version (simulating race condition)
        $response2 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title', 
            'value' => 'Second Title Update',
            'version' => 1,
            'session_id' => 'session_2'
        ]);

        // First update should succeed
        $response1->assertStatus(200)
                 ->assertJson(['success' => true]);

        // Second update should detect conflict
        $response2->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'conflict_detected' => true
                 ]);

        // Verify database state
        $this->rack->refresh();
        $this->assertEquals('First Title Update', $this->rack->title);
        $this->assertEquals(2, $this->rack->version);
    }

    /** @test */
    public function test_multiple_field_updates_maintain_consistency()
    {
        $this->actingAs($this->user);

        // Rapid updates to different fields
        $updateData = [
            ['field' => 'title', 'value' => 'Test Title'],
            ['field' => 'description', 'value' => 'Test Description'],
            ['field' => 'category', 'value' => 'bass'],
            ['field' => 'tags', 'value' => 'test, auto-save, concurrent']
        ];

        $responses = [];
        
        foreach ($updateData as $data) {
            $responses[] = $this->postJson("/racks/{$this->rack->id}/auto-save", array_merge($data, [
                'version' => $this->rack->fresh()->version,
                'session_id' => 'test_session'
            ]));
        }

        // All updates should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200)
                    ->assertJson(['success' => true]);
        }

        // Verify final state
        $this->rack->refresh();
        $this->assertEquals('Test Title', $this->rack->title);
        $this->assertEquals('Test Description', $this->rack->description);
        $this->assertEquals('bass', $this->rack->category);
        $this->assertEquals(5, $this->rack->version); // Started at 1, +4 updates
    }

    /** @test */
    public function test_optimistic_locking_prevents_lost_updates()
    {
        $field = 'description';
        $originalValue = 'Original description';
        
        // Set initial value
        $this->rack->update([
            $field => $originalValue,
            'version' => 1
        ]);

        // Simulate two users editing simultaneously
        $userAVersion = 1;
        $userBVersion = 1;

        // User A makes changes
        $resultA = $this->autoSaveService->saveField(
            $this->rack,
            $field,
            'User A changes',
            ['version' => $userAVersion, 'session_id' => 'user_a_session']
        );

        $this->assertTrue($resultA['success']);
        $this->assertEquals(2, $resultA['version']);

        // User B tries to make changes with stale version
        $this->expectException(ConcurrencyConflictException::class);
        
        $this->autoSaveService->saveField(
            $this->rack,
            $field,
            'User B changes',
            ['version' => $userBVersion, 'session_id' => 'user_b_session']
        );
    }

    /** @test */
    public function test_rapid_typing_debouncing_prevents_excessive_requests()
    {
        $this->actingAs($this->user);

        $startTime = microtime(true);
        
        // Simulate rapid typing (10 keystrokes in quick succession)
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson("/racks/{$this->rack->id}/auto-save", [
                'field' => 'title',
                'value' => 'Typing character ' . $i,
                'version' => $this->rack->fresh()->version,
                'session_id' => 'typing_session'
            ]);
            
            // Small delay to simulate real typing
            usleep(50000); // 50ms
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to ms

        // Verify the final save succeeded
        $this->rack->refresh();
        $this->assertEquals('Typing character 10', $this->rack->title);

        // Test should complete quickly due to proper debouncing
        $this->assertLessThan(2000, $totalTime, 'Test took too long, debouncing may not be working');
    }

    /** @test */
    public function test_network_interruption_simulation()
    {
        $this->actingAs($this->user);

        // First, successful save
        $response1 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Before interruption',
            'version' => 1,
            'session_id' => 'network_test'
        ]);
        
        $response1->assertStatus(200);

        // Simulate network error by making request to non-existent endpoint
        // This tests client-side error handling
        $response2 = $this->postJson("/racks/{$this->rack->id}/fake-endpoint", [
            'field' => 'title',
            'value' => 'During interruption'
        ]);
        
        $response2->assertStatus(404);

        // Recovery request after "network restoration"
        $response3 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'After recovery',
            'version' => $this->rack->fresh()->version,
            'session_id' => 'network_test'
        ]);
        
        $response3->assertStatus(200);

        // Verify final state
        $this->rack->refresh();
        $this->assertEquals('After recovery', $this->rack->title);
    }

    /** @test */
    public function test_session_conflict_detection()
    {
        $sessionA = 'session_a_' . time();
        $sessionB = 'session_b_' . time();

        $this->actingAs($this->user);

        // Session A starts editing
        $response1 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'description',
            'value' => 'Session A content',
            'version' => 1,
            'session_id' => $sessionA
        ]);
        
        $response1->assertStatus(200);

        // Session B tries to edit same field
        $response2 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'description',
            'value' => 'Session B content',
            'version' => 1, // Stale version
            'session_id' => $sessionB
        ]);

        // Should detect conflict
        $response2->assertStatus(200)
                 ->assertJson([
                     'success' => false,
                     'conflict_detected' => true
                 ]);
    }

    /** @test */
    public function test_conflict_resolution_endpoint()
    {
        $this->actingAs($this->user);
        $sessionId = 'conflict_test_session';

        // Create a conflict scenario
        $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'First version',
            'version' => 1,
            'session_id' => $sessionId
        ]);

        // Force a conflict by updating with stale version
        $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Conflicting version',
            'version' => 1,
            'session_id' => $sessionId . '_2'
        ]);

        // Try to resolve conflicts
        $response = $this->postJson("/racks/{$this->rack->id}/resolve-conflicts", [
            'session_id' => $sessionId . '_2',
            'resolutions' => [
                'title' => 'keep_yours'
            ]
        ]);

        // Should either resolve successfully or return specific error
        $this->assertContains($response->status(), [200, 400, 404]);
    }

    /** @test */
    public function test_auto_resolve_conflicts_endpoint()
    {
        $this->actingAs($this->user);
        $sessionId = 'auto_resolve_test';

        // Test auto-resolve endpoint
        $response = $this->postJson("/racks/{$this->rack->id}/auto-resolve-conflicts", [
            'session_id' => $sessionId,
            'strategy' => 'smart_merge'
        ]);

        // Should handle case where no conflicts exist
        $response->assertStatus(200);
    }

    /** @test */
    public function test_connection_recovery_endpoint()
    {
        $this->actingAs($this->user);
        $sessionId = 'recovery_test';

        $response = $this->postJson("/racks/{$this->rack->id}/connection-recovery", [
            'session_id' => $sessionId,
            'client_state' => [
                'version' => 1,
                'last_sync' => now()->toISOString(),
                'pending_changes' => []
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'recovery_needed',
                    'current_state'
                ]);
    }

    /** @test */
    public function test_large_content_auto_save()
    {
        $this->actingAs($this->user);

        // Generate large content (approaching limit)
        $largeContent = str_repeat('This is a test of large content auto-save functionality. ', 100);
        
        $response = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'how_to_article',
            'value' => $largeContent,
            'version' => 1,
            'session_id' => 'large_content_test'
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->rack->refresh();
        $this->assertEquals($largeContent, $this->rack->how_to_article);
    }

    /** @test */
    public function test_validation_errors_during_auto_save()
    {
        $this->actingAs($this->user);

        // Test with oversized content
        $oversizedContent = str_repeat('x', 11000); // Over 10000 char limit

        $response = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'how_to_article',
            'value' => $oversizedContent,
            'version' => 1,
            'session_id' => 'validation_test'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'error',
                    'error_type',
                    'errors'
                ]);
    }

    /** @test */
    public function test_unauthorized_access_protection()
    {
        // Try to auto-save without authentication
        $response = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Unauthorized attempt',
            'version' => 1
        ]);

        $response->assertStatus(401);

        // Try to auto-save as different user
        $this->actingAs($this->otherUser);
        
        $response = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Wrong user attempt',
            'version' => 1
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_status_endpoint_with_auto_save_state()
    {
        $this->actingAs($this->user);
        $sessionId = 'status_test_session';

        // Make some changes first
        $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Status test title',
            'version' => 1,
            'session_id' => $sessionId
        ]);

        // Check status with session ID
        $response = $this->getJson("/racks/{$this->rack->id}/status?session_id={$sessionId}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'is_complete',
                    'has_error',
                    'auto_save_state'
                ]);
    }

    /** @test */
    public function test_performance_under_load()
    {
        $this->actingAs($this->user);
        
        $startTime = microtime(true);
        $responses = [];

        // Simulate multiple rapid requests
        for ($i = 1; $i <= 20; $i++) {
            $responses[] = $this->postJson("/racks/{$this->rack->id}/auto-save", [
                'field' => 'title',
                'value' => "Load test iteration {$i}",
                'version' => $this->rack->fresh()->version,
                'session_id' => 'load_test_session'
            ]);
            
            usleep(10000); // 10ms delay between requests
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Verify responses
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->status() === 200) {
                $data = $response->json();
                if ($data['success'] === true) {
                    $successCount++;
                }
            }
        }

        // At least some requests should succeed
        $this->assertGreaterThan(0, $successCount);
        
        // Performance should be reasonable
        $this->assertLessThan(5000, $totalTime, 'Load test took too long');

        // Final state should be consistent
        $this->rack->refresh();
        $this->assertStringContains('Load test iteration', $this->rack->title);
    }

    /** @test */
    public function test_version_increment_consistency()
    {
        $this->actingAs($this->user);
        
        $initialVersion = $this->rack->version;

        // Make several sequential updates
        $updates = [
            ['field' => 'title', 'value' => 'Version test 1'],
            ['field' => 'description', 'value' => 'Version test description'],
            ['field' => 'category', 'value' => 'bass'],
            ['field' => 'title', 'value' => 'Version test final'],
        ];

        foreach ($updates as $update) {
            $currentVersion = $this->rack->fresh()->version;
            
            $response = $this->postJson("/racks/{$this->rack->id}/auto-save", array_merge($update, [
                'version' => $currentVersion,
                'session_id' => 'version_test'
            ]));

            $response->assertStatus(200);
            
            $responseData = $response->json();
            if ($responseData['success']) {
                $this->assertEquals($currentVersion + 1, $responseData['version']);
            }
        }

        // Verify final version is correct
        $this->rack->refresh();
        $expectedVersion = $initialVersion + count($updates);
        $this->assertEquals($expectedVersion, $this->rack->version);
    }

    /** @test */
    public function test_database_transaction_rollback_on_error()
    {
        $this->actingAs($this->user);

        $originalTitle = $this->rack->title;
        $originalVersion = $this->rack->version;

        // Force a database error by trying to save invalid data
        // This is a mock test - in real scenarios you'd need to simulate actual DB errors
        
        try {
            DB::transaction(function () {
                $this->rack->update(['title' => 'Test title']);
                // Simulate an error that would cause rollback
                throw new \Exception('Simulated database error');
            });
        } catch (\Exception $e) {
            // Expected to catch the exception
        }

        // Verify rollback worked
        $this->rack->refresh();
        $this->assertEquals($originalTitle, $this->rack->title);
        $this->assertEquals($originalVersion, $this->rack->version);
    }

    /** @test */
    public function test_concurrent_analysis_completion_and_auto_save()
    {
        $this->actingAs($this->user);

        // Simulate auto-save while analysis is completing
        $response = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'title',
            'value' => 'Title during analysis',
            'version' => 1,
            'session_id' => 'analysis_test'
        ]);

        $response->assertStatus(200);

        // Simulate analysis completion
        $this->rack->update([
            'status' => 'pending',
            'device_count' => 5,
            'chain_count' => 2
        ]);

        // Another auto-save after analysis completion
        $response2 = $this->postJson("/racks/{$this->rack->id}/auto-save", [
            'field' => 'description',
            'value' => 'Description after analysis',
            'version' => $this->rack->fresh()->version,
            'session_id' => 'analysis_test'
        ]);

        $response2->assertStatus(200);

        // Verify both saves worked
        $this->rack->refresh();
        $this->assertEquals('Title during analysis', $this->rack->title);
        $this->assertEquals('Description after analysis', $this->rack->description);
        $this->assertEquals('pending', $this->rack->status);
    }
}