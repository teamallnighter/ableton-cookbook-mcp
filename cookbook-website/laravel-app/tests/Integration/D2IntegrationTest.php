<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\D2DiagramService;

class D2IntegrationTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_d2_service()
    {
        $this->expectNotToPerformAssertions();
        
        try {
            $service = app(D2DiagramService::class);
            $this->addToAssertionCount(1);
        } catch (\Exception $e) {
            $this->fail("Could not instantiate D2DiagramService: " . $e->getMessage());
        }
    }
    
    /** @test */  
    public function d2_cli_is_available()
    {
        $output = shell_exec('which d2 2>/dev/null');
        $this->assertNotEmpty(trim($output), 'D2 CLI is not installed or not in PATH');
    }
    
    /** @test */
    public function d2_can_generate_simple_diagram()
    {
        // Create a simple D2 diagram
        $d2Content = "
direction: right

simple_test: \"Test Rack\" {
  device_a -> device_b -> output
  style.fill: \"#4ecdc4\"
}";
        
        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'd2_test_');
        file_put_contents($tempFile . '.d2', $d2Content);
        
        // Generate SVG
        $output = shell_exec("d2 {$tempFile}.d2 {$tempFile}.svg 2>&1");
        
        $this->assertFileExists($tempFile . '.svg', 'D2 failed to generate SVG: ' . $output);
        
        $svgContent = file_get_contents($tempFile . '.svg');
        $this->assertStringContainsString('<svg', $svgContent);
        $this->assertStringContainsString('Test Rack', $svgContent);
        
        // Cleanup
        unlink($tempFile . '.d2');
        unlink($tempFile . '.svg');
    }
    
    /** @test */
    public function d2_can_generate_ascii_output()
    {
        // Create a simple D2 diagram
        $d2Content = "
direction: right

ascii_test: \"ASCII Rack\" {
  input -> process -> output
}";
        
        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'd2_ascii_test_');
        file_put_contents($tempFile . '.d2', $d2Content);
        
        // Generate ASCII
        $output = shell_exec("d2 {$tempFile}.d2 {$tempFile}.txt 2>&1");
        
        $this->assertFileExists($tempFile . '.txt', 'D2 failed to generate ASCII: ' . $output);
        
        $asciiContent = file_get_contents($tempFile . '.txt');
        $this->assertStringContainsString('ASCII Rack', $asciiContent);
        $this->assertStringContainsString('┌', $asciiContent); // ASCII box drawing
        
        // Cleanup  
        unlink($tempFile . '.d2');
        unlink($tempFile . '.txt');
    }
    
    /** @test */
    public function d2_supports_sketch_mode()
    {
        // Create a simple D2 diagram
        $d2Content = "
direction: right

sketch_test: \"Sketch Style\" {
  device -> output
  style.fill: \"#ff6b6b\"
}";
        
        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'd2_sketch_test_');
        file_put_contents($tempFile . '.d2', $d2Content);
        
        // Generate sketch SVG
        $output = shell_exec("d2 --sketch {$tempFile}.d2 {$tempFile}_sketch.svg 2>&1");
        
        $this->assertFileExists($tempFile . '_sketch.svg', 'D2 failed to generate sketch SVG: ' . $output);
        
        $svgContent = file_get_contents($tempFile . '_sketch.svg');
        $this->assertStringContainsString('<svg', $svgContent);
        $this->assertStringContainsString('Sketch Style', $svgContent);
        
        // Cleanup
        unlink($tempFile . '.d2');
        unlink($tempFile . '_sketch.svg');
    }
}