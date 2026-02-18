<?php

namespace App\Http\Livewire\Traits;

use App\Enums\ErrorCode;
use App\Http\Responses\ErrorResponse;
use Illuminate\Support\Facades\Log;

trait HandlesLoadingStates
{
    public $isLoading = false;
    public $loadingMessage = 'Loading...';
    public $loadingProgress = 0;
    public $loadingType = 'spinner'; // spinner, progress, skeleton, dots
    public $error = null;
    public $errorCode = null;
    public $canRetry = false;
    public $retryAttempts = 0;
    public $maxRetries = 3;
    
    // Real-time updates
    public $enableRealTimeUpdates = false;
    public $updateInterval = 5000; // milliseconds
    public $lastUpdated = null;
    
    /**
     * Initialize loading state management
     */
    public function mountHandlesLoadingStates()
    {
        $this->lastUpdated = now()->toISOString();
    }
    
    /**
     * Show loading state
     */
    protected function startLoading(string $message = 'Loading...', string $type = 'spinner')
    {
        $this->isLoading = true;
        $this->loadingMessage = $message;
        $this->loadingType = $type;
        $this->loadingProgress = 0;
        $this->error = null;
        $this->errorCode = null;
        
        // Emit event for frontend listening
        $this->emit('loadingStarted', [
            'message' => $message,
            'type' => $type,
            'component' => static::class
        ]);
    }
    
    /**
     * Update loading progress
     */
    protected function updateLoadingProgress(int $progress, string $message = null)
    {
        if (!$this->isLoading) {
            return;
        }
        
        $this->loadingProgress = max(0, min(100, $progress));
        
        if ($message) {
            $this->loadingMessage = $message;
        }
        
        $this->emit('loadingProgress', [
            'progress' => $this->loadingProgress,
            'message' => $this->loadingMessage,
            'component' => static::class
        ]);
    }
    
    /**
     * Stop loading state
     */
    protected function stopLoading()
    {
        $this->isLoading = false;
        $this->loadingMessage = 'Loading...';
        $this->loadingProgress = 0;
        $this->lastUpdated = now()->toISOString();
        
        $this->emit('loadingStopped', [
            'component' => static::class
        ]);
    }
    
    /**
     * Show error with optional retry
     */
    protected function showError(
        ErrorCode $errorCode,
        ?string $customMessage = null,
        bool $canRetry = true,
        array $context = []
    ) {
        $this->isLoading = false;
        $this->error = $customMessage ?? $errorCode->message();
        $this->errorCode = $errorCode->value;
        $this->canRetry = $canRetry && $errorCode->isRetryable();
        
        // Log error for monitoring
        Log::warning('Livewire component error', [
            'component' => static::class,
            'error_code' => $errorCode->value,
            'message' => $this->error,
            'context' => $context,
            'user_id' => auth()->id(),
            'session_id' => session()->getId()
        ]);
        
        $this->emit('errorOccurred', [
            'error' => $this->error,
            'code' => $this->errorCode,
            'canRetry' => $this->canRetry,
            'userAction' => $errorCode->userAction(),
            'severity' => $errorCode->severity(),
            'component' => static::class
        ]);
    }
    
    /**
     * Clear error state
     */
    protected function clearError()
    {
        $this->error = null;
        $this->errorCode = null;
        $this->canRetry = false;
        $this->retryAttempts = 0;
    }
    
    /**
     * Retry last failed operation
     */
    public function retryOperation()
    {
        if (!$this->canRetry || $this->retryAttempts >= $this->maxRetries) {
            $this->showError(
                ErrorCode::JOB_RETRY_LIMIT_EXCEEDED,
                'Maximum retry attempts exceeded',
                false
            );
            return;
        }
        
        $this->retryAttempts++;
        $this->clearError();
        
        // Show retry loading state
        $this->startLoading(
            "Retrying... ({$this->retryAttempts}/{$this->maxRetries})",
            'spinner'
        );
        
        // Emit retry event for component to handle
        $this->emit('retryRequested', [
            'attempt' => $this->retryAttempts,
            'component' => static::class
        ]);
        
        // Call retry method if it exists
        if (method_exists($this, 'handleRetry')) {
            $this->handleRetry();
        }
    }
    
    /**
     * Execute operation with error handling
     */
    protected function executeWithErrorHandling(callable $operation, string $loadingMessage = 'Processing...')
    {
        try {
            $this->startLoading($loadingMessage);
            
            $result = $operation();
            
            $this->stopLoading();
            $this->clearError();
            
            return $result;
            
        } catch (\App\Exceptions\ValidationException $e) {
            $this->showError(
                ErrorCode::VALIDATION_FAILED,
                'Please check your input and try again',
                false,
                ['validation_errors' => $e->getErrors()]
            );
            throw $e;
            
        } catch (\App\Exceptions\ConcurrencyConflictException $e) {
            $this->showError(
                ErrorCode::CONCURRENT_MODIFICATION,
                $e->getMessage(),
                true,
                ['conflict_type' => $e->getConflictType()]
            );
            throw $e;
            
        } catch (\Illuminate\Database\QueryException $e) {
            $this->showError(
                ErrorCode::DATABASE_ERROR,
                'A database error occurred. Please try again.',
                true,
                ['sql_error' => $e->getMessage()]
            );
            throw $e;
            
        } catch (\Exception $e) {
            $this->showError(
                ErrorCode::TEMPORARY_FAILURE,
                'An unexpected error occurred. Please try again.',
                true,
                ['exception' => $e->getMessage()]
            );
            throw $e;
        }
    }
    
