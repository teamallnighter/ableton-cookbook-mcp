<?php

namespace App\Http\Responses;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ErrorResponse
{
    public static function create(
        ErrorCode $errorCode,
        ?string $customMessage = null,
        array $context = [],
        ?array $metadata = null,
        ?\Throwable $exception = null
    ): JsonResponse {
        // Log the error for monitoring
        self::logError($errorCode, $context, $exception);
        
        $response = [
            'success' => false,
            'error' => [
                'code' => $errorCode->value,
                'message' => $customMessage ?? $errorCode->message(),
                'user_action' => $errorCode->userAction(),
                'severity' => $errorCode->severity(),
                'is_retryable' => $errorCode->isRetryable(),
            ]
        ];
        
        // Add retry information for retryable errors
        if ($errorCode->isRetryable()) {
            $response['error']['retry_delay'] = $errorCode->retryDelay();
            $response['error']['retry_strategy'] = self::getRetryStrategy($errorCode);
        }
        
        // Add metadata if provided
        if ($metadata) {
            $response['error']['metadata'] = $metadata;
        }
        
        // Add context in development mode
        if (config('app.debug') && !empty($context)) {
            $response['error']['debug_context'] = $context;
        }
        
        // Add request ID for tracking
        $response['error']['request_id'] = request()->header('X-Request-ID', uniqid('req_', true));
        
        return response()->json($response, self::getHttpStatus($errorCode));
    }
    
    private static function logError(ErrorCode $errorCode, array $context, ?\Throwable $exception): void
    {
        $logContext = array_merge($context, [
            'error_code' => $errorCode->value,
            'severity' => $errorCode->severity(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
        
        if ($exception) {
            $logContext['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }
        
        match($errorCode->severity()) {
            'critical' => Log::critical($errorCode->message(), $logContext),
            'high' => Log::error($errorCode->message(), $logContext),
            'medium' => Log::warning($errorCode->message(), $logContext),
            'low' => Log::notice($errorCode->message(), $logContext),
            'info' => Log::info($errorCode->message(), $logContext),
        };
    }
    
    private static function getHttpStatus(ErrorCode $errorCode): int
    {
        return match($errorCode) {
            // 400 Bad Request
            ErrorCode::VALIDATION_FAILED,
            ErrorCode::MISSING_REQUIRED_FIELD,
            ErrorCode::INVALID_FORMAT,
            ErrorCode::INVALID_REQUEST,
            ErrorCode::MALFORMED_DATA => 400,
            
            // 401 Unauthorized
            ErrorCode::UNAUTHORIZED,
            ErrorCode::INVALID_TOKEN,
            ErrorCode::TOKEN_EXPIRED => 401,
            
            // 403 Forbidden
            ErrorCode::INSUFFICIENT_PERMISSIONS => 403,
            
            // 404 Not Found
            ErrorCode::RESOURCE_NOT_FOUND,
            ErrorCode::RESOURCE_DELETED => 404,
            
            // 409 Conflict
            ErrorCode::DUPLICATE_ENTRY,
            ErrorCode::CONFLICT_DETECTED,
            ErrorCode::VERSION_MISMATCH,
            ErrorCode::CONCURRENT_MODIFICATION,
            ErrorCode::AUTOSAVE_CONFLICT => 409,
            
            // 413 Payload Too Large
            ErrorCode::FILE_TOO_LARGE => 413,
            
            // 415 Unsupported Media Type
            ErrorCode::INVALID_FILE_TYPE => 415,
            
            // 422 Unprocessable Entity
            ErrorCode::PROCESSING_FAILED,
            ErrorCode::ANALYSIS_FAILED,
            ErrorCode::CONVERSION_FAILED => 422,
            
            // 423 Locked
            ErrorCode::RESOURCE_LOCKED,
            ErrorCode::LOCK_TIMEOUT => 423,
            
            // 429 Too Many Requests
            ErrorCode::RATE_LIMIT_EXCEEDED => 429,
            
            // 500 Internal Server Error
            ErrorCode::DATABASE_ERROR,
            ErrorCode::FILESYSTEM_ERROR,
            ErrorCode::JOB_FAILED,
            ErrorCode::JOB_PERMANENTLY_FAILED => 500,
            
            // 502 Bad Gateway
            ErrorCode::NETWORK_ERROR => 502,
            
            // 503 Service Unavailable
            ErrorCode::SERVICE_UNAVAILABLE,
            ErrorCode::RESOURCE_UNAVAILABLE,
            ErrorCode::JOB_QUEUE_FULL => 503,
            
            // 504 Gateway Timeout
            ErrorCode::JOB_TIMEOUT,
            ErrorCode::CLIENT_TIMEOUT => 504,
            
            // 501 Not Implemented
            ErrorCode::UNSUPPORTED_OPERATION => 501,
            
            // Default to 500
            default => 500,
        };
    }
    
    private static function getRetryStrategy(ErrorCode $errorCode): array
    {
        return match($errorCode) {
            // Linear backoff
            ErrorCode::NETWORK_ERROR,
            ErrorCode::CLIENT_TIMEOUT => [
                'type' => 'linear',
                'max_attempts' => 3,
                'base_delay' => $errorCode->retryDelay(),
            ],
            
            // Exponential backoff
            ErrorCode::RATE_LIMIT_EXCEEDED,
            ErrorCode::SERVICE_UNAVAILABLE,
            ErrorCode::TEMPORARY_FAILURE => [
                'type' => 'exponential',
                'max_attempts' => 5,
                'base_delay' => $errorCode->retryDelay(),
                'max_delay' => 60000, // 60 seconds
            ],
            
            // Fixed delay
            ErrorCode::DATABASE_ERROR,
            ErrorCode::PROCESSING_FAILED => [
                'type' => 'fixed',
                'max_attempts' => 3,
                'delay' => $errorCode->retryDelay(),
            ],
            
            // No automatic retry - manual only
            ErrorCode::JOB_FAILED,
            ErrorCode::ANALYSIS_FAILED => [
                'type' => 'manual',
                'max_attempts' => 1,
                'delay' => $errorCode->retryDelay(),
            ],
            
            // Default strategy
            default => [
                'type' => 'fixed',
                'max_attempts' => 2,
                'delay' => $errorCode->retryDelay(),
            ],
        };
    }
    
    /**
     * Create a validation error response
     */
    public static function validation(array $errors, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => ErrorCode::VALIDATION_FAILED->value,
                'message' => $message ?? ErrorCode::VALIDATION_FAILED->message(),
                'user_action' => ErrorCode::VALIDATION_FAILED->userAction(),
                'severity' => 'medium',
                'is_retryable' => false,
                'validation_errors' => $errors,
            ],
            'request_id' => request()->header('X-Request-ID', uniqid('req_', true)),
        ], 422);
    }
    
    /**
     * Create a success response with consistent structure
     */
    public static function success(mixed $data = null, ?string $message = null, array $metadata = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($metadata)) {
            $response['metadata'] = $metadata;
        }
        
        return response()->json($response);
    }
    
    /**
     * Create a loading state response
     */
    public static function loading(string $status, array $progress = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'loading' => true,
            'status' => $status,
            'progress' => $progress,
            'timestamp' => now()->toISOString(),
        ]);
    }
}