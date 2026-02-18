<?php

namespace Tests\Unit\Services;

use App\Models\Rack;
use App\Models\User;
use App\Models\Tag;
use App\Services\AutoSaveService;
use App\Services\OptimisticLockingService;
use App\Services\ConflictResolutionService;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\AutoSaveException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;
use Carbon\Carbon;

/**
 * Unit tests for AutoSaveService
 */
class AutoSaveServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AutoSaveService $service;
    protected OptimisticLockingService $lockingService;
    protected ConflictResolutionService $conflictService;
    protected User $user;
    protected Rack $rack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockingService = Mockery::mock(OptimisticLockingService::class);
        $this->conflictService = Mockery::mock(ConflictResolutionService::class);
        $this->service = new AutoSaveService($this->lockingService, $this->conflictService);

        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'version' => 1
        ]);

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function test_successful_field_save()
    {
        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now(),
            'fields_updated' => ['title']
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->with($this->rack, ['title' => 'Test Title'], null)
            ->andReturn($mockResult);

        $result = $this->service->saveField($this->rack, 'title', 'Test Title');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['version']);
        $this->assertEquals('title', $result['field']);
        $this->assertArrayHasKey('save_time_ms', $result);
    }

    /** @test */
    public function test_field_validation_rejects_invalid_field()
    {
        $this->expectException(AutoSaveException::class);
        $this->expectExceptionMessage("Field 'invalid_field' is not allowed for auto-save");

        $this->service->saveField($this->rack, 'invalid_field', 'test value');
    }

    /** @test */
    public function test_field_validation_rejects_oversized_content()
    {
        $oversizedContent = str_repeat('x', 11000); // Over 10000 limit

        $this->expectException(AutoSaveException::class);
        $this->expectExceptionMessage("Field 'how_to_article' exceeds maximum length");

        $this->service->saveField($this->rack, 'how_to_article', $oversizedContent);
    }

    /** @test */
    public function test_tags_field_handling()
    {
        // Create some existing tags
        $existingTag = Tag::factory()->create(['name' => 'existing']);
        $this->rack->tags()->attach($existingTag->id);

        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now()
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        $result = $this->service->saveField($this->rack, 'tags', 'new, test, existing');

        $this->assertTrue($result['success']);
        
        // Verify tags were processed
        $this->rack->refresh();
        $tagNames = $this->rack->tags->pluck('name')->toArray();
        $this->assertContains('new', $tagNames);
        $this->assertContains('test', $tagNames);
        $this->assertContains('existing', $tagNames);
    }

    /** @test */
    public function test_concurrency_conflict_handling()
    {
        $conflictException = new ConcurrencyConflictException(
            'Conflict detected',
            1, // expected version
            2, // current version
            ['title']
        );

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andThrow($conflictException);

        $this->lockingService->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([
                'has_conflicts' => true,
                'conflicts' => [
                    [
                        'field' => 'title',
                        'current_value' => 'Server Title',
                        'incoming_value' => 'Client Title'
                    ]
                ]
            ]);

        $result = $this->service->saveField($this->rack, 'title', 'Client Title');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['conflict_detected']);
        $this->assertEquals(2, $result['current_version']);
        $this->assertEquals(1, $result['expected_version']);
    }

    /** @test */
    public function test_multiple_fields_save_transaction()
    {
        $fieldData = [
            'title' => 'Multi Title',
            'description' => 'Multi Description'
        ];

        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now(),
            'fields_updated' => ['title', 'description']
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        $result = $this->service->saveMultipleFields($this->rack, $fieldData);

        $this->assertTrue($result['success']);
        $this->assertEquals(['title', 'description'], $result['fields']);
        $this->assertEquals(2, $result['version']);
    }

    /** @test */
    public function test_session_tracking()
    {
        $sessionId = 'test_session_123';

        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now(),
            'fields_updated' => ['title']
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        $result = $this->service->saveField(
            $this->rack, 
            'title', 
            'Session Test',
            ['session_id' => $sessionId]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals($sessionId, $result['session_id']);

        // Verify session was tracked in cache
        $sessionKey = "rack_editing_sessions_{$this->rack->id}";
        $sessions = Cache::get($sessionKey);
        $this->assertIsArray($sessions);
        $this->assertArrayHasKey($sessionId, $sessions);
    }

    /** @test */
    public function test_get_current_state()
    {
        $sessionId = 'state_test_session';

        $state = $this->service->getCurrentState($this->rack, $sessionId);

        $this->assertArrayHasKey('version', $state);
        $this->assertArrayHasKey('last_modified', $state);
        $this->assertArrayHasKey('analysis_status', $state);
        $this->assertArrayHasKey('active_sessions', $state);
        $this->assertArrayHasKey('pending_conflicts', $state);
        $this->assertArrayHasKey('fields', $state);
    }

    /** @test */
    public function test_connection_recovery_no_changes()
    {
        $sessionId = 'recovery_test';
        $clientState = [
            'version' => $this->rack->version,
            'last_sync' => now()->subMinutes(5)->toISOString()
        ];

        $result = $this->service->handleConnectionRecovery($this->rack, $sessionId, $clientState);

        $this->assertFalse($result['recovery_needed']);
        $this->assertArrayHasKey('current_state', $result);
    }

    /** @test */
    public function test_connection_recovery_with_version_gap()
    {
        // Update rack to higher version to simulate changes while offline
        $this->rack->update(['version' => 5]);

        $sessionId = 'recovery_test';
        $clientState = [
            'version' => 2, // Client is behind
            'last_sync' => now()->subMinutes(10)->toISOString()
        ];

        $result = $this->service->handleConnectionRecovery($this->rack, $sessionId, $clientState);

        $this->assertTrue($result['recovery_needed']);
        $this->assertArrayHasKey('missed_changes', $result);
        $this->assertEquals(3, $result['missed_changes']['version_gap']); // 5 - 2
    }

    /** @test */
    public function test_cleanup_expired_sessions()
    {
        $sessionId = 'expired_session';
        
        // Create an expired session
        $sessions = [
            $sessionId => [
                'last_activity' => Carbon::now()->subHours(2), // 2 hours old
                'active_fields' => ['title']
            ],
            'active_session' => [
                'last_activity' => Carbon::now()->subMinutes(5), // 5 minutes old
                'active_fields' => ['description']
            ]
        ];

        $sessionKey = "rack_editing_sessions_{$this->rack->id}";
        Cache::put($sessionKey, $sessions);

        $result = $this->service->cleanupExpiredSessions($this->rack);

        $this->assertContains($sessionId, $result['expired_sessions']);
        $this->assertEquals(1, $result['active_sessions']); // Only one active session remains
    }

    /** @test */
    public function test_save_field_with_version_control()
    {
        $clientVersion = 1;
        $options = ['version' => $clientVersion];

        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now(),
            'fields_updated' => ['title']
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->with($this->rack, ['title' => 'Versioned Update'], $clientVersion)
            ->andReturn($mockResult);

        $result = $this->service->saveField($this->rack, 'title', 'Versioned Update', $options);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['version']);
    }

    /** @test */
    public function test_analysis_status_integration()
    {
        $this->rack->update(['status' => 'pending']);

        $mockResult = [
            'model' => $this->rack->fresh(),
            'version' => 2,
            'timestamp' => now(),
            'fields_updated' => ['title']
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        $result = $this->service->saveField($this->rack, 'title', 'Analysis Test');

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['analysis_status']['status']);
        $this->assertTrue($result['analysis_status']['is_complete']);
    }

    /** @test */
    public function test_empty_tags_handling()
    {
        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now()
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        // Test empty tags string
        $result = $this->service->saveField($this->rack, 'tags', '');

        $this->assertTrue($result['success']);
        
        // Verify tags were cleared
        $this->rack->refresh();
        $this->assertCount(0, $this->rack->tags);
    }

    /** @test */
    public function test_malformed_tags_handling()
    {
        $mockResult = [
            'model' => $this->rack,
            'version' => 2,
            'timestamp' => now()
        ];

        $this->lockingService->shouldReceive('updateWithVersion')
            ->once()
            ->andReturn($mockResult);

        // Test tags with short names and extra commas
        $result = $this->service->saveField($this->rack, 'tags', 'a, valid_tag,    , x, another_good_tag');

        $this->assertTrue($result['success']);
        
        // Verify only valid tags were saved (length > 2)
        $this->rack->refresh();
        $tagNames = $this->rack->tags->pluck('name')->toArray();
        
        $this->assertContains('valid_tag', $tagNames);
        $this->assertContains('another_good_tag', $tagNames);
        $this->assertNotContains('a', $tagNames); // Too short
        $this->assertNotContains('x', $tagNames); // Too short
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}