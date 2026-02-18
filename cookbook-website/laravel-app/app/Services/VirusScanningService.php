<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Http\UploadedFile;
use App\Enums\ScanStatus;
use App\Enums\ThreatLevel;
use Exception;

/**
 * Comprehensive Virus Scanning Service
 * 
 * Provides multi-layered malware detection for file uploads including:
 * - ClamAV integration for signature-based scanning
 * - Heuristic analysis for suspicious patterns
 * - File type validation and content verification
 * - Quarantine management for infected files
 * - Real-time threat monitoring and alerting
 */
class VirusScanningService
{
    private const MAX_FILE_SIZE = 52428800; // 50MB
    private const SCAN_TIMEOUT = 120; // 2 minutes
    private const QUARANTINE_PATH = 'quarantine';
    
    private array $suspiciousPatterns;
    private array $malwareSignatures;
    private array $dangerousExtensions;
    
    public function __construct()
    {
        $this->initializePatterns();
    }
    
    /**
     * Initialize malware detection patterns and signatures
     */
    private function initializePatterns(): void
    {
        $this->suspiciousPatterns = [
            // PHP backdoors and webshells
            '/<\?php.*eval\s*\(/is' => 'PHP eval backdoor',
            '/<\?php.*base64_decode\s*\(/is' => 'PHP base64 backdoor',
            '/<\?php.*system\s*\(/is' => 'PHP system call',
            '/<\?php.*exec\s*\(/is' => 'PHP exec call',
            '/<\?php.*shell_exec\s*\(/is' => 'PHP shell exec',
            '/<\?php.*passthru\s*\(/is' => 'PHP passthru call',
            '/\$_GET\[.*\]\s*\(/is' => 'Dynamic function execution',
            '/\$_POST\[.*\]\s*\(/is' => 'Dynamic function execution',
            
            // JavaScript malware
            '/document\.write\s*\(\s*unescape\s*\(/is' => 'JavaScript unescape injection',
            '/eval\s*\(\s*atob\s*\(/is' => 'JavaScript base64 eval',
            '/Function\s*\(\s*atob\s*\(/is' => 'JavaScript Function constructor attack',
            '/\.innerHTML\s*=.*<script/is' => 'JavaScript innerHTML script injection',
            
            // Binary signatures (simplified hex patterns)
            '/\x4d\x5a.*\x50\x45\x00\x00/s' => 'Windows PE executable',
            '/\x7f\x45\x4c\x46/s' => 'Linux ELF executable',
            '/\xca\xfe\xba\xbe/s' => 'Java class file',
            
            // Compressed archives with suspicious names
            '/PK.*\.php/is' => 'PHP file in archive',
            '/PK.*\.exe/is' => 'Executable in archive',
            '/PK.*\.scr/is' => 'Screen saver in archive',
            
            // SQL injection in files
            '/union\s+select.*from/is' => 'SQL injection attempt',
            '/drop\s+table/is' => 'SQL drop table',
            '/insert\s+into.*values/is' => 'SQL injection',
            
            // Command injection
            '/\|\s*nc\s+-/is' => 'Netcat command injection',
            '/wget\s+http/is' => 'Wget download attempt',
            '/curl\s+-/is' => 'Curl download attempt',
            '/bash\s+-i/is' => 'Interactive bash shell',
            
            // Crypto mining patterns
            '/stratum\+tcp/is' => 'Cryptocurrency mining pool',
            '/xmrig/is' => 'Monero mining software',
            '/cpuminer/is' => 'CPU mining software',
            
            // Suspicious file headers
            '/\xff\xd8\xff.*<\?php/s' => 'PHP code in JPEG file',
            '/GIF89a.*<\?php/s' => 'PHP code in GIF file',
            '/\x89PNG.*<\?php/s' => 'PHP code in PNG file',
        ];
        
        $this->malwareSignatures = [
            // Common webshell signatures
            'c99shell', 'r57shell', 'wso', 'b374k', 'p0wny',
            'webshell', 'backdoor', 'rootkit', 'trojan',
            
            // PHP malware functions
            'assert', 'create_function', 'file_get_contents',
            'file_put_contents', 'fopen', 'fwrite',
            
            // Encoding techniques
            'base64_decode', 'gzinflate', 'str_rot13', 'strtr',
            'chr', 'ord', 'hex2bin', 'pack', 'unpack',
            
            // Network functions
            'fsockopen', 'socket_create', 'curl_exec',
            'stream_socket_client', 'pfsockopen',
        ];
        
        $this->dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'pht', 'phtml',
            'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'cfc',
            'exe', 'com', 'scr', 'bat', 'cmd', 'pif',
            'vbs', 'vbe', 'js', 'jar', 'app', 'deb', 'rpm',
            'sh', 'bash', 'csh', 'ksh', 'zsh', 'fish',
        ];
    }
    
    /**
     * Comprehensive file scanning with multiple detection methods
     */
    public function scanFile(string $filePath, string $context = 'upload'): array
    {
        $startTime = microtime(true);
        
        Log::info('Starting virus scan', [
            'file_path' => $filePath,
            'context' => $context,
            'file_size' => filesize($filePath) ?: 0
        ]);
        
        try {
            // Initialize scan result
            $scanResult = [
                'status' => ScanStatus::SCANNING,
                'is_clean' => false,
                'threats_found' => [],
                'threat_level' => ThreatLevel::NONE,
                'scan_duration' => 0,
                'scan_engines' => [],
                'quarantined' => false,
                'metadata' => [
                    'file_size' => filesize($filePath) ?: 0,
                    'file_type' => $this->detectFileType($filePath),
                    'scan_timestamp' => now()->toISOString(),
                    'context' => $context
                ]
            ];
            
            // Pre-scan validation
            $validationResult = $this->validateFile($filePath);
            if (!$validationResult['valid']) {
                $scanResult['status'] = ScanStatus::FAILED;
                $scanResult['threats_found'] = $validationResult['errors'];
                $scanResult['threat_level'] = ThreatLevel::HIGH;
                return $scanResult;
            }
            
            // Multiple scanning engines
            $scanEngines = [];
            
            // 1. ClamAV scanning (if available)
            if ($this->isClamAvAvailable()) {
                $clamavResult = $this->scanWithClamAv($filePath);
                $scanEngines['clamav'] = $clamavResult;
                
                if ($clamavResult['threats_found']) {
                    $scanResult['threats_found'] = array_merge(
                        $scanResult['threats_found'],
                        $clamavResult['threats_found']
                    );
                }
            }
            
            // 2. Heuristic pattern scanning
            $heuristicResult = $this->scanWithHeuristics($filePath);
            $scanEngines['heuristic'] = $heuristicResult;
            
            if ($heuristicResult['threats_found']) {
                $scanResult['threats_found'] = array_merge(
                    $scanResult['threats_found'],
                    $heuristicResult['threats_found']
                );
            }
            
            // 3. Content analysis
            $contentResult = $this->analyzeFileContent($filePath);
            $scanEngines['content_analysis'] = $contentResult;
            
            if ($contentResult['threats_found']) {
                $scanResult['threats_found'] = array_merge(
                    $scanResult['threats_found'],
                    $contentResult['threats_found']
                );
            }
            
            // Determine final result
            $scanResult['scan_engines'] = $scanEngines;
            $scanResult['threat_level'] = $this->calculateThreatLevel($scanResult['threats_found']);
            $scanResult['is_clean'] = empty($scanResult['threats_found']);
            $scanResult['status'] = $scanResult['is_clean'] ? ScanStatus::CLEAN : ScanStatus::INFECTED;
            
            // Handle infected files
            if (!$scanResult['is_clean']) {
                $scanResult['quarantined'] = $this->quarantineFile($filePath, $scanResult['threats_found']);
                $this->alertSecurityTeam($filePath, $scanResult);
            }
            
            $scanResult['scan_duration'] = round(microtime(true) - $startTime, 3);
            
            $this->logScanResult($filePath, $scanResult);
            return $scanResult;
            
        } catch (Exception $e) {
            Log::error('Virus scan failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => ScanStatus::ERROR,
                'is_clean' => false,
                'threats_found' => ['Scan engine error: ' . $e->getMessage()],
                'threat_level' => ThreatLevel::UNKNOWN,
                'scan_duration' => round(microtime(true) - $startTime, 3),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate file before scanning
     */
    private function validateFile(string $filePath): array
    {
        $errors = [];
        
        // Check if file exists
        if (!file_exists($filePath)) {
            $errors[] = 'File does not exist';
        }
        
        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            $errors[] = 'File too large for scanning (' . $this->formatFileSize($fileSize) . ')';
        }
        
        if ($fileSize === 0) {
            $errors[] = 'Empty file detected';
        }
        
        // Check if file is readable
        if (!is_readable($filePath)) {
            $errors[] = 'File not readable';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Detect file type using multiple methods
     */
    private function detectFileType(string $filePath): array
    {
        $info = [
            'mime_type' => null,
            'extension' => null,
            'magic_bytes' => null,
            'detected_type' => null
        ];
        
        // Get MIME type
        if (function_exists('mime_content_type')) {
            $info['mime_type'] = mime_content_type($filePath);
        }
        
        // Get file extension
        $info['extension'] = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Read magic bytes
        if (is_readable($filePath)) {
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $info['magic_bytes'] = bin2hex(fread($handle, 16));
                fclose($handle);
            }
        }
        
        // Detect type from magic bytes
        $info['detected_type'] = $this->detectTypeFromMagicBytes($info['magic_bytes']);
        
        return $info;
    }
    
    /**
     * Scan file with ClamAV antivirus
     */
    private function scanWithClamAv(string $filePath): array
    {
        try {
            $result = Process::timeout(self::SCAN_TIMEOUT)
                ->run(['clamscan', '--no-summary', '--infected', $filePath]);
            
            $output = $result->output();
            $threats = [];
            
            if ($result->exitCode() === 1) {
                // Virus found
                preg_match_all('/(.+): (.+) FOUND/', $output, $matches);
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $threats[] = [
                        'engine' => 'clamav',
                        'type' => 'signature_match',
                        'threat_name' => trim($matches[2][$i]),
                        'severity' => 'high',
                        'description' => 'ClamAV signature detection'
                    ];
                }
            }
            
            return [
                'success' => true,
                'threats_found' => $threats,
                'raw_output' => $output,
                'exit_code' => $result->exitCode()
            ];
            
        } catch (Exception $e) {
            Log::warning('ClamAV scan failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            return [
                'success' => false,
                'threats_found' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Heuristic pattern-based scanning
     */
    private function scanWithHeuristics(string $filePath): array
    {
        $threats = [];
        
        try {
            $content = file_get_contents($filePath, false, null, 0, 1048576); // Read first 1MB
            
            // Pattern matching
            foreach ($this->suspiciousPatterns as $pattern => $description) {
                if (preg_match($pattern, $content, $matches)) {
                    $threats[] = [
                        'engine' => 'heuristic',
                        'type' => 'pattern_match',
                        'threat_name' => $description,
                        'severity' => $this->calculatePatternSeverity($pattern),
                        'matched_content' => substr($matches[0], 0, 100),
                        'description' => 'Heuristic pattern detection'
                    ];
                }
            }
            
            // Check for suspicious function combinations
            $suspiciousCount = 0;
            foreach ($this->malwareSignatures as $signature) {
                if (stripos($content, $signature) !== false) {
                    $suspiciousCount++;
                }
            }
            
            if ($suspiciousCount >= 3) {
                $threats[] = [
                    'engine' => 'heuristic',
                    'type' => 'behavioral_analysis',
                    'threat_name' => 'Multiple suspicious functions',
                    'severity' => 'medium',
                    'suspicious_count' => $suspiciousCount,
                    'description' => 'High concentration of potentially malicious functions'
                ];
            }
            
            return [
                'success' => true,
                'threats_found' => $threats
            ];
            
        } catch (Exception $e) {
            Log::warning('Heuristic scan failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            return [
                'success' => false,
                'threats_found' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze file content and structure
     */
    private function analyzeFileContent(string $filePath): array
    {
        $threats = [];
        
        try {
            $fileInfo = $this->detectFileType($filePath);
            $extension = $fileInfo['extension'];
            
            // Check for dangerous extensions
            if (in_array($extension, $this->dangerousExtensions)) {
                $threats[] = [
                    'engine' => 'content_analysis',
                    'type' => 'dangerous_extension',
                    'threat_name' => 'Potentially dangerous file extension',
                    'severity' => 'medium',
                    'extension' => $extension,
                    'description' => 'File extension may allow code execution'
                ];
            }
            
            // Check for file type mismatch
            if ($this->isFileTypeMismatch($fileInfo)) {
                $threats[] = [
                    'engine' => 'content_analysis',
                    'type' => 'file_type_mismatch',
                    'threat_name' => 'File type mismatch detected',
                    'severity' => 'high',
                    'description' => 'File extension does not match actual file content',
                    'details' => $fileInfo
                ];
            }
            
            // Check for embedded executables in images
            if (in_array($fileInfo['mime_type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                $embeddedThreats = $this->checkForEmbeddedContent($filePath);
                $threats = array_merge($threats, $embeddedThreats);
            }
            
            return [
                'success' => true,
                'threats_found' => $threats
            ];
            
        } catch (Exception $e) {
            Log::warning('Content analysis failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            
            return [
                'success' => false,
                'threats_found' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if ClamAV is available
     */
    private function isClamAvAvailable(): bool
    {
        try {
            $result = Process::run(['which', 'clamscan']);
            return $result->exitCode() === 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Calculate overall threat level from detected threats
     */
    private function calculateThreatLevel(array $threats): ThreatLevel
    {
        if (empty($threats)) {
            return ThreatLevel::NONE;
        }
        
        $maxSeverity = 'low';
        
        foreach ($threats as $threat) {
            $severity = $threat['severity'] ?? 'low';
            
            if ($severity === 'critical') {
                return ThreatLevel::CRITICAL;
            } elseif ($severity === 'high' && $maxSeverity !== 'critical') {
                $maxSeverity = 'high';
            } elseif ($severity === 'medium' && !in_array($maxSeverity, ['critical', 'high'])) {
                $maxSeverity = 'medium';
            }
        }
        
        return match ($maxSeverity) {
            'high' => ThreatLevel::HIGH,
            'medium' => ThreatLevel::MEDIUM,
            default => ThreatLevel::LOW
        };
    }
    
    /**
     * Quarantine infected file
     */
    private function quarantineFile(string $filePath, array $threats): bool
    {
        try {
            $quarantineDir = storage_path('app/' . self::QUARANTINE_PATH);
            
            if (!is_dir($quarantineDir)) {
                mkdir($quarantineDir, 0755, true);
            }
            
            $quarantineFilename = date('Y-m-d_H-i-s_') . md5($filePath) . '_quarantined';
            $quarantinePath = $quarantineDir . '/' . $quarantineFilename;
            
            // Move file to quarantine
            $success = rename($filePath, $quarantinePath);
            
            if ($success) {
                // Create quarantine metadata
                $metadataPath = $quarantinePath . '.json';
                $metadata = [
                    'original_path' => $filePath,
                    'quarantine_time' => now()->toISOString(),
                    'threats' => $threats,
                    'file_size' => filesize($quarantinePath),
                    'file_hash' => hash_file('sha256', $quarantinePath)
                ];
                
                file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
                
                Log::warning('File quarantined', [
                    'original_path' => $filePath,
                    'quarantine_path' => $quarantinePath,
                    'threat_count' => count($threats)
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error('Failed to quarantine file', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Alert security team about threats
     */
    private function alertSecurityTeam(string $filePath, array $scanResult): void
    {
        $threatLevel = $scanResult['threat_level'];
        $threats = $scanResult['threats_found'];
        
        Log::warning('Virus/Malware detected', [
            'file_path' => $filePath,
            'threat_level' => $threatLevel->value,
            'threats' => $threats,
            'scan_result' => $scanResult
        ]);
        
        // For critical threats, send immediate alerts
        if ($threatLevel === ThreatLevel::CRITICAL) {
            Log::critical('CRITICAL SECURITY THREAT DETECTED', [
                'file_path' => $filePath,
                'threats' => $threats,
                'immediate_action_required' => true
            ]);
            
            // Here you could integrate with:
            // - Slack notifications
            // - Email alerts
            // - SMS notifications
            // - Security incident management systems
        }
    }
    
    /**
     * Log comprehensive scan results
     */
    private function logScanResult(string $filePath, array $scanResult): void
    {
        Log::info('Virus scan completed', [
            'file_path' => $filePath,
            'status' => $scanResult['status']->value,
            'is_clean' => $scanResult['is_clean'],
            'threat_count' => count($scanResult['threats_found']),
            'threat_level' => $scanResult['threat_level']->value,
            'scan_duration' => $scanResult['scan_duration'],
            'engines_used' => array_keys($scanResult['scan_engines'])
        ]);
    }
    
    /**
     * Helper methods
     */
    private function detectTypeFromMagicBytes(?string $magicBytes): ?string
    {
        if (!$magicBytes) return null;
        
        $signatures = [
            'ffd8ff' => 'jpeg',
            '89504e47' => 'png',
            '47494638' => 'gif',
            '504b0304' => 'zip',
            '52617221' => 'rar',
            '25504446' => 'pdf',
            '4d5a' => 'executable',
            '7f454c46' => 'elf',
        ];
        
        foreach ($signatures as $signature => $type) {
            if (str_starts_with($magicBytes, $signature)) {
                return $type;
            }
        }
        
        return null;
    }
    
    private function calculatePatternSeverity(string $pattern): string
    {
        if (str_contains($pattern, 'eval|system|exec')) {
            return 'critical';
        } elseif (str_contains($pattern, 'base64|unescape')) {
            return 'high';
        } else {
            return 'medium';
        }
    }
    
    private function isFileTypeMismatch(array $fileInfo): bool
    {
        $extension = $fileInfo['extension'];
        $detectedType = $fileInfo['detected_type'];
        
        if (!$extension || !$detectedType) {
            return false;
        }
        
        $extensionTypeMap = [
            'jpg' => 'jpeg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'zip' => 'zip',
            'pdf' => 'pdf',
        ];
        
        $expectedType = $extensionTypeMap[$extension] ?? null;
        
        return $expectedType && $expectedType !== $detectedType;
    }
    
    private function checkForEmbeddedContent(string $filePath): array
    {
        $threats = [];
        
        try {
            $content = file_get_contents($filePath);
            
            // Look for embedded PHP code in images
            if (preg_match('/<\?php|<script|javascript:/i', $content)) {
                $threats[] = [
                    'engine' => 'content_analysis',
                    'type' => 'embedded_code',
                    'threat_name' => 'Code embedded in image file',
                    'severity' => 'high',
                    'description' => 'Executable code found embedded in image file'
                ];
            }
            
        } catch (Exception $e) {
            // Ignore errors in embedded content checking
        }
        
        return $threats;
    }
    
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