    /**
     * Execute async operation with polling
     */
    protected function executeAsyncOperation(
        callable $operation,
        callable $statusChecker,
        string $loadingMessage = 'Processing...',
        int $maxPolls = 60
    ) {
        try {
            $this->startLoading($loadingMessage, 'progress');
            
            // Start the operation
            $operationResult = $operation();
            
            // If operation returns immediately, we're done
            if ($this->isOperationComplete($operationResult)) {
                $this->stopLoading();
                return $operationResult;
            }
            
            // Enable real-time updates for polling
            $this->enableRealTimeUpdates = true;
            
            // Store operation ID for status checking
            $this->emit('asyncOperationStarted', [
                'operation_id' => $operationResult['id'] ?? 'unknown',
                'estimated_completion' => $operationResult['estimated_completion'] ?? null,
                'component' => static::class
            ]);
            
            return $operationResult;
            
        } catch (\Exception $e) {
            $this->showError(
                ErrorCode::PROCESSING_FAILED,
                'Failed to start operation: ' . $e->getMessage(),
                true,
                ['operation_error' => $e->getMessage()]
            );
            throw $e;
        }
    }
    
    /**
     * Check if operation is complete
     */
    protected function isOperationComplete($result): bool
    {
        if (is_array($result)) {
            return ($result['status'] ?? '') === 'completed' || 
                   ($result['is_complete'] ?? false);
        }
        
        if (is_object($result) && method_exists($result, 'isComplete')) {
            return $result->isComplete();
        }
        
        return true; // Assume complete if we can't determine
    }
    
    /**
     * Poll for operation status (called by frontend)
     */
    public function pollStatus()
    {
        if (!$this->enableRealTimeUpdates) {
            return;
        }
        
        try {
            // Call status check method if it exists
            if (method_exists($this, 'checkOperationStatus')) {
                $status = $this->checkOperationStatus();
                
                if ($status) {
                    $this->handleStatusUpdate($status);
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to poll status in Livewire component', [
                'component' => static::class,
                'error' => $e->getMessage()
            ]);
            
            // Don't show error for polling failures, just log them
        }
    }
    
    /**
     * Handle status update
     */
    protected function handleStatusUpdate(array $status)
    {
        $this->lastUpdated = now()->toISOString();
        
        if ($status['is_loading'] ?? false) {
            $progress = $status['progress_percentage'] ?? $this->loadingProgress;
            $message = $status['status_message'] ?? $this->loadingMessage;
            
            $this->updateLoadingProgress($progress, $message);
        } else {
            $this->enableRealTimeUpdates = false;
            
            if ($status['has_error'] ?? false) {
                $errorCode = ErrorCode::tryFrom($status['error_code'] ?? '') ?? ErrorCode::PROCESSING_FAILED;
                $this->showError(
                    $errorCode,
                    $status['error_message'] ?? null,
                    $status['can_retry'] ?? true
                );
            } else {
                $this->stopLoading();
                
                // Emit completion event
                $this->emit('operationCompleted', [
                    'status' => $status,
                    'component' => static::class
                ]);
            }
        }
    }
    
    /**
     * Handle connection recovery
     */
    public function handleConnectionRecovery()
    {
        if ($this->enableRealTimeUpdates) {
            // Resume polling after connection recovery
            $this->emit('resumePolling', [
                'component' => static::class
            ]);
        }
    }
    
    /**
     * Get loading state for frontend
     */
    public function getLoadingStateProperty()
    {
        return [
            'isLoading' => $this->isLoading,
            'message' => $this->loadingMessage,
            'progress' => $this->loadingProgress,
            'type' => $this->loadingType,
            'error' => $this->error,
            'errorCode' => $this->errorCode,
            'canRetry' => $this->canRetry,
            'retryAttempts' => $this->retryAttempts,
            'maxRetries' => $this->maxRetries,
            'enableRealTimeUpdates' => $this->enableRealTimeUpdates,
            'updateInterval' => $this->updateInterval,
            'lastUpdated' => $this->lastUpdated
        ];
    }
    
    /**
     * Render loading state partial
     */
    protected function renderLoadingState(): string
    {
        if (!$this->isLoading && !$this->error) {
            return '';
        }
        
        $viewName = $this->error ? 'components.error-state' : 'components.loading-state';
        
        return view($viewName, [
            'loadingState' => $this->getLoadingStateProperty(),
            'component' => $this
        ])->render();
    }
}