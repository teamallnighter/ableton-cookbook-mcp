<?php

namespace App\Enums;

enum ErrorCode: string
{
    // Authentication Errors (1xxx)
    case UNAUTHORIZED = '1001';
    case INVALID_TOKEN = '1002';
    case TOKEN_EXPIRED = '1003';
    case INSUFFICIENT_PERMISSIONS = '1004';
    
    // Validation Errors (2xxx)
    case VALIDATION_FAILED = '2001';
    case MISSING_REQUIRED_FIELD = '2002';
    case INVALID_FORMAT = '2003';
    case FILE_TOO_LARGE = '2004';
    case INVALID_FILE_TYPE = '2005';
    case DUPLICATE_ENTRY = '2006';
    
    // Resource Errors (3xxx)
    case RESOURCE_NOT_FOUND = '3001';
    case RESOURCE_DELETED = '3002';
    case RESOURCE_LOCKED = '3003';
    case RESOURCE_UNAVAILABLE = '3004';
    
    // Processing Errors (4xxx)
    case PROCESSING_FAILED = '4001';
    case ANALYSIS_FAILED = '4002';
    case CONVERSION_FAILED = '4003';
    case UPLOAD_FAILED = '4004';
    case DOWNLOAD_FAILED = '4005';
    
    // Concurrency Errors (5xxx)
    case CONFLICT_DETECTED = '5001';
    case VERSION_MISMATCH = '5002';
    case CONCURRENT_MODIFICATION = '5003';
    case AUTOSAVE_CONFLICT = '5004';
    case LOCK_TIMEOUT = '5005';
    
    // System Errors (6xxx)
    case DATABASE_ERROR = '6001';
    case FILESYSTEM_ERROR = '6002';
    case NETWORK_ERROR = '6003';
    case SERVICE_UNAVAILABLE = '6004';
    case RATE_LIMIT_EXCEEDED = '6005';
    case TEMPORARY_FAILURE = '6006';
    
    // Job Processing Errors (7xxx)
    case JOB_FAILED = '7001';
    case JOB_TIMEOUT = '7002';
    case JOB_QUEUE_FULL = '7003';
    case JOB_RETRY_LIMIT_EXCEEDED = '7004';
    case JOB_PERMANENTLY_FAILED = '7005';
    
    // Client Errors (8xxx)
    case INVALID_REQUEST = '8001';
    case MALFORMED_DATA = '8002';
    case UNSUPPORTED_OPERATION = '8003';
    case CLIENT_TIMEOUT = '8004';
    
