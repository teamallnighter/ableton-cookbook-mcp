<?php

namespace Tests\Unit\Services;

use App\Services\DrumRackAnalyzerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DrumRackAnalyzerServiceTest extends TestCase
{
    use RefreshDatabase;

    private DrumRackAnalyzerService $service;
    private string $testDrumRackPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DrumRackAnalyzerService::class);
        
        // Create a test storage disk for temporary files
        Storage::fake('local');
        
        // Create a minimal test .adg file (gzipped XML)
        $this->testDrumRackPath = $this->createTestDrumRackFile();
    }

    private function createTestDrumRackFile(): string
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<Ableton MajorVersion="12" MinorVersion="0" Revision="0">
    <GroupDevicePreset>
        <Name Value="Test Drum Rack"/>
        <Device>
            <DrumGroupDevice>
                <MacroDisplayNames.0 Value="Macro 1"/>
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
                <VelocityRange>
                    <Min Value="1"/>
                    <Max Value="127"/>
                </VelocityRange>
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
        </BranchPresets>
    </GroupDevicePreset>
</Ableton>';

        // Create temporary file with gzipped content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_drum_rack_') . '.adg';
        file_put_contents($tempFile, gzencode($xmlContent));
        
        return $tempFile;
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testDrumRackPath)) {
            unlink($this->testDrumRackPath);
        }
        parent::tearDown();
    }

    public function test_drum_rack_analyzer_service_can_be_instantiated()
    {
        $this->assertInstanceOf(DrumRackAnalyzerService::class, $this->service);
    }

    public function test_validates_drum_rack_file_successfully()
    {
        $validation = $this->service->validateDrumRackFile($this->testDrumRackPath);
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertArrayHasKey('info', $validation);
    }

    public function test_validates_invalid_file_path()
    {
        $validation = $this->service->validateDrumRackFile('/nonexistent/file.adg');
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('File does not exist', $validation['errors']);
    }

    public function test_validates_invalid_extension()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.txt';
        file_put_contents($tempFile, 'test content');
        
        $validation = $this->service->validateDrumRackFile($tempFile);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('File must have .adg extension', $validation['errors']);
        
        unlink($tempFile);
    }

    public function test_analyzes_drum_rack_successfully()
    {
        $result = $this->service->analyzeDrumRack($this->testDrumRackPath, [
            'verbose' => false,
            'include_performance' => true
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('drum_rack_name', $result['data']);
        $this->assertArrayHasKey('drum_chains', $result['data']);
        $this->assertArrayHasKey('drum_statistics', $result['data']);
        $this->assertArrayHasKey('performance_analysis', $result['data']);
        
        // Check drum-specific features
        $this->assertEquals('drum_rack', $result['data']['rack_type']);
        $this->assertIsArray($result['data']['drum_chains']);
        $this->assertArrayHasKey('active_pads', $result['data']['drum_statistics']);
    }

    public function test_detects_drum_rack_correctly()
    {
        $isDrumRack = $this->service->isDrumRack($this->testDrumRackPath);
        $this->assertTrue($isDrumRack);
    }

    public function test_gets_analysis_statistics()
    {
        $analysisResult = $this->service->analyzeDrumRack($this->testDrumRackPath);
        $statistics = $this->service->getAnalysisStatistics($analysisResult);
        
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('drum_rack_name', $statistics);
        $this->assertArrayHasKey('total_chains', $statistics);
        $this->assertArrayHasKey('active_chains', $statistics);
        $this->assertArrayHasKey('total_devices', $statistics);
        $this->assertArrayHasKey('drum_statistics', $statistics);
        $this->assertArrayHasKey('complexity_score', $statistics);
    }

    public function test_gets_supported_extensions()
    {
        $extensions = $this->service->getSupportedExtensions();
        
        $this->assertIsArray($extensions);
        $this->assertContains('adg', $extensions);
    }

    public function test_analyzes_drum_rack_with_export_json()
    {
        $outputFolder = storage_path('app/test-drum-analysis');
        if (!is_dir($outputFolder)) {
            mkdir($outputFolder, 0755, true);
        }

        $result = $this->service->analyzeDrumRack($this->testDrumRackPath, [
            'export_json' => true,
            'output_folder' => $outputFolder
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('exported_json_path', $result['data']);
        
        // Clean up
        if (isset($result['data']['exported_json_path']) && file_exists($result['data']['exported_json_path'])) {
            unlink($result['data']['exported_json_path']);
        }
        if (is_dir($outputFolder)) {
            rmdir($outputFolder);
        }
    }

    public function test_batch_analysis()
    {
        // Create a second test file
        $secondTestFile = $this->createTestDrumRackFile();
        
        $result = $this->service->analyzeDrumRackBatch([
            $this->testDrumRackPath,
            $secondTestFile
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEquals(2, $result['summary']['total']);
        
        // Clean up second test file
        if (file_exists($secondTestFile)) {
            unlink($secondTestFile);
        }
    }

    public function test_handles_invalid_drum_rack_gracefully()
    {
        // Create invalid .adg file
        $invalidFile = tempnam(sys_get_temp_dir(), 'invalid_') . '.adg';
        file_put_contents($invalidFile, gzencode('<invalid>xml</invalid>'));
        
        $result = $this->service->analyzeDrumRack($invalidFile);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        
        unlink($invalidFile);
    }

    public function test_finds_drum_rack_files()
    {
        // Create a temporary directory with test files
        $testDir = sys_get_temp_dir() . '/drum_rack_test_' . uniqid();
        mkdir($testDir);
        
        // Create test .adg files
        file_put_contents($testDir . '/test1.adg', 'test content');
        file_put_contents($testDir . '/test2.adg', 'test content');
        file_put_contents($testDir . '/ignore.txt', 'ignore this');
        
        $files = $this->service->findDrumRackFiles($testDir);
        
        $this->assertCount(2, $files);
        $this->assertContains($testDir . '/test1.adg', $files);
        $this->assertContains($testDir . '/test2.adg', $files);
        
        // Clean up
        unlink($testDir . '/test1.adg');
        unlink($testDir . '/test2.adg');
        unlink($testDir . '/ignore.txt');
        rmdir($testDir);
    }
}