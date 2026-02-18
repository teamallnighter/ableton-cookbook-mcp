<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DrumRackAnalyzerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Storage::fake('local');
    }

    private function createTestDrumRackFile(): UploadedFile
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<Ableton MajorVersion="12" MinorVersion="0" Revision="0">
    <GroupDevicePreset>
        <Name Value="Test Drum Kit"/>
        <Device>
            <DrumGroupDevice>
                <MacroDisplayNames.0 Value="Volume"/>
                <MacroControls.0><Manual Value="64.0"/></MacroControls.0>
            </DrumGroupDevice>
        </Device>
        <BranchPresets>
            <InstrumentBranchPreset>
                <Name Value="Kick"/>
                <KeyRange>
                    <Min Value="36"/>
                    <Max Value="36"/>
                </KeyRange>
                <DevicePresets>
                    <DevicePreset>
                        <Device>
                            <Kick>
                                <On><Manual Value="true"/></On>
                            </Kick>
                        </Device>
                    </DevicePreset>
                </DevicePresets>
            </InstrumentBranchPreset>
            <InstrumentBranchPreset>
                <Name Value="Snare"/>
                <KeyRange>
                    <Min Value="38"/>
                    <Max Value="38"/>
                </KeyRange>
                <DevicePresets>
                    <DevicePreset>
                        <Device>
                            <Snare>
                                <On><Manual Value="true"/></On>
                            </Snare>
                        </Device>
                    </DevicePreset>
                </DevicePresets>
            </InstrumentBranchPreset>
        </BranchPresets>
    </GroupDevicePreset>
</Ableton>';

        // Create a temporary file with gzipped content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_drum_kit_') . '.adg';
        file_put_contents($tempFile, gzencode($xmlContent));

        return new UploadedFile(
            $tempFile,
            'test_drum_kit.adg',
            'application/octet-stream',
            null,
            true // Mark as test file
        );
    }

    public function test_info_endpoint_returns_analyzer_information()
    {
        $response = $this->getJson('/api/v1/drum-racks/info');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'analyzer' => [
                    'name',
                    'version',
                    'type',
                    'supported_extensions',
                    'features',
                    'drum_specific_features'
                ],
                'integration'
            ])
            ->assertJson([
                'success' => true,
                'analyzer' => [
                    'name' => 'Ableton Drum Rack Analyzer',
                    'type' => 'specialized'
                ]
            ]);
    }

    public function test_analyze_endpoint_requires_authentication()
    {
        $file = $this->createTestDrumRackFile();
        
        $response = $this->postJson('/api/v1/drum-racks/analyze', [
            'file' => $file
        ]);
        
        $response->assertStatus(401);
    }

    public function test_analyze_endpoint_validates_file_upload()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze');
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_analyze_endpoint_rejects_invalid_file_type()
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze', [
                'file' => $invalidFile
            ]);
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid file type. Only .adg files are supported.'
            ]);
    }

    public function test_analyze_endpoint_processes_valid_drum_rack()
    {
        $file = $this->createTestDrumRackFile();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze', [
                'file' => $file,
                'options' => [
                    'verbose' => true,
                    'include_performance' => true
                ]
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'statistics' => [
                    'drum_rack_name',
                    'total_chains',
                    'active_chains',
                    'total_devices',
                    'drum_statistics'
                ],
                'validation',
                'data' => [
                    'drum_rack_name',
                    'rack_type',
                    'drum_chains',
                    'drum_statistics',
                    'performance_analysis'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'rack_type' => 'drum_rack'
                ]
            ]);
        
        // Verify drum-specific analysis data
        $data = $response->json('data');
        $this->assertArrayHasKey('drum_chains', $data);
        $this->assertArrayHasKey('drum_statistics', $data);
        $this->assertArrayHasKey('active_pads', $data['drum_statistics']);
        $this->assertArrayHasKey('performance_analysis', $data);
    }

    public function test_validate_endpoint_validates_drum_rack_file()
    {
        $file = $this->createTestDrumRackFile();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/validate', [
                'file' => $file
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'validation' => [
                    'valid',
                    'errors',
                    'warnings',
                    'info'
                ]
            ])
            ->assertJson([
                'success' => true,
                'validation' => [
                    'valid' => true
                ]
            ]);
    }

    public function test_detect_endpoint_detects_drum_rack()
    {
        $file = $this->createTestDrumRackFile();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/detect', [
                'file' => $file
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'is_drum_rack',
                'filename'
            ])
            ->assertJson([
                'success' => true,
                'is_drum_rack' => true
            ]);
    }

    public function test_batch_analyze_endpoint_processes_multiple_files()
    {
        $file1 = $this->createTestDrumRackFile();
        $file2 = $this->createTestDrumRackFile();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze-batch', [
                'files' => [$file1, $file2],
                'options' => [
                    'verbose' => false
                ]
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'summary' => [
                    'total',
                    'successful',
                    'failed'
                ],
                'results'
            ])
            ->assertJson([
                'summary' => [
                    'total' => 2
                ]
            ]);
    }

    public function test_batch_analyze_endpoint_limits_file_count()
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = $this->createTestDrumRackFile();
        }
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze-batch', [
                'files' => $files
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    public function test_endpoints_respect_rate_limiting()
    {
        // This test would require multiple requests to test rate limiting
        // For now, we'll just ensure the middleware is applied correctly
        $file = $this->createTestDrumRackFile();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze', [
                'file' => $file
            ]);
        
        $response->assertStatus(200);
        
        // The rate limiting headers should be present
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_analyze_endpoint_handles_invalid_drum_rack_gracefully()
    {
        // Create a file that looks like .adg but has invalid content
        $invalidXml = '<?xml version="1.0"?><invalid>content</invalid>';
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_') . '.adg';
        file_put_contents($tempFile, gzencode($invalidXml));
        
        $file = new UploadedFile(
            $tempFile,
            'invalid.adg',
            'application/octet-stream',
            null,
            true
        );
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze', [
                'file' => $file
            ]);
        
        // Should either return validation errors or analysis failure
        $this->assertContains($response->status(), [400, 500]);
        $response->assertJson([
            'success' => false
        ]);
    }

    public function test_endpoints_log_user_activity()
    {
        $file = $this->createTestDrumRackFile();
        
        // Clear logs and test
        \Illuminate\Support\Facades\Log::spy();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/drum-racks/analyze', [
                'file' => $file
            ]);
        
        if ($response->status() === 200) {
            \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
                ->with('Drum rack analysis started', \Mockery::type('array'));
                
            \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
                ->with('Drum rack analysis completed', \Mockery::type('array'));
        }
    }
}