    public function message(): string
    {
        return match($this) {
            // Authentication
            self::UNAUTHORIZED => 'You are not authorized to perform this action.',
            self::INVALID_TOKEN => 'The provided authentication token is invalid.',
            self::TOKEN_EXPIRED => 'Your session has expired. Please log in again.',
            self::INSUFFICIENT_PERMISSIONS => 'You do not have sufficient permissions for this action.',
            
            // Validation
            self::VALIDATION_FAILED => 'The provided data failed validation.',
            self::MISSING_REQUIRED_FIELD => 'A required field is missing.',
            self::INVALID_FORMAT => 'The data format is invalid.',
            self::FILE_TOO_LARGE => 'The uploaded file is too large.',
            self::INVALID_FILE_TYPE => 'The file type is not supported.',
            self::DUPLICATE_ENTRY => 'This item already exists.',
            
            // Resources
            self::RESOURCE_NOT_FOUND => 'The requested resource was not found.',
            self::RESOURCE_DELETED => 'This resource has been deleted.',
            self::RESOURCE_LOCKED => 'This resource is currently locked by another user.',
            self::RESOURCE_UNAVAILABLE => 'This resource is temporarily unavailable.',
            
            // Processing
            self::PROCESSING_FAILED => 'Processing failed. Please try again.',
            self::ANALYSIS_FAILED => 'File analysis failed. The file may be corrupted.',
            self::CONVERSION_FAILED => 'File conversion failed.',
            self::UPLOAD_FAILED => 'File upload failed. Please check your connection and try again.',
            self::DOWNLOAD_FAILED => 'Download failed. Please try again later.',
            
            // Concurrency
            self::CONFLICT_DETECTED => 'A conflict was detected with another user\'s changes.',
            self::VERSION_MISMATCH => 'The version you are editing is outdated.',
            self::CONCURRENT_MODIFICATION => 'Another user is currently editing this item.',
            self::AUTOSAVE_CONFLICT => 'Auto-save detected conflicting changes.',
            self::LOCK_TIMEOUT => 'Could not acquire lock within the timeout period.',
            
            // System
            self::DATABASE_ERROR => 'A database error occurred. Please try again.',
            self::FILESYSTEM_ERROR => 'A file system error occurred.',
            self::NETWORK_ERROR => 'Network connection failed. Please check your internet connection.',
            self::SERVICE_UNAVAILABLE => 'The service is temporarily unavailable.',
            self::RATE_LIMIT_EXCEEDED => 'Too many requests. Please wait before trying again.',
            self::TEMPORARY_FAILURE => 'A temporary failure occurred. Please try again.',
            
            // Jobs
            self::JOB_FAILED => 'Background processing failed.',
            self::JOB_TIMEOUT => 'Processing timed out. The file may be too complex.',
            self::JOB_QUEUE_FULL => 'Processing queue is full. Please try again later.',
            self::JOB_RETRY_LIMIT_EXCEEDED => 'Processing failed after multiple retries.',
            self::JOB_PERMANENTLY_FAILED => 'Processing has permanently failed and cannot be retried.',
            
            // Client
            self::INVALID_REQUEST => 'The request is invalid.',
            self::MALFORMED_DATA => 'The request data is malformed.',
            self::UNSUPPORTED_OPERATION => 'This operation is not supported.',
            self::CLIENT_TIMEOUT => 'The request timed out.',
        };
    }
    
    public function userAction(): ?string
    {
        return match($this) {
            // Authentication
            self::UNAUTHORIZED => 'Please log in to continue.',
            self::INVALID_TOKEN, self::TOKEN_EXPIRED => 'Please refresh the page and try again.',
            self::INSUFFICIENT_PERMISSIONS => 'Contact an administrator if you believe this is an error.',
            
            // Validation
            self::VALIDATION_FAILED => 'Please correct the highlighted fields and try again.',
            self::MISSING_REQUIRED_FIELD => 'Please fill in all required fields.',
            self::INVALID_FORMAT => 'Please check the format and try again.',
            self::FILE_TOO_LARGE => 'Please choose a smaller file or compress your current file.',
            self::INVALID_FILE_TYPE => 'Please upload a valid Ableton device group (.adg) file.',
            self::DUPLICATE_ENTRY => 'This file has already been uploaded. You can find it in your racks.',
            
            // Resources
            self::RESOURCE_NOT_FOUND => 'Please check the URL or navigate back to the main page.',
            self::RESOURCE_DELETED => 'Please refresh the page to see updated content.',
            self::RESOURCE_LOCKED => 'Please try again in a few minutes or contact the current editor.',
            self::RESOURCE_UNAVAILABLE => 'Please try again in a few minutes.',
            
            // Processing
            self::PROCESSING_FAILED => 'Please try uploading the file again or contact support if the issue persists.',
            self::ANALYSIS_FAILED => 'Please verify your .adg file is valid and not corrupted.',
            self::CONVERSION_FAILED => 'Please try again or contact support if the issue persists.',
            self::UPLOAD_FAILED => 'Please check your internet connection and try again.',
            self::DOWNLOAD_FAILED => 'Please try again or contact support if the issue persists.',
            
            // Concurrency
            self::CONFLICT_DETECTED => 'Please review the conflicts and choose which changes to keep.',
            self::VERSION_MISMATCH => 'Please refresh the page to get the latest version.',
            self::CONCURRENT_MODIFICATION => 'Please wait for the other user to finish or try again later.',
            self::AUTOSAVE_CONFLICT => 'Please resolve the conflicts to continue editing.',
            self::LOCK_TIMEOUT => 'Please try again or contact support if the issue persists.',
            
            // System
            self::DATABASE_ERROR => 'Please try again in a few minutes.',
            self::FILESYSTEM_ERROR => 'Please try again or contact support if the issue persists.',
            self::NETWORK_ERROR => 'Please check your internet connection and try again.',
            self::SERVICE_UNAVAILABLE => 'Please try again in a few minutes.',
            self::RATE_LIMIT_EXCEEDED => 'Please wait a moment before trying again.',
            self::TEMPORARY_FAILURE => 'Please wait a moment and try again.',
            
            // Jobs
            self::JOB_FAILED => 'You can retry processing or contact support if the issue persists.',
            self::JOB_TIMEOUT => 'Try uploading a simpler file or contact support for help.',
            self::JOB_QUEUE_FULL => 'Please try again in a few minutes when processing load is lower.',
            self::JOB_RETRY_LIMIT_EXCEEDED => 'Please contact support for assistance with this file.',
            self::JOB_PERMANENTLY_FAILED => 'Please upload a different file or contact support.',
            
            // Client
            self::INVALID_REQUEST => 'Please refresh the page and try again.',
            self::MALFORMED_DATA => 'Please refresh the page and try again.',
            self::UNSUPPORTED_OPERATION => 'Please try a different approach or contact support.',
            self::CLIENT_TIMEOUT => 'Please try again with a stable internet connection.',
        };
    }
    
