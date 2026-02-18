<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rack;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ConstitutionalComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin'); // Assuming Spatie permissions

        $this->regularUser = User::factory()->create();

        // Create test racks with various compliance states
        $this->createTestRacksWithComplianceStates();
    }

    /**
     * Test successful compliance report retrieval
     */
    public function test_can_retrieve_constitutional_compliance_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_racks',
                'compliant_racks',
                'non_compliant_racks',
                'compliance_percentage',
                'racks_requiring_reprocessing' => [
                    '*' => [
                        'rack_uuid',
                        'rack_title',
                        'uploaded_at',
                        'reason'
                    ]
                ]
            ]);
    }

    /**
     * Test compliance statistics are calculated correctly
     */
    public function test_compliance_statistics_calculated_correctly(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();

        // Verify calculations
        $this->assertEquals(6, $data['total_racks']); // From setUp
        $this->assertEquals(3, $data['compliant_racks']);
        $this->assertEquals(3, $data['non_compliant_racks']);
        $this->assertEquals(50.0, $data['compliance_percentage']);
    }

    /**
     * Test pagination with limit and offset parameters
     */
    public function test_pagination_with_limit_and_offset(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Test with limit
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?limit=2');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertLessThanOrEqual(2, count($data['racks_requiring_reprocessing']));

        // Test with offset
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?limit=2&offset=1');

        $response->assertStatus(200);
        $secondPageData = $response->json();

        // Should be different data due to offset
        if (count($data['racks_requiring_reprocessing']) > 0 && count($secondPageData['racks_requiring_reprocessing']) > 0) {
            $this->assertNotEquals(
                $data['racks_requiring_reprocessing'][0]['rack_uuid'],
                $secondPageData['racks_requiring_reprocessing'][0]['rack_uuid']
            );
        }
    }

    /**
     * Test filtering by compliance status
     */
    public function test_filtering_by_compliance_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Test filter for non-compliant only
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?status=non_compliant');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertGreaterThan(0, count($data['racks_requiring_reprocessing']));

        // All returned racks should be non-compliant
        foreach ($data['racks_requiring_reprocessing'] as $rack) {
            $this->assertNotEmpty($rack['reason']);
        }

        // Test filter for compliant only
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?status=compliant');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(0, count($data['racks_requiring_reprocessing']));
        $this->assertGreaterThan(0, $data['compliant_racks']);
    }

    /**
     * Test sorting by upload date
     */
    public function test_sorting_by_upload_date(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Test ascending sort
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?sort=uploaded_at&order=asc');

        $response->assertStatus(200);

        $data = $response->json();
        $uploadDates = collect($data['racks_requiring_reprocessing'])
            ->pluck('uploaded_at')
            ->toArray();

        $sortedDates = $uploadDates;
        sort($sortedDates);
        $this->assertEquals($sortedDates, $uploadDates);

        // Test descending sort
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?sort=uploaded_at&order=desc');

        $response->assertStatus(200);

        $descendingData = $response->json();
        $descendingDates = collect($descendingData['racks_requiring_reprocessing'])
            ->pluck('uploaded_at')
            ->toArray();

        $sortedDescendingDates = $uploadDates;
        rsort($sortedDescendingDates);
        $this->assertEquals($sortedDescendingDates, $descendingDates);
    }

    /**
     * Test admin authorization required
     */
    public function test_admin_authorization_required(): void
    {
        // Test unauthorized access
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $response->assertUnauthorized();

        // Test regular user access
        Sanctum::actingAs($this->regularUser);
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $response->assertForbidden();

        // Test admin access
        Sanctum::actingAs($this->adminUser);
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');
        $response->assertStatus(200);
    }

    /**
     * Test compliance reasons are descriptive
     */
    public function test_compliance_reasons_are_descriptive(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();

        foreach ($data['racks_requiring_reprocessing'] as $rack) {
            $this->assertNotEmpty($rack['reason']);
            $this->assertIsString($rack['reason']);

            // Verify reason contains expected compliance violation descriptions
            $validReasons = [
                'No enhanced analysis performed',
                'Incomplete nested chain detection',
                'Analysis predates constitutional requirement',
                'Failed constitutional compliance check'
            ];

            $reasonFound = false;
            foreach ($validReasons as $validReason) {
                if (str_contains($rack['reason'], $validReason)) {
                    $reasonFound = true;
                    break;
                }
            }

            $this->assertTrue($reasonFound, "Invalid compliance reason: {$rack['reason']}");
        }
    }

    /**
     * Test compliance percentage calculation edge cases
     */
    public function test_compliance_percentage_edge_cases(): void
    {
        // Clear existing racks
        Rack::query()->delete();
        EnhancedRackAnalysis::query()->delete();

        Sanctum::actingAs($this->adminUser);

        // Test with no racks
        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(0, $data['total_racks']);
        $this->assertEquals(0, $data['compliant_racks']);
        $this->assertEquals(0, $data['non_compliant_racks']);
        $this->assertEquals(0.0, $data['compliance_percentage']);

        // Test with all compliant racks
        $compliantRack = Rack::factory()->create([
            'enhanced_analysis_complete' => true
        ]);

        EnhancedRackAnalysis::factory()->create([
            'rack_id' => $compliantRack->id,
            'constitutional_compliant' => true
        ]);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['total_racks']);
        $this->assertEquals(1, $data['compliant_racks']);
        $this->assertEquals(0, $data['non_compliant_racks']);
        $this->assertEquals(100.0, $data['compliance_percentage']);
    }

    /**
     * Test performance with large dataset
     */
    public function test_performance_with_large_dataset(): void
    {
        // Create many racks for performance testing
        Rack::factory()->count(100)->create([
            'enhanced_analysis_complete' => false
        ]);

        Sanctum::actingAs($this->adminUser);

        $startTime = microtime(true);

        $response = $this->getJson('/api/v1/analysis/constitutional-compliance?limit=50');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        // Should respond within 1 second even with large dataset
        $this->assertLessThan(1000, $duration, 'Compliance API should respond within 1 second');
    }

    /**
     * Test date filtering for recent uploads
     */
    public function test_date_filtering_for_recent_uploads(): void
    {
        Sanctum::actingAs($this->adminUser);

        $lastWeek = now()->subWeek()->toDateString();

        $response = $this->getJson("/api/v1/analysis/constitutional-compliance?uploaded_after={$lastWeek}");

        $response->assertStatus(200);

        $data = $response->json();

        foreach ($data['racks_requiring_reprocessing'] as $rack) {
            $uploadDate = \Carbon\Carbon::parse($rack['uploaded_at']);
            $this->assertTrue($uploadDate->isAfter($lastWeek));
        }
    }

    /**
     * Test caching of compliance report
     */
    public function test_caching_of_compliance_report(): void
    {
        Sanctum::actingAs($this->adminUser);

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

    /**
     * Create test racks with various compliance states
     */
    private function createTestRacksWithComplianceStates(): void
    {
        // Compliant racks
        for ($i = 0; $i < 3; $i++) {
            $rack = Rack::factory()->create([
                'enhanced_analysis_complete' => true,
                'title' => "Compliant Rack {$i}"
            ]);

            EnhancedRackAnalysis::factory()->create([
                'rack_id' => $rack->id,
                'constitutional_compliant' => true,
                'has_nested_chains' => true,
                'max_nesting_depth' => 2
            ]);
        }

        // Non-compliant racks
        for ($i = 0; $i < 3; $i++) {
            Rack::factory()->create([
                'enhanced_analysis_complete' => false,
                'title' => "Non-Compliant Rack {$i}"
            ]);
        }
    }
}