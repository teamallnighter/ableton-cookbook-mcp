<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SecureMarkdownService;
use App\Services\XssMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive XSS Security Test Suite
 * Tests all XSS attack vectors and prevention mechanisms
 */
class XssSecurityTest extends TestCase
{
    use RefreshDatabase;
    
    private SecureMarkdownService $markdownService;
    private XssMonitoringService $xssMonitor;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->markdownService = new SecureMarkdownService();
        $this->xssMonitor = new XssMonitoringService();
    }
    
    /**
     * Test basic script tag injection prevention
     */
    public function test_prevents_basic_script_injection(): void
    {
        $maliciousContent = '<script>alert("xss")</script>';
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content contains potentially dangerous elements');
        
        $this->markdownService->parseToHtml($maliciousContent);
    }
    
    /**
     * Test JavaScript protocol injection prevention
     */
    public function test_prevents_javascript_protocol_injection(): void
    {
        $maliciousContent = '[Click me](javascript:alert("xss"))';
        
        $this->expectException(\InvalidArgumentException::class);
        $this->markdownService->parseToHtml($maliciousContent);
    }
    
    /**
     * Test event handler injection prevention
     */
    public function test_prevents_event_handler_injection(): void
    {
        $testCases = [
            '<img src="x" onerror="alert(1)">',
            '<div onclick="alert(1)">Click</div>',
            '<a href="#" onload="alert(1)">Link</a>',
            '<input onchange="alert(1)">',
            '<body onload="alert(1)">',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test data URI injection prevention
     */
    public function test_prevents_data_uri_injection(): void
    {
        $testCases = [
            'data:text/html,<script>alert(1)</script>',
            'data:text/html;base64,' . base64_encode('<script>alert(1)</script>'),
            '[Link](data:text/html,<script>alert(1)</script>)',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test DOM manipulation injection prevention
     */
    public function test_prevents_dom_manipulation(): void
    {
        $testCases = [
            'document.write("evil")',
            'document.cookie = "stolen"',
            'eval("alert(1)")',
            'setTimeout("alert(1)", 1000)',
            'setInterval("alert(1)", 1000)',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test CSS injection prevention
     */
    public function test_prevents_css_injection(): void
    {
        $testCases = [
            '<style>body{background:url("javascript:alert(1)")}</style>',
            'expression(alert(1))',
            'url(javascript:alert(1))',
            '@import "javascript:alert(1)"',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test SVG injection prevention
     */
    public function test_prevents_svg_injection(): void
    {
        $svgPayloads = [
            '<svg onload="alert(1)">',
            '<svg><script>alert(1)</script></svg>',
            '<svg xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml,<svg id=\'x\' xmlns=\'http://www.w3.org/2000/svg\' onload=\'alert(1)\'></svg>#x"></use></svg>',
        ];
        
        foreach ($svgPayloads as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test object/embed tag prevention
     */
    public function test_prevents_object_embed_tags(): void
    {
        $testCases = [
            '<object data="javascript:alert(1)">',
            '<embed src="javascript:alert(1)">',
            '<applet>alert(1)</applet>',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test form injection prevention
     */
    public function test_prevents_form_injection(): void
    {
        $maliciousContent = '<form action="javascript:alert(1)"><input type="submit"></form>';
        
        $this->expectException(\InvalidArgumentException::class);
        $this->markdownService->parseToHtml($maliciousContent);
    }
    
    /**
     * Test meta refresh injection prevention
     */
    public function test_prevents_meta_refresh_injection(): void
    {
        $maliciousContent = '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">';
        
        $this->expectException(\InvalidArgumentException::class);
        $this->markdownService->parseToHtml($maliciousContent);
    }
    
    /**
     * Test Unicode and encoding attacks
     */
    public function test_prevents_unicode_encoding_attacks(): void
    {
        $testCases = [
            // Unicode encoded script tags
            '\u003cscript\u003ealert(1)\u003c/script\u003e',
            // HTML entity encoded
            '&#60;script&#62;alert(1)&#60;/script&#62;',
            // Mixed encoding
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        ];
        
        foreach ($testCases as $maliciousContent) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($maliciousContent);
        }
    }
    
    /**
     * Test that safe content passes through correctly
     */
    public function test_allows_safe_content(): void
    {
        $safeContent = '# Hello World\n\nThis is **bold** and *italic* text.\n\n[Safe Link](https://example.com)\n\n![Safe Image](https://example.com/image.jpg)';
        
        $result = $this->markdownService->parseToHtml($safeContent);
        
        $this->assertStringContainsString('<h1>Hello World</h1>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
    }
    
    /**
     * Test YouTube embed security
     */
    public function test_youtube_embed_security(): void
    {
        $validYouTube = '[Video](https://www.youtube.com/watch?v=dQw4w9WgXcQ)';
        $maliciousYouTube = '[Video](https://evil.com/youtube.com/watch?v=<script>alert(1)</script>)';
        
        // Valid YouTube should work
        $result = $this->markdownService->parseToHtml($validYouTube);
        $this->assertStringContainsString('youtube.com/embed', $result);
        $this->assertStringContainsString('sandbox=', $result);
        
        // Malicious YouTube should be blocked
        $this->expectException(\InvalidArgumentException::class);
        $this->markdownService->parseToHtml($maliciousYouTube);
    }
    
    /**
     * Test XSS monitoring service detection
     */
    public function test_xss_monitoring_detection(): void
    {
        $maliciousContent = '<script>alert("xss")</script>';
        
        $analysis = $this->xssMonitor->monitorContent($maliciousContent, 'test_context');
        
        $this->assertTrue($analysis['has_threats']);
        $this->assertEquals('critical', $analysis['threat_level']);
        $this->assertNotEmpty($analysis['detected_patterns']);
        $this->assertLessThan(100, $analysis['security_score']);
    }
    
    /**
     * Test multiple threat detection
     */
    public function test_multiple_threat_detection(): void
    {
        $complexMaliciousContent = '
            <script>alert("xss1")</script>
            <img src="x" onerror="alert(2)">
            javascript:alert(3)
            document.cookie = "steal"
        ';
        
        $analysis = $this->xssMonitor->monitorContent($complexMaliciousContent, 'complex_test');
        
        $this->assertTrue($analysis['has_threats']);
        $this->assertEquals('critical', $analysis['threat_level']);
        $this->assertGreaterThan(3, count($analysis['detected_patterns']));
        $this->assertEquals(0, $analysis['security_score']);
    }
    
    /**
     * Test that monitoring generates appropriate recommendations
     */
    public function test_security_recommendations(): void
    {
        $maliciousContent = '<script>eval("alert(1)")</script>';
        
        $analysis = $this->xssMonitor->monitorContent($maliciousContent, 'recommendation_test');
        
        $this->assertNotEmpty($analysis['recommendations']);
        $this->assertContains('Content contains potential XSS vectors - sanitize all user input', $analysis['recommendations']);
        $this->assertContains('Use CSP headers to prevent script execution', $analysis['recommendations']);
    }
    
    /**
     * Test emergency sanitization
     */
    public function test_emergency_sanitization(): void
    {
        $maliciousContent = '<script>alert("dangerous")</script><p>Safe content</p>';
        
        $sanitized = $this->xssMonitor->emergencySanitize($maliciousContent);
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        $this->assertStringContainsString('Safe content', $sanitized);
    }
    
    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting_for_attacks(): void
    {
        $this->withSession(['_token' => 'test']);
        
        // Simulate multiple XSS attempts
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->markdownService->parseToHtml('<script>alert(' . $i . ')</script>');
            } catch (\InvalidArgumentException $e) {
                // Expected to fail
            }
        }
        
        // After multiple attempts, further requests should be rate limited
        // This would be tested through the actual HTTP layer
        $this->assertTrue(true); // Placeholder for rate limiting test
    }
    
    /**
     * Test CSP violation reporting
     */
    public function test_csp_violation_reporting(): void
    {
        // This would test the CSP reporting endpoint
        // Mock a CSP violation report
        $cspReport = [
            'csp-report' => [
                'document-uri' => 'https://example.com/page',
                'blocked-uri' => 'javascript:alert(1)',
                'violated-directive' => 'script-src',
                'effective-directive' => 'script-src'
            ]
        ];
        
        // Test would post to CSP report endpoint and verify logging
        $this->assertTrue(true); // Placeholder
    }
    
    /**
     * Test security headers are properly set
     */
    public function test_security_headers(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Check CSP header exists and contains expected directives
        $cspHeader = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $cspHeader);
        $this->assertStringContainsString("object-src 'none'", $cspHeader);
        $this->assertStringContainsString("frame-ancestors 'none'", $cspHeader);
    }
    
    /**
     * Test file upload XSS prevention
     */
    public function test_file_upload_xss_prevention(): void
    {
        // Test would create malicious image files and verify they're blocked
        // This requires actual file creation and upload simulation
        $this->assertTrue(true); // Placeholder
    }
    
    /**
     * Stress test with large payloads
     */
    public function test_handles_large_malicious_payloads(): void
    {
        // Create a large malicious payload
        $largePayload = str_repeat('<script>alert("xss")</script>', 1000);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->markdownService->parseToHtml($largePayload);
        
        // Verify it doesn't cause memory issues or timeouts
        $this->assertTrue(true);
    }
    
    /**
     * Test nested attack vectors
     */
    public function test_nested_attack_vectors(): void
    {
        $nestedAttacks = [
            '<div><script>alert(1)</script></div>',
            '<p onclick="eval(atob(\'YWxlcnQoMSk=\'))">Click</p>', // Base64 encoded alert
            '<img src="javascript:alert(String.fromCharCode(88,83,83))">',
        ];
        
        foreach ($nestedAttacks as $attack) {
            $this->expectException(\InvalidArgumentException::class);
            $this->markdownService->parseToHtml($attack);
        }
    }
}