    public function isRetryable(): bool
    {
        return match($this) {
            // Non-retryable errors
            self::UNAUTHORIZED,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::INSUFFICIENT_PERMISSIONS,
            self::MISSING_REQUIRED_FIELD,
            self::INVALID_FORMAT,
            self::FILE_TOO_LARGE,
            self::INVALID_FILE_TYPE,
            self::DUPLICATE_ENTRY,
            self::RESOURCE_NOT_FOUND,
            self::RESOURCE_DELETED,
            self::JOB_PERMANENTLY_FAILED,
            self::MALFORMED_DATA,
            self::UNSUPPORTED_OPERATION => false,
            
            // Retryable errors
            default => true,
        };
    }
    
    public function retryDelay(): int
    {
        return match($this) {
            // Immediate retry
            self::NETWORK_ERROR,
            self::CLIENT_TIMEOUT => 1000,
            
            // Short delay
            self::TEMPORARY_FAILURE,
            self::DATABASE_ERROR,
            self::PROCESSING_FAILED => 3000,
            
            // Medium delay
            self::SERVICE_UNAVAILABLE,
            self::JOB_FAILED,
            self::RESOURCE_UNAVAILABLE => 5000,
            
            // Long delay
            self::RATE_LIMIT_EXCEEDED => 10000,
            self::JOB_QUEUE_FULL => 15000,
            
            // Very long delay
            self::LOCK_TIMEOUT => 30000,
            
            // Default
            default => 5000,
        };
    }
    
    public function severity(): string
    {
        return match($this) {
            // Critical - affects core functionality
            self::DATABASE_ERROR,
            self::FILESYSTEM_ERROR,
            self::SERVICE_UNAVAILABLE,
            self::JOB_PERMANENTLY_FAILED => 'critical',
            
            // High - important operations fail
            self::PROCESSING_FAILED,
            self::ANALYSIS_FAILED,
            self::UPLOAD_FAILED,
            self::JOB_FAILED,
            self::CONCURRENT_MODIFICATION => 'high',
            
            // Medium - user experience impact
            self::VALIDATION_FAILED,
            self::CONFLICT_DETECTED,
            self::VERSION_MISMATCH,
            self::RATE_LIMIT_EXCEEDED,
            self::RESOURCE_LOCKED => 'medium',
            
            // Low - minor issues
            self::TEMPORARY_FAILURE,
            self::NETWORK_ERROR,
            self::CLIENT_TIMEOUT => 'low',
            
            // Info - expected behavior
            self::UNAUTHORIZED,
            self::DUPLICATE_ENTRY,
            self::RESOURCE_NOT_FOUND => 'info',
        };
    }
}