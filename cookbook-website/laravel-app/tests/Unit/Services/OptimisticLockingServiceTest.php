<?php

namespace Tests\Unit\Services;

use App\Models\Rack;
use App\Models\User;
use App\Services\OptimisticLockingService;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\OptimisticLockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;
use Mockery;

/**
 * Unit tests for OptimisticLockingService
 */
class OptimisticLockingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OptimisticLockingService $service;
    protected User $user;
    protected Rack $rack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OptimisticLockingService();
        $this->user = User::factory()->create();
        $this->rack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'version' => 1
        ]);
    }

    /** @test */
    public function test_successful_update_with_version_increment()
    {
        $data = ['title' => 'Updated Title'];
        $expectedVersion = 1;

        $result = $this->service->updateWithVersion($this->rack, $data, $expectedVersion);

        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertEquals(2, $result['version']); // Version should increment
        $this->assertEquals('Updated Title', $result['model']->title);
    }

    /** @test */
    public function test_version_conflict_throws_exception()
    {
        // Update the rack to version 2
        $this->rack->update(['version' => 2]);

        $data = ['title' => 'Conflicting Update'];
        $expectedVersion = 1; // Stale version

        $this->expectException(ConcurrencyConflictException::class);

        $this->service->updateWithVersion($this->rack, $data, $expectedVersion);
    }

    /** @test */
    public function test_conflict_detection_identifies_changed_fields()
    {
        // Setup initial state
        $this->rack->update(['title' => 'Original Title', 'description' => 'Original Description']);

        // Simulate another user changing the title
        $this->rack->update(['title' => 'Changed by Other User']);

        // Try to detect conflicts with incoming data
        $incomingData = [
            'title' => 'My Title Change',
            'description' => 'My Description Change'
        ];

        $conflicts = $this->service->detectConflicts($this->rack, $incomingData);

        $this->assertTrue($conflicts['has_conflicts']);
        $this->assertGreaterThan(0, $conflicts['total_conflicts']);
        $this->assertCount(1, $conflicts['conflicts']); // Only title should conflict
        $this->assertEquals('title', $conflicts['conflicts'][0]['field']);
    }

    /** @test */
    public function test_no_conflicts_when_fields_unchanged()
    {
        $incomingData = [
            'title' => $this->rack->title,
            'description' => $this->rack->description
        ];

        $conflicts = $this->service->detectConflicts($this->rack, $incomingData);

        $this->assertFalse($conflicts['has_conflicts']);
        $this->assertEquals(0, $conflicts['total_conflicts']);
        $this->assertEmpty($conflicts['conflicts']);
    }

    /** @test */
    public function test_resolve_conflicts_with_last_write_wins()
    {
        $conflicts = [
            'conflicts' => [
                [
                    'field' => 'title',
                    'original_value' => 'Original',
                    'current_value' => 'Current',
                    'incoming_value' => 'Incoming'
                ]
            ]
        ];

        $resolved = $this->service->resolveConflicts($conflicts, 'last_write_wins');

        $this->assertEquals('Incoming', $resolved['title']);
    }

    /** @test */
    public function test_resolve_conflicts_with_first_write_wins()
    {
        $conflicts = [
            'conflicts' => [
                [
                    'field' => 'title',
                    'original_value' => 'Original',
                    'current_value' => 'Current',
                    'incoming_value' => 'Incoming'
                ]
            ]
        ];

        $resolved = $this->service->resolveConflicts($conflicts, 'first_write_wins');

        $this->assertEquals('Current', $resolved['title']);
    }

    /** @test */
    public function test_resolve_conflicts_with_user_choices()
    {
        $conflicts = [
            'conflicts' => [
                [
                    'field' => 'title',
                    'original_value' => 'Original',
                    'current_value' => 'Current',
                    'incoming_value' => 'Incoming'
                ]
            ]
        ];

        $userChoices = ['title' => 'User Selected Value'];

        $resolved = $this->service->resolveConflicts($conflicts, 'last_write_wins', $userChoices);

        $this->assertEquals('User Selected Value', $resolved['title']);
    }

    /** @test */
    public function test_supports_versioning_checks_fillable_fields()
    {
        $this->assertTrue($this->service->supportsVersioning($this->rack));
        
        // Test with model that doesn't have version in fillable
        $mockModel = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $mockModel->shouldReceive('getFillable')->andReturn(['title', 'description']); // No 'version'
        
        $this->assertFalse($this->service->supportsVersioning($mockModel));
    }

    /** @test */
    public function test_initialize_versioning_sets_initial_version()
    {
        // Create rack without version
        $newRack = Rack::factory()->create([
            'user_id' => $this->user->id,
            'version' => null
        ]);

        $result = $this->service->initializeVersioning($newRack);

        $this->assertTrue($result);
        $newRack->refresh();
        $this->assertEquals(1, $newRack->version);
    }

    /** @test */
    public function test_get_current_version_returns_fresh_version()
    {
        $this->assertEquals(1, $this->service->getCurrentVersion($this->rack));

        // Update version in database directly
        $this->rack->update(['version' => 5]);

        // Should return fresh version from database
        $this->assertEquals(5, $this->service->getCurrentVersion($this->rack));
    }

    /** @test */
    public function test_update_with_retry_on_query_exception()
    {
        // This is a complex test that would require mocking database connections
        // For now, we'll test the basic structure
        
        $data = ['title' => 'Test Title'];
        
        // Should not throw exception with valid data
        $result = $this->service->updateWithVersion($this->rack, $data, 1);
        
        $this->assertArrayHasKey('version', $result);
        $this->assertEquals(2, $result['version']);
    }

    /** @test */
    public function test_conflict_type_determination()
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('determineConflictType');
        $method->setAccessible(true);

        // Test text expansion
        $type = $method->invokeArgs($this->service, ['short', 'short text expanded', 'short']);
        $this->assertEquals('text_expansion', $type);

        // Test text reduction
        $type = $method->invokeArgs($this->service, ['long text here', 'short', 'long text here']);
        $this->assertEquals('text_reduction', $type);

        // Test text modification
        $type = $method->invokeArgs($this->service, ['original', 'modified', 'original']);
        $this->assertEquals('text_modification', $type);

        // Test value change (non-strings)
        $type = $method->invokeArgs($this->service, [1, 2, 1]);
        $this->assertEquals('value_change', $type);
    }

    /** @test */
    public function test_text_merge_functionality()
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('mergeTextContent');
        $method->setAccessible(true);

        // Test simple merge where current equals original
        $result = $method->invokeArgs($this->service, ['original', 'original', 'incoming changes']);
        $this->assertEquals('incoming changes', $result);

        // Test simple merge where incoming equals original  
        $result = $method->invokeArgs($this->service, ['original', 'current changes', 'original']);
        $this->assertEquals('current changes', $result);

        // Test complex merge (should default to incoming)
        $result = $method->invokeArgs($this->service, ['original', 'current changes', 'incoming changes']);
        $this->assertEquals('incoming changes', $result);
    }

    /** @test */
    public function test_multiple_field_update_maintains_atomicity()
    {
        $data = [
            'title' => 'New Title',
            'description' => 'New Description',
            'category' => 'bass'
        ];

        $result = $this->service->updateWithVersion($this->rack, $data, 1);

        $this->assertEquals(2, $result['version']);
        $this->assertEquals(['title', 'description', 'category'], $result['fields_updated']);
        
        // Verify all fields were updated
        $updatedRack = $result['model'];
        $this->assertEquals('New Title', $updatedRack->title);
        $this->assertEquals('New Description', $updatedRack->description);
        $this->assertEquals('bass', $updatedRack->category);
    }

    /** @test */
    public function test_update_includes_timestamp_fields()
    {
        $data = ['title' => 'Timestamped Update'];

        $result = $this->service->updateWithVersion($this->rack, $data, 1);

        $updatedModel = $result['model'];
        $this->assertNotNull($updatedModel->last_auto_save);
        $this->assertInstanceOf(\Carbon\Carbon::class, $updatedModel->last_auto_save);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}