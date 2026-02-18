<?php

namespace App\Enums;

/**
 * Categorizes different types of failures that can occur during rack processing
 * 
 * This enum helps classify failures to determine appropriate recovery strategies,
 * user messages, and escalation procedures.
 */
enum FailureCategory: string
{
    // Temporary/recoverable failures
    case NETWORK_ERROR = 'network_error';
    case TIMEOUT = 'timeout';
    case MEMORY_LIMIT = 'memory_limit';
    case DISK_SPACE = 'disk_space';
    case SERVICE_UNAVAILABLE = 'service_unavailable';
    case TEMPORARY_FILE_ACCESS = 'temporary_file_access';
    
    // File-related failures
    case FILE_NOT_FOUND = 'file_not_found';
    case FILE_CORRUPTED = 'file_corrupted';
    case FILE_TOO_LARGE = 'file_too_large';
    case INVALID_FILE_FORMAT = 'invalid_file_format';
    case FILE_PERMISSION_ERROR = 'file_permission_error';
    
    // Processing failures
    case PARSING_ERROR = 'parsing_error';
    case ANALYSIS_ERROR = 'analysis_error';
    case DATABASE_ERROR = 'database_error';
    case VALIDATION_ERROR = 'validation_error';
    
    // System failures
    case SYSTEM_ERROR = 'system_error';
    case CONFIGURATION_ERROR = 'configuration_error';
    case DEPENDENCY_ERROR = 'dependency_error';
    
    // User errors
    case USER_INPUT_ERROR = 'user_input_error';
    case AUTHORIZATION_ERROR = 'authorization_error';
    case QUOTA_EXCEEDED = 'quota_exceeded';
    
    // Unknown/unclassified
    case UNKNOWN_ERROR = 'unknown_error';
    
    /**
     * Get human-readable category name
     */
    public function label(): string
    {
        return match($this) {
            self::NETWORK_ERROR => 'Network Error',
            self::TIMEOUT => 'Processing Timeout',
            self::MEMORY_LIMIT => 'Memory Limit Exceeded',
            self::DISK_SPACE => 'Insufficient Disk Space',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::TEMPORARY_FILE_ACCESS => 'Temporary File Access Issue',
            self::FILE_NOT_FOUND => 'File Not Found',
            self::FILE_CORRUPTED => 'File Corrupted',
            self::FILE_TOO_LARGE => 'File Too Large',
            self::INVALID_FILE_FORMAT => 'Invalid File Format',
            self::FILE_PERMISSION_ERROR => 'File Permission Error',
            self::PARSING_ERROR => 'File Parsing Error',
            self::ANALYSIS_ERROR => 'Analysis Error',
            self::DATABASE_ERROR => 'Database Error',
            self::VALIDATION_ERROR => 'Validation Error',
            self::SYSTEM_ERROR => 'System Error',
            self::CONFIGURATION_ERROR => 'Configuration Error',
            self::DEPENDENCY_ERROR => 'Dependency Error',
            self::USER_INPUT_ERROR => 'User Input Error',
            self::AUTHORIZATION_ERROR => 'Authorization Error',
            self::QUOTA_EXCEEDED => 'Quota Exceeded',
            self::UNKNOWN_ERROR => 'Unknown Error',
        };
    }
    
