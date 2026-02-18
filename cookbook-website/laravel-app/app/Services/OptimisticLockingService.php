<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\OptimisticLockException;
use Carbon\Carbon;

/**
 * Optimistic Locking Service
 * 
 * Provides concurrency control for auto-save operations using optimistic locking.
 * Prevents race conditions and data loss in concurrent editing scenarios.
 */
class OptimisticLockingService
{
    /**
     * Update model with optimistic locking
     *
     * @param Model $model The model to update
     * @param array $data Data to update
     * @param int|null $expectedVersion Expected version number for conflict detection
     * @return array Updated model data with new version
     * @throws OptimisticLockException
     * @throws ConcurrencyConflictException
     */
    public function updateWithVersion(Model $model, array $data, ?int $expectedVersion = null): array
    {
        $attempts = 0;
        $maxAttempts = 3;
        
        while ($attempts < $maxAttempts) {
            try {
                return DB::transaction(function () use ($model, $data, $expectedVersion) {
                    // Fresh model to get latest version
                    $freshModel = $model->fresh(['id', 'version', 'updated_at']);
                    
                    if (!$freshModel) {
                        throw new OptimisticLockException('Model not found during update');
                    }
                    
                    // Check for version conflicts
                    if ($expectedVersion !== null && $freshModel->version !== $expectedVersion) {
                        $this->logConflict($model, $expectedVersion, $freshModel->version);
                        
                        throw new ConcurrencyConflictException(
                            'Concurrent modification detected',
                            $expectedVersion,
                            $freshModel->version,
                            $this->detectChangedFields($model, $data)
                        );
                    }
                    
                    // Prepare update data with version increment
                    $updateData = array_merge($data, [
                        'version' => ($freshModel->version ?? 0) + 1,
                        'last_auto_save' => Carbon::now()
                    ]);
                    
                    // Perform atomic update with version check
                    $updated = $freshModel->where('id', $freshModel->id)
                        ->where('version', $freshModel->version ?? 0)
                        ->update($updateData);
                    
                    if ($updated === 0) {
                        throw new ConcurrencyConflictException(
                            'Update failed due to concurrent modification',
                            $expectedVersion,
                            null, // Unknown new version
                            array_keys($data)
                        );
                    }
                    
                    // Return fresh model data
                    $updatedModel = $freshModel->fresh();
                    
                    $this->logSuccess($model, $updatedModel->version, array_keys($data));
                    
                    return [
                        'model' => $updatedModel,
                        'version' => $updatedModel->version,
                        'fields_updated' => array_keys($data),
                        'timestamp' => $updatedModel->updated_at
                    ];
                });
                
            } catch (QueryException $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    $this->logError($model, $e, $data);
                    throw new OptimisticLockException(
                        'Database error during optimistic update: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
                
                // Brief backoff before retry
                usleep(50000 * $attempts); // 50ms, 100ms, 150ms
            }
        }
        
        throw new OptimisticLockException('Maximum retry attempts exceeded');
    }
    
    /**
     * Detect conflicts between current data and incoming changes
     *
     * @param Model $model Current model
     * @param array $incomingData New data to save
     * @return array Conflict analysis
     */
    public function detectConflicts(Model $model, array $incomingData): array
    {
        $freshModel = $model->fresh();
        $conflicts = [];
        
        foreach ($incomingData as $field => $newValue) {
            $currentValue = $freshModel->getAttribute($field);
            $originalValue = $model->getOriginal($field);
            
            // Check if field was changed by another user
            if ($currentValue !== $originalValue && $currentValue !== $newValue) {
                $conflicts[] = [
                    'field' => $field,
                    'original_value' => $originalValue,
                    'current_value' => $currentValue,
                    'incoming_value' => $newValue,
                    'conflict_type' => $this->determineConflictType($originalValue, $currentValue, $newValue)
                ];
            }
        }
        
        return [
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts,
            'total_conflicts' => count($conflicts),
            'model_version' => $freshModel->version ?? 0,
            'last_modified' => $freshModel->updated_at
        ];
    }
    
    /**
     * Resolve conflicts using specified strategy
     *
     * @param array $conflicts Conflicts from detectConflicts
     * @param string $resolution Resolution strategy
     * @param array $userChoices User-specific field choices
     * @return array Resolved data
     */
    public function resolveConflicts(array $conflicts, string $resolution = 'last_write_wins', array $userChoices = []): array
    {
        $resolvedData = [];
        
        foreach ($conflicts['conflicts'] as $conflict) {
            $field = $conflict['field'];
            
            // Check for user-specific choice first
            if (isset($userChoices[$field])) {
                $resolvedData[$field] = $userChoices[$field];
                continue;
            }
            
            // Apply resolution strategy
            switch ($resolution) {
                case 'last_write_wins':
                    $resolvedData[$field] = $conflict['incoming_value'];
                    break;
                    
                case 'first_write_wins':
                    $resolvedData[$field] = $conflict['current_value'];
                    break;
                    
                case 'merge_text':
                    if ($this->isTextField($conflict)) {
                        $resolvedData[$field] = $this->mergeTextContent(
                            $conflict['original_value'],
                            $conflict['current_value'],
                            $conflict['incoming_value']
                        );
                    } else {
                        $resolvedData[$field] = $conflict['incoming_value'];
                    }
                    break;
                    
                default:
                    $resolvedData[$field] = $conflict['incoming_value'];
            }
        }
        
        return $resolvedData;
    }
    
    /**
     * Check if model supports versioning
     *
     * @param Model $model
     * @return bool
     */
    public function supportsVersioning(Model $model): bool
    {
        return $model->getFillable() && in_array('version', $model->getFillable());
    }
    
    /**
     * Initialize versioning for a model
     *
     * @param Model $model
     * @return bool
     */
    public function initializeVersioning(Model $model): bool
    {
        if (!$this->supportsVersioning($model)) {
            return false;
        }
        
        if ($model->version === null) {
            $model->version = 1;
            $model->save();
        }
        
        return true;
    }
    
    /**
     * Get current version of model
     *
     * @param Model $model
     * @return int
     */
    public function getCurrentVersion(Model $model): int
    {
        return $model->fresh()->version ?? 0;
    }
    
    /**
     * Determine conflict type
     */
    private function determineConflictType($original, $current, $incoming): string
    {
        if (is_string($original) && is_string($current) && is_string($incoming)) {
            if (strlen($incoming) > strlen($current)) {
                return 'text_expansion';
            }
            if (strlen($incoming) < strlen($current)) {
                return 'text_reduction';
            }
            return 'text_modification';
        }
        
        return 'value_change';
    }
    
    /**
     * Check if field contains text content
     */
    private function isTextField(array $conflict): bool
    {
        return is_string($conflict['original_value']) && 
               is_string($conflict['current_value']) && 
               is_string($conflict['incoming_value']);
    }
    
    /**
     * Merge text content using simple strategy
     */
    private function mergeTextContent(string $original, string $current, string $incoming): string
    {
        // Simple merge strategy - append changes
        // In a production environment, you might use a more sophisticated diff algorithm
        
        if ($current === $original) {
            return $incoming;
        }
        
        if ($incoming === $original) {
            return $current;
        }
        
        // Both have changes - combine them
        $currentChanges = $this->getTextDiff($original, $current);
        $incomingChanges = $this->getTextDiff($original, $incoming);
        
        // For now, return incoming (last write wins for complex text merging)
        // TODO: Implement more sophisticated text merging
        return $incoming;
    }
    
    /**
     * Get simple text diff (placeholder for more sophisticated diff)
     */
    private function getTextDiff(string $original, string $modified): array
    {
        return [
            'added' => strlen($modified) - strlen($original),
            'content' => $modified
        ];
    }
    
    /**
     * Detect which fields have changed
     */
    private function detectChangedFields(Model $model, array $data): array
    {
        $changed = [];
        
        foreach ($data as $field => $value) {
            if ($model->getAttribute($field) !== $value) {
                $changed[] = $field;
            }
        }
        
        return $changed;
    }
    
    /**
     * Log successful update
     */
    private function logSuccess(Model $model, int $version, array $fields): void
    {
        Log::info('Optimistic lock update succeeded', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'new_version' => $version,
            'updated_fields' => $fields
        ]);
    }
    
    /**
     * Log conflict detection
     */
    private function logConflict(Model $model, int $expected, int $actual): void
    {
        Log::warning('Optimistic lock conflict detected', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'expected_version' => $expected,
            'actual_version' => $actual
        ]);
    }
    
    /**
     * Log error during update
     */
    private function logError(Model $model, \Exception $exception, array $data): void
    {
        Log::error('Optimistic lock update failed', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'error' => $exception->getMessage(),
            'attempted_fields' => array_keys($data)
        ]);
    }
}