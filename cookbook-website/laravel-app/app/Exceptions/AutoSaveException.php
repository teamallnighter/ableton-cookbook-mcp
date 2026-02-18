<?php

namespace App\Exceptions;

use Exception;

/**
 * Auto Save Exception
 * 
 * Thrown when auto-save operations fail due to validation errors,
 * network issues, or other problems not related to concurrency.
 */
class AutoSaveException extends Exception
{
    private ?string $field;
    private $attemptedValue;
    private ?string $errorType;
    private array $context;
    
    public function __construct(
        string $message = 'Auto-save operation failed',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $field = null,
        $attemptedValue = null,
        ?string $errorType = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->field = $field;
        $this->attemptedValue = $attemptedValue;
        $this->errorType = $errorType;
        $this->context = $context;
    }
    
    /**
     * Get the field that failed to save
     */
    public function getField(): ?string
    {
        return $this->field;
    }
    
    /**
     * Get the value that failed to save
     */
    public function getAttemptedValue()
    {
        return $this->attemptedValue;
    }
    
    /**
     * Get the type of error
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }
    
    /**
     * Get additional context
     */
    public function getContext(): array
    {
        return $this->context;
    }
    
    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            'network_error',
            'temporary_database_error',
            'timeout',
            'server_overload'
        ]);
    }
    
    /**
     * Get recommended retry delay in milliseconds
     */
    public function getRetryDelay(): int
    {
        return match($this->errorType) {
            'network_error' => 1000,
            'temporary_database_error' => 2000,
            'timeout' => 3000,
            'server_overload' => 5000,
            default => 1000
        };
    }
    
    /**
     * Convert to array for JSON responses
     */
    public function toArray(): array
    {
        return [
            'type' => 'auto_save_error',
            'message' => $this->getMessage(),
            'field' => $this->field,
            'error_type' => $this->errorType,
            'is_retryable' => $this->isRetryable(),
            'retry_delay' => $this->isRetryable() ? $this->getRetryDelay() : null,
            'context' => $this->context
        ];
    }
}