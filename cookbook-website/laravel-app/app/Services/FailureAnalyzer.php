<?php

namespace App\Services;

use App\Enums\FailureCategory;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comprehensive failure analysis service for job processing errors
 * 
 * This service classifies exceptions, generates user-friendly messages,
 * and provides context for recovery strategies and support escalation.
 */
class FailureAnalyzer
{
    /**
     * Exception patterns for automatic categorization
     */
    private const EXCEPTION_PATTERNS = [
        // Network and connectivity errors
        'network_error' => [
            'curl error',
            'connection timed out',
            'name or service not known',
            'network is unreachable',
            'connection refused',
            'ssl handshake failed',
            'could not resolve host'
        ],
        
        // File system errors
        'file_not_found' => [
            'no such file or directory',
            'file not found',
            'the system cannot find the file specified',
            'does not exist'
        ],
        
        'file_corrupted' => [
            'file is corrupted',
            'invalid file format',
            'unexpected end of file',
            'malformed',
            'parse error'
        ],
        
        'file_permission_error' => [
            'permission denied',
            'access is denied',
            'operation not permitted',
            'insufficient privileges'
        ],
        
        // Memory and resource errors
        'memory_limit' => [
            'allowed memory size',
            'out of memory',
            'maximum execution time',
            'memory limit exceeded'
        ],
        
        'disk_space' => [
            'no space left on device',
            'disk full',
            'insufficient disk space',
            'write failed: disk full'
        ],
        
        // Database errors
        'database_error' => [
            'sqlstate',
            'database connection failed',
            'mysql server has gone away',
            'connection lost',
            'deadlock found',
            'table doesn\'t exist',
            'duplicate entry'
        ],
        
        // Processing specific errors
        'parsing_error' => [
            'xml parse error',
            'json decode error',
            'malformed xml',
            'parsing failed',
            'invalid xml'
        ],
        
        'timeout' => [
            'operation timed out',
            'execution time limit',
            'timeout exceeded',
            'request timeout'
        ],
        
        // System errors
        'system_error' => [
            'internal server error',
            'system error',
            'kernel panic',
            'segmentation fault',
            'bus error'
        ],
        
        // Service availability
        'service_unavailable' => [
            'service unavailable',
            'temporarily unavailable',
            'server overloaded',
            'too many requests',
            'rate limit exceeded'
        ]
    ];
    
    /**
     * File size thresholds for categorization (in bytes)
     */
    private const FILE_SIZE_THRESHOLDS = [
        'large' => 10 * 1024 * 1024,    // 10MB
        'very_large' => 50 * 1024 * 1024, // 50MB
        'huge' => 100 * 1024 * 1024,     // 100MB
    ];
    
    /**
     * Analyze an exception and return comprehensive failure information
     */
    public function analyzeException(Throwable $exception): array
    {
        // Basic exception information
        $exceptionInfo = $this->extractExceptionInfo($exception);
        
        // Categorize the failure
        $category = $this->categorizeException($exception);
        
        // Generate user-friendly message
        $userMessage = $this->generateUserMessage($category, $exceptionInfo);
        
        // Extract contextual information
        $context = $this->extractContextualInfo($exception, $exceptionInfo);
        
        // Determine severity and priority
        $severity = $this->determineSeverity($category, $exceptionInfo);
        
        // Generate suggested actions
        $suggestedActions = $this->generateSuggestedActions($category, $context);
        
        // Check if escalation is needed
        $escalationNeeded = $this->shouldEscalate($category, $severity, $context);
        
        return [
            'category' => $category,
            'severity' => $severity,
            'message' => $exceptionInfo['message'],
            'user_message' => $userMessage,
            'technical_details' => $exceptionInfo,
            'context' => $context,
            'suggested_actions' => $suggestedActions,
            'escalation_needed' => $escalationNeeded,
            'recovery_strategy' => $this->determineRecoveryStrategy($category, $context),
            'monitoring_tags' => $this->generateMonitoringTags($category, $exceptionInfo)
        ];
    }
    
    /**
     * Extract basic exception information
     */
    private function extractExceptionInfo(Throwable $exception): array
    {
        return [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'previous' => $exception->getPrevious() ? [
                'type' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
                'code' => $exception->getPrevious()->getCode(),
            ] : null,
        ];
    }
    
