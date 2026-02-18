<?php

namespace App\Http\Middleware;

use App\Services\VirusScanningService;
use App\Jobs\VirusScanFileJob;
use App\Enums\ScanStatus;
use App\Enums\ThreatLevel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * File Upload Security Middleware
 * 
 * Provides comprehensive security protection for file uploads including:
 * - Pre-upload validation and sanitization
 * - Malware pattern detection
 * - File type verification and content analysis
 * - Rate limiting and abuse prevention
 * - Real-time virus scanning integration
 * - Security incident logging and alerting
 */
class FileUploadSecurity
{
    private VirusScanningService $virusScanner;
    private array $dangerousExtensions;
    private array $suspiciousMimeTypes;
    private array $malwarePatterns;
    
    public function __construct(VirusScanningService $virusScanner)
    {
        $this->virusScanner = $virusScanner;
        $this->initializeSecurityPatterns();
    }
    
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, ...$parameters): Response
    {
        // Check if this request contains file uploads
        if (!$this->hasFileUploads($request)) {
            return $next($request);
        }
        
        Log::info('File upload security check initiated', [
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'url' => $request->url(),
            'files_count' => count($request->allFiles())
        ]);
        
        try {
            // Rate limiting check
            if ($this->isRateLimited($request)) {
                return $this->createSecurityResponse(
                    'Too many file upload attempts. Please try again later.',
                    429,
                    'rate_limit_exceeded'
                );
            }
            
            // Pre-upload security validation
            $validationResult = $this->validateUploadedFiles($request);
            
            if (!$validationResult['passed']) {
                $this->logSecurityViolation($request, 'file_validation_failed', $validationResult);
                
                return $this->createSecurityResponse(
                    $validationResult['message'],
                    403,
                    'validation_failed',
                    $validationResult['details']
                );
            }
            
            // Apply rate limiting
            $this->applyRateLimit($request);
            
            // Continue with the request
            $response = $next($request);
            
            // Post-upload security processing
            $this->scheduleVirusScanning($request);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('File upload security middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $this->sanitizeRequestData($request)
            ]);
            
            return $this->createSecurityResponse(
                'Security validation failed. Please try again.',
                500,
                'security_check_error'
            );
        }
    }
    
    /**
     * Initialize security patterns and rules
     */
    private function initializeSecurityPatterns(): void
    {
        $this->dangerousExtensions = [
            // Executable files
            'exe', 'com', 'scr', 'bat', 'cmd', 'pif', 'msi', 'dll',
            
            // Script files
            'php', 'php3', 'php4', 'php5', 'pht', 'phtml', 'phps',
            'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'cfc', 'pl', 'cgi',
            'sh', 'bash', 'zsh', 'fish', 'csh', 'ksh',
            
            // Web files that can execute
            'js', 'vbs', 'vbe', 'ws', 'wsf', 'wsc', 'wsh',
            
            // Archive files (can contain executables)
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz',
            
            // Other risky formats
            'jar', 'deb', 'rpm', 'dmg', 'pkg', 'app',
            'swf', 'fla', 'hta', 'reg', 'inf', 'scf',
        ];
        
        $this->suspiciousMimeTypes = [
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/x-executable',
            'application/x-winexe',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/php',
            'text/x-script.phps',
            'application/x-javascript',
            'text/javascript',
            'application/javascript',
        ];
        
        $this->malwarePatterns = [
            // PHP backdoors
            '/<\?php.*eval\s*\(/is',
            '/<\?php.*base64_decode\s*\(/is',
            '/<\?php.*system\s*\(/is',
            '/<\?php.*exec\s*\(/is',
            '/<\?php.*shell_exec\s*\(/is',
            
            // JavaScript malware
            '/document\.write\s*\(\s*unescape\s*\(/is',
            '/eval\s*\(\s*atob\s*\(/is',
            '/Function\s*\(\s*atob\s*\(/is',
            
            // Binary signatures
            '/\x4d\x5a.*\x50\x45\x00\x00/s', // PE executable
            '/\x7f\x45\x4c\x46/s',           // ELF executable
            
            // Embedded scripts in images
            '/\xff\xd8\xff.*<\?php/s',  // PHP in JPEG
            '/\x89PNG.*<\?php/s',       // PHP in PNG
            '/GIF89a.*<\?php/s',        // PHP in GIF
            
            // Suspicious strings
            '/backdoor|webshell|rootkit|trojan/i',
            '/c99shell|r57shell|wso|b374k|p0wny/i',
        ];
    }
    
    /**
     * Check if request contains file uploads
     */
    private function hasFileUploads(Request $request): bool
    {
        return !empty($request->allFiles());
    }
    
    /**
     * Check rate limiting for file uploads
     */
    private function isRateLimited(Request $request): bool
    {
        $key = 'file_uploads:' . $request->ip();
        
        // Allow 10 file uploads per hour per IP
        return RateLimiter::tooManyAttempts($key, 10);
    }
    
    /**
     * Apply rate limiting
     */
    private function applyRateLimit(Request $request): void
    {
        $key = 'file_uploads:' . $request->ip();
        RateLimiter::hit($key, 3600); // 1 hour
    }
    
    /**
     * Validate all uploaded files
     */
    private function validateUploadedFiles(Request $request): array
    {
        $allFiles = $request->allFiles();
        $violations = [];
        
        foreach ($this->flattenFiles($allFiles) as $fieldName => $file) {
            if (!($file instanceof UploadedFile)) {
                continue;
            }
            
            $fileViolations = $this->validateSingleFile($file, $fieldName);
            
            if (!empty($fileViolations)) {
                $violations[$fieldName] = $fileViolations;
            }
        }
        
        if (!empty($violations)) {
            return [
                'passed' => false,
                'message' => 'File security validation failed',
                'details' => $violations
            ];
        }
        
        return ['passed' => true];
    }
    
    /**
     * Validate a single uploaded file
     */
    private function validateSingleFile(UploadedFile $file, string $fieldName): array
    {
        $violations = [];
        
        // Basic file validation
        if (!$file->isValid()) {
            $violations[] = 'File upload failed: ' . $file->getErrorMessage();
            return $violations;
        }
        
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        
        Log::debug('Validating uploaded file', [
            'field' => $fieldName,
            'filename' => $filename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size
        ]);
        
        // Check file extension
        if (in_array($extension, $this->dangerousExtensions)) {
            $violations[] = "Dangerous file extension detected: {$extension}";
        }
        
        // Check MIME type
        if (in_array($mimeType, $this->suspiciousMimeTypes)) {
            $violations[] = "Suspicious MIME type detected: {$mimeType}";
        }
        
        // Check file size (prevent DoS attacks)
        $maxSize = config('security.image_upload.max_file_size', 5120) * 1024; // Convert KB to bytes
        if ($size > $maxSize) {
            $violations[] = "File too large: " . $this->formatFileSize($size);
        }
        
        // Check for suspicious filename patterns
        if ($this->hasSuspiciousFilename($filename)) {
            $violations[] = "Suspicious filename pattern detected";
        }
        
        // Content-based validation
        $contentViolations = $this->validateFileContent($file);
        $violations = array_merge($violations, $contentViolations);
        
        // Double extension check
        if ($this->hasDoubleExtension($filename)) {
            $violations[] = "Double file extension detected (possible evasion attempt)";
        }
        
        // Null byte injection check
        if (str_contains($filename, "\x00")) {
            $violations[] = "Null byte detected in filename";
        }
        
        return $violations;
    }
    
    /**
     * Validate file content for malware patterns
     */
    private function validateFileContent(UploadedFile $file): array
    {
        $violations = [];
        
        try {
            $content = file_get_contents($file->getRealPath());
            
            if ($content === false) {
                $violations[] = "Cannot read file content for validation";
                return $violations;
            }
            
            // Check for malware patterns
            foreach ($this->malwarePatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "Malicious content pattern detected";
                    break; // Don't reveal specific patterns
                }
            }
            
            // Check for embedded executables in allowed file types
            if ($this->isImageFile($file)) {
                if ($this->hasEmbeddedExecutable($content)) {
                    $violations[] = "Embedded executable detected in image file";
                }
            }
            
            // Check for suspicious file headers
            if ($this->hasSuspiciousFileHeader($content, $file->getClientOriginalExtension())) {
                $violations[] = "File header mismatch or suspicious content detected";
            }
            
        } catch (\Exception $e) {
            Log::warning('Content validation failed', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            $violations[] = "Content validation failed";
        }
        
        return $violations;
    }
    
    /**
     * Check for suspicious filename patterns
     */
    private function hasSuspiciousFilename(string $filename): bool
    {
        $suspiciousPatterns = [
            '/\.(php|asp|jsp|cgi|pl)\./i',  // Double extensions with script files
            '/\.(php|asp|jsp|cgi|pl)$/i',   // Script file extensions
            '/\.htaccess$/i',               // Apache config file
            '/\.htpasswd$/i',               // Apache password file
            '/web\.config$/i',              // IIS config file
            '/\.(cmd|bat|sh|exe|com|scr|pif)$/i', // Executable extensions
            '/[<>:"|?*\\\\\/]/',            // Invalid filename characters
            '/^\./i',                       // Hidden files
            '/\s+\.(php|asp|jsp|exe)$/i',   // Space before dangerous extension
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for double file extensions
     */
    private function hasDoubleExtension(string $filename): bool
    {
        $parts = explode('.', $filename);
        
        if (count($parts) < 3) {
            return false;
        }
        
        // Check if second-to-last part is a dangerous extension
        $secondExtension = strtolower($parts[count($parts) - 2]);
        
        return in_array($secondExtension, $this->dangerousExtensions);
    }
    
    /**
     * Check if file is an image
     */
    private function isImageFile(UploadedFile $file): bool
    {
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff',
        ];
        
        return in_array($file->getMimeType(), $imageMimeTypes);
    }
    
    /**
     * Check for embedded executables in content
     */
    private function hasEmbeddedExecutable(string $content): bool
    {
        $executableSignatures = [
            "\x4d\x5a",         // PE executable (MZ header)
            "\x7f\x45\x4c\x46", // ELF executable
            "\xca\xfe\xba\xbe", // Java class file
            "\x50\x4b\x03\x04", // ZIP file (could contain executables)
        ];
        
        foreach ($executableSignatures as $signature) {
            if (str_contains($content, $signature)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for suspicious file headers
     */
    private function hasSuspiciousFileHeader(string $content, string $extension): bool
    {
        if (strlen($content) < 16) {
            return false;
        }
        
        $header = substr($content, 0, 16);
        
        // Expected headers for common file types
        $expectedHeaders = [
            'jpg' => ["\xff\xd8\xff"],
            'jpeg' => ["\xff\xd8\xff"],
            'png' => ["\x89\x50\x4e\x47"],
            'gif' => ["\x47\x49\x46\x38"],
            'pdf' => ["\x25\x50\x44\x46"],
            'zip' => ["\x50\x4b\x03\x04"],
        ];
        
        $extension = strtolower($extension);
        
        if (!isset($expectedHeaders[$extension])) {
            return false; // Unknown extension, can't validate
        }
        
        $validHeaders = $expectedHeaders[$extension];
        
        foreach ($validHeaders as $validHeader) {
            if (str_starts_with($header, $validHeader)) {
                return false; // Valid header found
            }
        }
        
        return true; // No valid header found for this extension
    }
    
    /**
     * Schedule virus scanning for uploaded files
     */
    private function scheduleVirusScanning(Request $request): void
    {
        $allFiles = $request->allFiles();
        
        foreach ($this->flattenFiles($allFiles) as $fieldName => $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }
            
            // Store the file temporarily and schedule scanning
            $tempPath = $file->getRealPath();
            
            if ($tempPath && file_exists($tempPath)) {
                $context = $this->determineContext($request, $fieldName);
                
                Log::info('Scheduling virus scan for uploaded file', [
                    'field' => $fieldName,
                    'filename' => $file->getClientOriginalName(),
                    'context' => $context,
                    'user_id' => auth()->id()
                ]);
                
                // Schedule async virus scanning
                VirusScanFileJob::dispatch(
                    $tempPath,
                    $context,
                    auth()->id(),
                    [
                        'original_filename' => $file->getClientOriginalName(),
                        'field_name' => $fieldName,
                        'upload_ip' => $request->ip(),
                        'upload_time' => now()->toISOString()
                    ]
                )->delay(now()->addSeconds(5)); // Small delay to ensure file is properly stored
            }
        }
    }
    
    /**
     * Determine scanning context from request
     */
    private function determineContext(Request $request, string $fieldName): string
    {
        $route = $request->route();
        
        if (!$route) {
            return 'unknown';
        }
        
        $routeName = $route->getName();
        $uri = $request->path();
        
        // Map routes to contexts
        if (str_contains($uri, 'rack') || str_contains($routeName ?? '', 'rack')) {
            return 'rack_upload';
        } elseif (str_contains($uri, 'image') || str_contains($routeName ?? '', 'image')) {
            return 'image_upload';
        } elseif (str_contains($fieldName, 'avatar') || str_contains($fieldName, 'profile')) {
            return 'profile_upload';
        } elseif (str_contains($uri, 'admin')) {
            return 'admin_upload';
        } else {
            return 'general_upload';
        }
    }
    
    /**
     * Flatten nested file arrays
     */
    private function flattenFiles(array $files): array
    {
        $flattened = [];
        
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                foreach ($this->flattenFiles($value) as $nestedKey => $nestedValue) {
                    $flattened[$key . '.' . $nestedKey] = $nestedValue;
                }
            } else {
                $flattened[$key] = $value;
            }
        }
        
        return $flattened;
    }
    
    /**
     * Log security violation
     */
    private function logSecurityViolation(Request $request, string $type, array $details): void
    {
        $violationId = 'SEC-' . date('Ymd-His') . '-' . Str::random(6);
        
        $violationData = [
            'violation_id' => $violationId,
            'type' => $type,
            'severity' => 'high',
            'timestamp' => now()->toISOString(),
            'request_data' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ],
            'violation_details' => $details
        ];
        
        Log::warning('File upload security violation', $violationData);
        
        // Store in cache for security dashboard
        Cache::put("security_violation_{$violationId}", $violationData, 86400);
        
        // Increment violation counter for IP
        $ipViolationsKey = 'ip_violations:' . $request->ip();
        $violations = Cache::get($ipViolationsKey, 0) + 1;
        Cache::put($ipViolationsKey, $violations, 3600);
        
        // Alert if too many violations from same IP
        if ($violations >= 5) {
            Log::critical('Multiple security violations from IP', [
                'ip' => $request->ip(),
                'violation_count' => $violations,
                'latest_violation' => $violationData
            ]);
        }
    }
    
    /**
     * Create security response
     */
    private function createSecurityResponse(
        string $message,
        int $statusCode = 403,
        string $errorCode = 'security_violation',
        array $details = []
    ): Response {
        $responseData = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'timestamp' => now()->toISOString(),
        ];
        
        if (!empty($details) && config('app.debug')) {
            $responseData['details'] = $details;
        }
        
        return response()->json($responseData, $statusCode);
    }
    
    /**
     * Sanitize request data for logging
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->except(['password', 'password_confirmation', '_token']);
        
        // Remove file contents from logging
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $data[$key] = [
                    'filename' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                    'mime_type' => $value->getMimeType()
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unit];
    }
}