    /**
     * Get user-friendly error message
     */
    public function userMessage(): string
    {
        return match($this) {
            self::NETWORK_ERROR => 'A network error occurred while processing your rack. We\'ll retry automatically.',
            self::TIMEOUT => 'Processing took longer than expected. We\'ll try again with optimized settings.',
            self::MEMORY_LIMIT => 'Your rack file requires more memory to process. We\'ll retry with increased resources.',
            self::DISK_SPACE => 'Our servers are temporarily low on space. We\'ll retry once space is available.',
            self::SERVICE_UNAVAILABLE => 'Our processing service is temporarily unavailable. We\'ll retry automatically.',
            self::TEMPORARY_FILE_ACCESS => 'Temporary file access issue. This usually resolves automatically on retry.',
            self::FILE_NOT_FOUND => 'Your rack file could not be found. Please try uploading again.',
            self::FILE_CORRUPTED => 'Your rack file appears to be corrupted. Please check the file and upload again.',
            self::FILE_TOO_LARGE => 'Your rack file is too large to process. Please try reducing the file size.',
            self::INVALID_FILE_FORMAT => 'The uploaded file is not a valid Ableton rack file (.adg). Please check your file.',
            self::FILE_PERMISSION_ERROR => 'File permission error. This is usually temporary and will be retried.',
            self::PARSING_ERROR => 'We couldn\'t parse your rack file. It might be from an unsupported Ableton version.',
            self::ANALYSIS_ERROR => 'An error occurred while analyzing your rack structure. We\'ll retry automatically.',
            self::DATABASE_ERROR => 'A database error occurred. This is usually temporary and will be retried.',
            self::VALIDATION_ERROR => 'Validation failed during processing. Please check your file and try again.',
            self::SYSTEM_ERROR => 'A system error occurred. Our team has been notified and will investigate.',
            self::CONFIGURATION_ERROR => 'A configuration error occurred. Our team has been notified.',
            self::DEPENDENCY_ERROR => 'A dependency error occurred. This is usually temporary and will be retried.',
            self::USER_INPUT_ERROR => 'There was an issue with the provided information. Please check and try again.',
            self::AUTHORIZATION_ERROR => 'You don\'t have permission to perform this action.',
            self::QUOTA_EXCEEDED => 'You\'ve exceeded your upload quota. Please try again later or upgrade your plan.',
            self::UNKNOWN_ERROR => 'An unexpected error occurred. We\'ll try again automatically.',
        };
    }
    
    /**
     * Get detailed technical description for logging
     */
    public function technicalDescription(): string
    {
        return match($this) {
            self::NETWORK_ERROR => 'Network connectivity issues, DNS resolution failures, or external service timeouts',
            self::TIMEOUT => 'Processing exceeded maximum allowed time limit',
            self::MEMORY_LIMIT => 'PHP memory limit exceeded or insufficient system memory',
            self::DISK_SPACE => 'Insufficient disk space for temporary file operations',
            self::SERVICE_UNAVAILABLE => 'External services or dependencies unavailable',
            self::TEMPORARY_FILE_ACCESS => 'Temporary file system access issues, permissions, or locks',
            self::FILE_NOT_FOUND => 'Source file missing from storage, path resolution failed',
            self::FILE_CORRUPTED => 'File integrity check failed, incomplete upload, or data corruption',
            self::FILE_TOO_LARGE => 'File size exceeds processing limits or memory constraints',
            self::INVALID_FILE_FORMAT => 'File format validation failed, wrong MIME type, or unsupported format',
            self::FILE_PERMISSION_ERROR => 'File system permission denied, access control issues',
            self::PARSING_ERROR => 'XML parsing failed, malformed data structure, or encoding issues',
            self::ANALYSIS_ERROR => 'Rack analysis logic failed, unexpected data structure',
            self::DATABASE_ERROR => 'Database connection, query execution, or constraint violations',
            self::VALIDATION_ERROR => 'Data validation rules failed during processing',
            self::SYSTEM_ERROR => 'Unhandled system-level errors, OS issues, or resource constraints',
            self::CONFIGURATION_ERROR => 'Missing or invalid configuration values',
            self::DEPENDENCY_ERROR => 'Missing dependencies, version conflicts, or service failures',
            self::USER_INPUT_ERROR => 'Invalid user input, missing required fields, or data format issues',
            self::AUTHORIZATION_ERROR => 'Authentication or authorization checks failed',
            self::QUOTA_EXCEEDED => 'User or system quotas exceeded',
            self::UNKNOWN_ERROR => 'Unclassified or unexpected errors',
        };
    }
    
    /**
     * Determine if this failure type is retryable
     */
    public function isRetryable(): bool
    {
        return in_array($this, [
            self::NETWORK_ERROR,
            self::TIMEOUT,
            self::MEMORY_LIMIT,
            self::DISK_SPACE,
            self::SERVICE_UNAVAILABLE,
            self::TEMPORARY_FILE_ACCESS,
            self::ANALYSIS_ERROR,
            self::DATABASE_ERROR,
            self::SYSTEM_ERROR,
            self::DEPENDENCY_ERROR,
            self::UNKNOWN_ERROR,
        ]);
    }
    