    /**
     * Categorize exception based on patterns and context
     */
    private function categorizeException(Throwable $exception): FailureCategory
    {
        $message = strtolower($exception->getMessage());
        $exceptionType = get_class($exception);
        
        // Check for specific exception types first
        $category = $this->categorizeByExceptionType($exceptionType);
        if ($category) {
            return $category;
        }
        
        // Check message patterns
        foreach (self::EXCEPTION_PATTERNS as $categoryKey => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    return $this->mapCategoryKeyToEnum($categoryKey);
                }
            }
        }
        
        // Check file-related errors
        if (str_contains($message, 'file') || str_contains($message, 'adg')) {
            if (str_contains($message, 'not found')) {
                return FailureCategory::FILE_NOT_FOUND;
            }
            if (str_contains($message, 'corrupted') || str_contains($message, 'invalid')) {
                return FailureCategory::FILE_CORRUPTED;
            }
            if (str_contains($message, 'too large') || str_contains($message, 'size')) {
                return FailureCategory::FILE_TOO_LARGE;
            }
            if (str_contains($message, 'format')) {
                return FailureCategory::INVALID_FILE_FORMAT;
            }
        }
        
        // Check for analysis-specific errors
        if (str_contains($message, 'analysis') || str_contains($message, 'parse') || str_contains($message, 'xml')) {
            return FailureCategory::ANALYSIS_ERROR;
        }
        
        // Default to unknown error
        return FailureCategory::UNKNOWN_ERROR;
    }
    
    /**
     * Categorize by specific exception types
     */
    private function categorizeByExceptionType(string $exceptionType): ?FailureCategory
    {
        return match($exceptionType) {
            'PDOException', 'Illuminate\Database\QueryException' => FailureCategory::DATABASE_ERROR,
            'GuzzleHttp\Exception\ConnectException', 'GuzzleHttp\Exception\RequestException' => FailureCategory::NETWORK_ERROR,
            'Symfony\Component\Process\Exception\ProcessTimedOutException' => FailureCategory::TIMEOUT,
            'ErrorException' => $this->categorizeErrorException($exceptionType),
            'InvalidArgumentException' => FailureCategory::USER_INPUT_ERROR,
            'UnexpectedValueException' => FailureCategory::VALIDATION_ERROR,
            default => null,
        };
    }
    
    /**
     * Handle ErrorException categorization based on error level
     */
    private function categorizeErrorException(string $exceptionType): FailureCategory
    {
        // This would need access to the actual ErrorException to check severity
        // For now, return a default category
        return FailureCategory::SYSTEM_ERROR;
    }
    
    /**
     * Map category keys to enum values
     */
    private function mapCategoryKeyToEnum(string $categoryKey): FailureCategory
    {
        return match($categoryKey) {
            'network_error' => FailureCategory::NETWORK_ERROR,
            'file_not_found' => FailureCategory::FILE_NOT_FOUND,
            'file_corrupted' => FailureCategory::FILE_CORRUPTED,
            'file_permission_error' => FailureCategory::FILE_PERMISSION_ERROR,
            'memory_limit' => FailureCategory::MEMORY_LIMIT,
            'disk_space' => FailureCategory::DISK_SPACE,
            'database_error' => FailureCategory::DATABASE_ERROR,
            'parsing_error' => FailureCategory::PARSING_ERROR,
            'timeout' => FailureCategory::TIMEOUT,
            'system_error' => FailureCategory::SYSTEM_ERROR,
            'service_unavailable' => FailureCategory::SERVICE_UNAVAILABLE,
            default => FailureCategory::UNKNOWN_ERROR,
        };
    }
    
    /**
     * Generate user-friendly error message
     */
    private function generateUserMessage(FailureCategory $category, array $exceptionInfo): string
    {
        $baseMessage = $category->userMessage();
        
        // Enhance message based on specific error details
        $enhancedMessage = $this->enhanceUserMessage($baseMessage, $category, $exceptionInfo);
        
        return $enhancedMessage;
    }
    
    /**
     * Enhance user message with specific context
     */
    private function enhanceUserMessage(string $baseMessage, FailureCategory $category, array $exceptionInfo): string
    {
        $message = $baseMessage;
        
        // Add specific hints based on error message content
        if ($category === FailureCategory::FILE_CORRUPTED) {
            if (str_contains(strtolower($exceptionInfo['message']), 'xml')) {
                $message .= ' This might be due to an incomplete file upload or an older Ableton Live version.';
            }
        }
        
        if ($category === FailureCategory::MEMORY_LIMIT) {
            if (str_contains(strtolower($exceptionInfo['message']), 'allowed memory')) {
                $message .= ' Your rack might be particularly complex. We\'ll try with more memory allocated.';
            }
        }
        
        if ($category === FailureCategory::TIMEOUT) {
            $message .= ' Large or complex racks may take longer to process. We\'ll retry with extended time limits.';
        }
        
        return $message;
    }
    
    /**
     * Extract contextual information for better error handling
     */
    private function extractContextualInfo(Throwable $exception, array $exceptionInfo): array
    {
        $context = [
            'timestamp' => now()->toISOString(),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time'),
        ];
        
        // Add stack trace analysis
        $context['stack_analysis'] = $this->analyzeStackTrace($exceptionInfo['trace']);
        
        // Add error location context
        if ($exceptionInfo['file'] && $exceptionInfo['line']) {
            $context['error_location'] = [
                'file' => basename($exceptionInfo['file']),
                'line' => $exceptionInfo['line'],
                'in_vendor' => str_contains($exceptionInfo['file'], '/vendor/'),
                'in_app' => str_contains($exceptionInfo['file'], '/app/'),
            ];
        }
        
        // Add system resource context
        $context['system_resources'] = $this->gatherSystemResourceInfo();
        
        return $context;
    }
    
    /**
     * Analyze stack trace for patterns and insights
     */
    private function analyzeStackTrace(string $stackTrace): array
    {
        $analysis = [
            'depth' => substr_count($stackTrace, "\n"),
            'has_database_calls' => str_contains($stackTrace, 'Database') || str_contains($stackTrace, 'PDO'),
            'has_file_operations' => str_contains($stackTrace, 'file_') || str_contains($stackTrace, 'Storage'),
            'has_network_calls' => str_contains($stackTrace, 'curl_') || str_contains($stackTrace, 'Guzzle'),
            'has_xml_parsing' => str_contains($stackTrace, 'xml') || str_contains($stackTrace, 'SimpleXML'),
            'error_in_vendor' => str_contains($stackTrace, '/vendor/'),
            'error_in_app_code' => str_contains($stackTrace, '/app/'),
        ];
        
        // Identify the probable root cause location
        $lines = explode("\n", $stackTrace);
        foreach ($lines as $line) {
            if (str_contains($line, '/app/') && !str_contains($line, '/vendor/')) {
                $analysis['likely_root_cause'] = trim($line);
                break;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Gather system resource information
     */
    private function gatherSystemResourceInfo(): array
    {
        return [
            'load_average' => $this->getSystemLoadAverage(),
            'disk_free_space' => disk_free_space(storage_path()),
            'disk_total_space' => disk_total_space(storage_path()),
        ];
    }
    
    /**
     * Get system load average (Linux/Unix only)
     */
    private function getSystemLoadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return null;
    }
    
    /**
     * Determine error severity level
     */
    private function determineSeverity(FailureCategory $category, array $exceptionInfo): string
    {
        // Use category's default severity as baseline
        $baseSeverity = $category->severity();
        
        // Adjust based on specific context
        if ($this->isRecurringError($exceptionInfo)) {
            $baseSeverity = $this->escalateSeverity($baseSeverity);
        }
        
        if ($this->affectsSystemStability($category)) {
            $baseSeverity = $this->escalateSeverity($baseSeverity);
        }
        
        return $baseSeverity;
    }
    
    /**
     * Generate suggested actions for recovery
     */
    private function generateSuggestedActions(FailureCategory $category, array $context): array
    {
        $actions = $category->suggestedActions();
        
        // Add context-specific suggestions
        if ($category === FailureCategory::MEMORY_LIMIT) {
            if (isset($context['system_resources']['disk_free_space'])) {
                $freeSpace = $context['system_resources']['disk_free_space'];
                if ($freeSpace < 1024 * 1024 * 1024) { // Less than 1GB
                    $actions[] = 'System is low on disk space - this may affect processing';
                }
            }
        }
        
        if ($category === FailureCategory::PARSING_ERROR) {
            if (isset($context['stack_analysis']['has_xml_parsing']) && $context['stack_analysis']['has_xml_parsing']) {
                $actions[] = 'Check if the Ableton file was exported correctly from Live';
                $actions[] = 'Ensure you\'re using a supported version of Ableton Live';
            }
        }
        
        return array_unique($actions);
    }
    
    /**
     * Determine if error should be escalated
     */
    private function shouldEscalate(FailureCategory $category, string $severity, array $context): bool
    {
        // Always escalate critical/high severity system errors
        if ($category->shouldEscalate() && in_array($severity, ['critical', 'high'])) {
            return true;
        }
        
        // Escalate if error is in application code (not vendor)
        if (isset($context['error_location']['in_app']) && $context['error_location']['in_app']) {
            return true;
        }
        
        // Escalate if this is a recurring issue
        if ($this->isRecurringError($context)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Determine recovery strategy
     */
    private function determineRecoveryStrategy(FailureCategory $category, array $context): array
    {
        $strategy = [
            'type' => 'automatic', // automatic, manual, escalated
            'actions' => [],
            'estimated_resolution_time' => null,
        ];
        
        if ($category->isRetryable()) {
            $strategy['type'] = 'automatic';
            $strategy['actions'][] = 'automatic_retry';
            $strategy['estimated_resolution_time'] = $this->estimateResolutionTime($category, $context);
        } elseif ($category->requiresUserAction()) {
            $strategy['type'] = 'manual';
            $strategy['actions'][] = 'user_intervention_required';
        } else {
            $strategy['type'] = 'escalated';
            $strategy['actions'][] = 'support_escalation';
        }
        
        return $strategy;
    }
    
    /**
     * Generate monitoring tags for metrics and alerting
     */
    private function generateMonitoringTags(FailureCategory $category, array $exceptionInfo): array
    {
        return [
            'failure_category' => $category->value,
            'exception_type' => $exceptionInfo['type'],
            'severity' => $category->severity(),
            'retryable' => $category->isRetryable() ? 'yes' : 'no',
            'user_action_required' => $category->requiresUserAction() ? 'yes' : 'no',
        ];
    }
    
    /**
     * Check if this is a recurring error pattern
     */
    private function isRecurringError(array $exceptionInfo): bool
    {
        // This would check against historical error patterns
        // For now, return false - implement based on error tracking needs
        return false;
    }
    
    /**
     * Check if error affects system stability
     */
    private function affectsSystemStability(FailureCategory $category): bool
    {
        return in_array($category, [
            FailureCategory::SYSTEM_ERROR,
            FailureCategory::MEMORY_LIMIT,
            FailureCategory::DISK_SPACE,
            FailureCategory::DATABASE_ERROR,
        ]);
    }
    
    /**
     * Escalate severity level
     */
    private function escalateSeverity(string $currentSeverity): string
    {
        return match($currentSeverity) {
            'info' => 'low',
            'low' => 'medium',
            'medium' => 'high',
            'high' => 'critical',
            default => $currentSeverity,
        };
    }
    
    /**
     * Estimate resolution time based on category and context
     */
    private function estimateResolutionTime(FailureCategory $category, array $context): ?string
    {
        return match($category) {
            FailureCategory::NETWORK_ERROR => '1-5 minutes',
            FailureCategory::TIMEOUT => '2-10 minutes',
            FailureCategory::MEMORY_LIMIT => '5-15 minutes',
            FailureCategory::DISK_SPACE => '10-60 minutes',
            FailureCategory::SERVICE_UNAVAILABLE => '5-30 minutes',
            FailureCategory::TEMPORARY_FILE_ACCESS => '1-5 minutes',
            FailureCategory::ANALYSIS_ERROR => '1-10 minutes',
            FailureCategory::DATABASE_ERROR => '1-5 minutes',
            default => null,
        };
    }
    
    /**
     * Generate a detailed error report for support teams
     */
    public function generateSupportReport(Throwable $exception): array
    {
        $analysis = $this->analyzeException($exception);
        
        return [
            'report_id' => Str::uuid(),
            'timestamp' => now()->toISOString(),
            'analysis' => $analysis,
            'environment' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            ],
            'request_context' => [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'recommendations' => $this->generateSupportRecommendations($analysis),
        ];
    }
    
    /**
     * Generate recommendations for support teams
     */
    private function generateSupportRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        $category = $analysis['category'];
        $severity = $analysis['severity'];
        
        if ($severity === 'critical') {
            $recommendations[] = 'URGENT: Requires immediate attention';
        }
        
        if ($analysis['escalation_needed']) {
            $recommendations[] = 'Escalate to development team for code review';
        }
        
        if ($category->shouldEscalate()) {
            $recommendations[] = 'Check system resources and infrastructure';
        }
        
        if (isset($analysis['context']['stack_analysis']['likely_root_cause'])) {
            $recommendations[] = 'Focus investigation on: ' . $analysis['context']['stack_analysis']['likely_root_cause'];
        }
        
        return $recommendations;
    }
}