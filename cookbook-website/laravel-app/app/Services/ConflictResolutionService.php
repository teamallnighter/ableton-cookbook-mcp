<?php

namespace App\Services;

use App\Models\Rack;
use App\Services\OptimisticLockingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Conflict Resolution Service
 * 
 * Handles conflict resolution for concurrent editing scenarios.
 * Provides user-friendly conflict resolution UI and automated resolution strategies.
 */
class ConflictResolutionService
{
    public function __construct(
        private OptimisticLockingService $lockingService
    ) {}
    
    /**
     * Present conflicts to user for resolution
     *
     * @param Rack $rack
     * @param string $sessionId
     * @return array Conflict presentation data
     */
    public function presentConflictsForResolution(Rack $rack, string $sessionId): array
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        $conflictData = Cache::get($conflictKey);
        
        if (!$conflictData || $conflictData['resolved']) {
            return [
                'has_conflicts' => false,
                'conflicts' => []
            ];
        }
        
        $conflicts = $conflictData['conflicts'];
        $presentableConflicts = [];
        
        foreach ($conflicts['conflicts'] as $conflict) {
            $presentableConflicts[] = [
                'field' => $conflict['field'],
                'field_label' => $this->getFieldLabel($conflict['field']),
                'your_version' => [
                    'value' => $conflict['incoming_value'],
                    'preview' => $this->generatePreview($conflict['field'], $conflict['incoming_value']),
                    'timestamp' => 'Your changes'
                ],
                'server_version' => [
                    'value' => $conflict['current_value'],
                    'preview' => $this->generatePreview($conflict['field'], $conflict['current_value']),
                    'timestamp' => $conflicts['last_modified'] ?? 'Server version'
                ],
                'conflict_type' => $conflict['conflict_type'],
                'suggestions' => $this->generateResolutionSuggestions($conflict),
                'auto_mergeable' => $this->isAutoMergeable($conflict)
            ];
        }
        
