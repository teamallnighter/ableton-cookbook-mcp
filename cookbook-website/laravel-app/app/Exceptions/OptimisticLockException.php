<?php

namespace App\Exceptions;

use Exception;

/**
 * Optimistic Lock Exception
 * 
 * Thrown when optimistic locking operations fail due to technical issues
 * (as opposed to ConcurrencyConflictException which indicates user conflicts).
 */
class OptimisticLockException extends Exception
{
    private ?string $modelType;
    private $modelId;
    private ?string $operation;
    private array $failedFields;
    
    public function __construct(
        string $message = 'Optimistic locking operation failed',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $modelType = null,
        $modelId = null,
        ?string $operation = null,
        array $failedFields = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->operation = $operation;
        $this->failedFields = $failedFields;
    }
    
    /**
     * Get the model type that failed to update
     */
    public function getModelType(): ?string
    {
        return $this->modelType;
    }
    
    /**
     * Get the model ID that failed to update
     */
    public function getModelId()
    {
        return $this->modelId;
    }
    
    /**
     * Get the operation that failed
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
    
    /**
     * Get the fields that failed to update
     */
    public function getFailedFields(): array
    {
        return $this->failedFields;
    }
    
    /**
     * Convert to array for JSON responses
     */
    public function toArray(): array
    {
        return [
            'type' => 'optimistic_lock_error',
            'message' => $this->getMessage(),
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'operation' => $this->operation,
            'failed_fields' => $this->failedFields,
            'retry_recommended' => true
        ];
    }
}