    /**
     * Determine if this failure requires immediate user action
     */
    public function requiresUserAction(): bool
    {
        return in_array($this, [
            self::FILE_NOT_FOUND,
            self::FILE_CORRUPTED,
            self::FILE_TOO_LARGE,
            self::INVALID_FILE_FORMAT,
            self::USER_INPUT_ERROR,
            self::AUTHORIZATION_ERROR,
            self::QUOTA_EXCEEDED,
        ]);
    }
    
    /**
     * Determine if this failure should be escalated to support
     */
    public function shouldEscalate(): bool
    {
        return in_array($this, [
            self::SYSTEM_ERROR,
            self::CONFIGURATION_ERROR,
            self::DEPENDENCY_ERROR,
        ]);
    }
    
    /**
     * Get maximum retry attempts for this failure type
     */
    public function maxRetries(): int
    {
        return match($this) {
            self::NETWORK_ERROR => 5,
            self::TIMEOUT => 3,
            self::MEMORY_LIMIT => 2,
            self::DISK_SPACE => 10,
            self::SERVICE_UNAVAILABLE => 8,
            self::TEMPORARY_FILE_ACCESS => 5,
            self::ANALYSIS_ERROR => 3,
            self::DATABASE_ERROR => 5,
            self::SYSTEM_ERROR => 2,
            self::DEPENDENCY_ERROR => 3,
            self::UNKNOWN_ERROR => 3,
            default => 0, // Non-retryable failures
        };
    }
    
    /**
     * Get base retry delay in seconds
     */
    public function baseRetryDelay(): int
    {
        return match($this) {
            self::NETWORK_ERROR => 30,
            self::TIMEOUT => 60,
            self::MEMORY_LIMIT => 300, // 5 minutes
            self::DISK_SPACE => 600,   // 10 minutes
            self::SERVICE_UNAVAILABLE => 120,
            self::TEMPORARY_FILE_ACCESS => 60,
            self::ANALYSIS_ERROR => 30,
            self::DATABASE_ERROR => 30,
            self::SYSTEM_ERROR => 300,
            self::DEPENDENCY_ERROR => 60,
            self::UNKNOWN_ERROR => 60,
            default => 0,
        };
    }
    
    /**
     * Get severity level for monitoring and alerting
     */
    public function severity(): string
    {
        return match($this) {
            self::SYSTEM_ERROR, self::CONFIGURATION_ERROR => 'critical',
            self::DATABASE_ERROR, self::DEPENDENCY_ERROR => 'high',
            self::PARSING_ERROR, self::ANALYSIS_ERROR, self::MEMORY_LIMIT => 'medium',
            self::NETWORK_ERROR, self::TIMEOUT, self::SERVICE_UNAVAILABLE => 'low',
            default => 'info',
        };
    }
    
    /**
     * Get suggested actions for recovery
     */
    public function suggestedActions(): array
    {
        return match($this) {
            self::FILE_NOT_FOUND => ['Re-upload the file', 'Check file exists before submission'],
            self::FILE_CORRUPTED => ['Re-export from Ableton Live', 'Check file integrity', 'Try a different file'],
            self::FILE_TOO_LARGE => ['Reduce rack complexity', 'Remove unnecessary devices', 'Split into multiple racks'],
            self::INVALID_FILE_FORMAT => ['Ensure file is exported as .adg', 'Check Ableton Live version compatibility'],
            self::USER_INPUT_ERROR => ['Review form inputs', 'Check required fields', 'Verify data format'],
            self::AUTHORIZATION_ERROR => ['Log in again', 'Check account permissions', 'Contact support'],
            self::QUOTA_EXCEEDED => ['Wait for quota reset', 'Upgrade account plan', 'Delete old uploads'],
            default => ['Wait for automatic retry', 'Contact support if issue persists'],
        };
    }
}