        return [
            'has_conflicts' => true,
            'conflict_count' => count($presentableConflicts),
            'conflicts' => $presentableConflicts,
            'model_version' => $conflicts['model_version'],
            'session_id' => $sessionId,
            'timestamp' => $conflictData['timestamp']
        ];
    }
    
    /**
     * Resolve conflicts based on user choices
     *
     * @param Rack $rack
     * @param string $sessionId
     * @param array $resolutions
     * @return array Resolution result
     */
    public function resolveConflicts(Rack $rack, string $sessionId, array $resolutions): array
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        $conflictData = Cache::get($conflictKey);
        
        if (!$conflictData || $conflictData['resolved']) {
            return [
                'success' => false,
                'error' => 'No pending conflicts found'
            ];
        }
        
        try {
            $conflicts = $conflictData['conflicts'];
            $resolvedData = [];
            
            // Process each resolution
            foreach ($resolutions as $fieldName => $resolution) {
                $conflict = $this->findConflictForField($conflicts['conflicts'], $fieldName);
                
                if (!$conflict) {
                    continue;
                }
                
                $resolvedValue = $this->applyResolution($conflict, $resolution);
                $resolvedData[$fieldName] = $resolvedValue;
            }
            
            // Apply resolved data using optimistic locking
            $result = $this->lockingService->updateWithVersion(
                $rack, 
                $resolvedData, 
                $conflicts['model_version']
            );
            
            // Mark conflicts as resolved
            $this->markConflictsResolved($rack, $sessionId);
            
            // Log resolution
            $this->logConflictResolution($rack, $sessionId, $resolutions, true);
            
            return [
                'success' => true,
                'resolved_fields' => array_keys($resolvedData),
                'new_version' => $result['version'],
                'timestamp' => $result['timestamp']
            ];
            
        } catch (\Exception $e) {
            $this->logConflictResolution($rack, $sessionId, $resolutions, false, $e);
            
            return [
                'success' => false,
                'error' => 'Failed to resolve conflicts: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Auto-resolve conflicts where possible
     *
     * @param Rack $rack
     * @param string $sessionId
     * @param string $strategy
     * @return array Auto-resolution result
     */
    public function autoResolveConflicts(Rack $rack, string $sessionId, string $strategy = 'smart_merge'): array
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        $conflictData = Cache::get($conflictKey);
        
        if (!$conflictData || $conflictData['resolved']) {
            return [
                'success' => false,
                'error' => 'No pending conflicts found'
            ];
        }
        
        $conflicts = $conflictData['conflicts'];
        $autoResolutions = [];
        $manualResolutionRequired = [];
        
        foreach ($conflicts['conflicts'] as $conflict) {
            if ($this->canAutoResolve($conflict, $strategy)) {
                $autoResolutions[$conflict['field']] = $this->getAutoResolution($conflict, $strategy);
            } else {
                $manualResolutionRequired[] = $conflict['field'];
            }
        }
        
        $result = [
            'auto_resolved_count' => count($autoResolutions),
            'manual_resolution_required' => count($manualResolutionRequired),
            'manual_fields' => $manualResolutionRequired
        ];
        
        if (!empty($autoResolutions)) {
            $resolutionResult = $this->resolveConflicts($rack, $sessionId, $autoResolutions);
            $result['auto_resolution_result'] = $resolutionResult;
        }
        
        return $result;
    }
    
    /**
     * Get conflict history for debugging
     *
     * @param Rack $rack
     * @param int $limit
     * @return array Conflict history
     */
    public function getConflictHistory(Rack $rack, int $limit = 10): array
    {
        $historyKey = "rack_conflict_history_{$rack->id}";
        $history = Cache::get($historyKey, []);
        
        // Return most recent conflicts
        return array_slice($history, -$limit);
    }
    
    /**
     * Clear resolved conflicts
     *
     * @param Rack $rack
     * @param string $sessionId
     * @return bool Success status
     */
    public function clearResolvedConflicts(Rack $rack, string $sessionId): bool
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        return Cache::forget($conflictKey);
    }
    
    /**
     * Generate field label for UI
     */
    private function getFieldLabel(string $field): string
    {
        return match($field) {
            'title' => 'Title',
            'description' => 'Description',
            'category' => 'Category',
            'tags' => 'Tags',
            'how_to_article' => 'How-to Article',
            default => ucfirst(str_replace('_', ' ', $field))
        };
    }
    
    /**
     * Generate preview for field value
     */
    private function generatePreview(string $field, $value): string
    {
        if (empty($value)) {
            return '(empty)';
        }
        
        $stringValue = (string) $value;
        
        if (strlen($stringValue) <= 100) {
            return $stringValue;
        }
        
        return substr($stringValue, 0, 97) . '...';
    }
    
    /**
     * Generate resolution suggestions
     */
    private function generateResolutionSuggestions(array $conflict): array
    {
        $suggestions = [];
        
        // Always offer basic choices
        $suggestions[] = [
            'id' => 'keep_yours',
            'label' => 'Keep your version',
            'description' => 'Use your changes and discard server changes'
        ];
        
        $suggestions[] = [
            'id' => 'keep_server',
            'label' => 'Keep server version',
            'description' => 'Use server changes and discard your changes'
        ];
        
        // Suggest merge for text fields
        if ($this->isTextField($conflict)) {
            $suggestions[] = [
                'id' => 'merge',
                'label' => 'Try to merge both',
                'description' => 'Attempt to combine both versions'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Check if conflict is auto-mergeable
     */
    private function isAutoMergeable(array $conflict): bool
    {
        // Simple text expansion can be auto-merged
        if ($conflict['conflict_type'] === 'text_expansion') {
            return true;
        }
        
        // Empty to content can be auto-merged
        if (empty($conflict['original_value']) && !empty($conflict['incoming_value'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if conflict can be auto-resolved
     */
    private function canAutoResolve(array $conflict, string $strategy): bool
    {
        switch ($strategy) {
            case 'last_write_wins':
                return true;
                
            case 'first_write_wins':
                return true;
                
            case 'smart_merge':
                return $this->isAutoMergeable($conflict);
                
            default:
                return false;
        }
    }
    
    /**
     * Get auto-resolution value
     */
    private function getAutoResolution(array $conflict, string $strategy): string
    {
        switch ($strategy) {
            case 'last_write_wins':
                return 'keep_yours';
                
            case 'first_write_wins':
                return 'keep_server';
                
            case 'smart_merge':
                if ($this->isAutoMergeable($conflict)) {
                    return 'merge';
                }
                return 'keep_yours';
                
            default:
                return 'keep_yours';
        }
    }
    
    /**
     * Apply resolution to conflict
     */
    private function applyResolution(array $conflict, string $resolution)
    {
        switch ($resolution) {
            case 'keep_yours':
                return $conflict['incoming_value'];
                
            case 'keep_server':
                return $conflict['current_value'];
                
            case 'merge':
                return $this->mergeValues($conflict);
                
            default:
                return $conflict['incoming_value'];
        }
    }
    
    /**
     * Merge conflicting values
     */
    private function mergeValues(array $conflict)
    {
        if (!$this->isTextField($conflict)) {
            return $conflict['incoming_value'];
        }
        
        $original = $conflict['original_value'] ?? '';
        $current = $conflict['current_value'] ?? '';
        $incoming = $conflict['incoming_value'] ?? '';
        
        // Simple merge strategy
        if (empty($original)) {
            // Both added content to empty field - combine them
            if (empty($current)) {
                return $incoming;
            }
            if (empty($incoming)) {
                return $current;
            }
            return $current . "\n\n" . $incoming;
        }
        
        // If one version is just extended, prefer the longer one
        if (strpos($current, $original) === 0 || strpos($incoming, $original) === 0) {
            return strlen($current) > strlen($incoming) ? $current : $incoming;
        }
        
        // Default to incoming value for complex merges
        return $incoming;
    }
    
    /**
     * Check if conflict involves text field
     */
    private function isTextField(array $conflict): bool
    {
        return is_string($conflict['original_value']) && 
               is_string($conflict['current_value']) && 
               is_string($conflict['incoming_value']);
    }
    
    /**
     * Find conflict for specific field
     */
    private function findConflictForField(array $conflicts, string $fieldName): ?array
    {
        foreach ($conflicts as $conflict) {
            if ($conflict['field'] === $fieldName) {
                return $conflict;
            }
        }
        
        return null;
    }
    
    /**
     * Mark conflicts as resolved
     */
    private function markConflictsResolved(Rack $rack, string $sessionId): void
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        $conflictData = Cache::get($conflictKey);
        
        if ($conflictData) {
            $conflictData['resolved'] = true;
            $conflictData['resolved_at'] = Carbon::now();
            Cache::put($conflictKey, $conflictData, 3600);
        }
        
        // Add to history
        $this->addToConflictHistory($rack, $conflictData);
    }
    
    /**
     * Add conflict to history
     */
    private function addToConflictHistory(Rack $rack, array $conflictData): void
    {
        $historyKey = "rack_conflict_history_{$rack->id}";
        $history = Cache::get($historyKey, []);
        
        $history[] = [
            'timestamp' => Carbon::now(),
            'conflict_count' => count($conflictData['conflicts']['conflicts'] ?? []),
            'resolved' => $conflictData['resolved'] ?? false
        ];
        
        // Keep only last 50 entries
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        
        Cache::put($historyKey, $history, 86400); // 24 hour cache
    }
    
    /**
     * Get conflict cache key
     */
    private function getConflictCacheKey(Rack $rack, string $sessionId): string
    {
        return "rack_conflicts_{$rack->id}_{$sessionId}";
    }
    
    /**
     * Log conflict resolution
     */
    private function logConflictResolution(
        Rack $rack, 
        string $sessionId, 
        array $resolutions, 
        bool $success, 
        ?\Exception $exception = null
    ): void {
        $logData = [
            'rack_id' => $rack->id,
            'session_id' => $sessionId,
            'resolutions' => $resolutions,
            'resolution_count' => count($resolutions),
            'success' => $success
        ];
        
        if ($exception) {
            $logData['error'] = $exception->getMessage();
        }
        
        if ($success) {
            Log::info('Conflict resolution succeeded', $logData);
        } else {
            Log::error('Conflict resolution failed', $logData);
        }
    }
}