<?php

namespace App\Exceptions;

use Exception;

/**
 * Concurrency Conflict Exception
 * 
 * Thrown when a concurrency conflict is detected during optimistic locking operations.
 * Contains detailed information about the conflict for resolution.
 */
class ConcurrencyConflictException extends Exception
{
    private ?int $expectedVersion;
    private ?int $currentVersion;
    private array $conflictedFields;
    
    public function __construct(
        string $message = 'Concurrency conflict detected',
        ?int $expectedVersion = null,
        ?int $currentVersion = null,
        array $conflictedFields = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->expectedVersion = $expectedVersion;
        $this->currentVersion = $currentVersion;
        $this->conflictedFields = $conflictedFields;
    }
    
    /**
     * Get the version number that was expected
     */
    public function getExpectedVersion(): ?int
    {
        return $this->expectedVersion;
    }
    
    /**
     * Get the current version number in the database
     */
    public function getCurrentVersion(): ?int
    {
        return $this->currentVersion;
    }
    
    /**
     * Get the fields that have conflicts
     */
    public function getConflictedFields(): array
    {
        return $this->conflictedFields;
    }
    
    /**
     * Check if specific field has conflict
     */
    public function hasFieldConflict(string $field): bool
    {
        return in_array($field, $this->conflictedFields);
    }
    
    /**
     * Get detailed conflict information
     */
    public function getConflictDetails(): array
    {
        return [
            'message' => $this->getMessage(),
            'expected_version' => $this->expectedVersion,
            'current_version' => $this->currentVersion,
            'conflicted_fields' => $this->conflictedFields,
            'version_gap' => $this->currentVersion && $this->expectedVersion 
                ? $this->currentVersion - $this->expectedVersion 
                : null
        ];
    }
    
    /**
     * Convert to array for JSON responses
     */
    public function toArray(): array
    {
        return [
            'type' => 'concurrency_conflict',
            'message' => $this->getMessage(),
            'expected_version' => $this->expectedVersion,
            'current_version' => $this->currentVersion,
            'conflicted_fields' => $this->conflictedFields,
            'resolution_required' => true
        ];
    }
}