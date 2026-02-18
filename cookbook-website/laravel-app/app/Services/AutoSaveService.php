<?php

namespace App\Services;

use App\Models\Rack;
use App\Services\OptimisticLockingService;
use App\Services\ConflictResolutionService;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\AutoSaveException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Auto-Save Service
 * 
 * Handles auto-save operations with concurrency control, conflict resolution,
 * and comprehensive error handling. Integrates with optimistic locking and
 * provides robust state management for real-time editing.
 */
class AutoSaveService
{
    public function __construct(
        private OptimisticLockingService $lockingService,
        private ConflictResolutionService $conflictService
    ) {}
    
    /**
     * Save field data with concurrency protection
     *
     * @param Rack $rack
     * @param string $field
     * @param mixed $value
     * @param array $options
     * @return array Save result
     * @throws AutoSaveException
     */
    public function saveField(Rack $rack, string $field, $value, array $options = []): array
    {
        $startTime = microtime(true);
        $sessionId = $options['session_id'] ?? $this->generateSessionId();
        $clientVersion = $options['version'] ?? null;
        
        try {
            // Validate field and value
            $this->validateFieldAndValue($rack, $field, $value);
            
            // Check for active editing sessions
            $this->checkActiveEditingSessions($rack, $sessionId, $field);
            
            // Prepare save data
            $saveData = $this->prepareSaveData($field, $value);
            
            // Attempt save with optimistic locking
            $result = $this->performOptimisticSave($rack, $saveData, $clientVersion, $sessionId);
            
            // Update session tracking
            $this->updateSessionTracking($rack, $sessionId, $field, $value);
            
            // Log successful save
            $this->logSaveSuccess($rack, $field, microtime(true) - $startTime, $result['version']);
            
            return [
                'success' => true,
                'version' => $result['version'],
                'timestamp' => $result['timestamp'],
                'field' => $field,
                'session_id' => $sessionId,
                'save_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'analysis_status' => $this->getAnalysisStatus($rack),
                'conflicts' => []
            ];
            
        } catch (ConcurrencyConflictException $e) {
            return $this->handleConcurrencyConflict($rack, $field, $value, $e, $sessionId);
            
        } catch (\Exception $e) {
            $this->logSaveError($rack, $field, $e, microtime(true) - $startTime);
            
            throw new AutoSaveException(
                "Auto-save failed for field '{$field}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Save multiple fields in a single transaction
     *
     * @param Rack $rack
     * @param array $fieldData
     * @param array $options
     * @return array Save result
     */
    public function saveMultipleFields(Rack $rack, array $fieldData, array $options = []): array
    {
        $startTime = microtime(true);
        $sessionId = $options['session_id'] ?? $this->generateSessionId();
        $clientVersion = $options['version'] ?? null;
        
        try {
            return DB::transaction(function () use ($rack, $fieldData, $clientVersion, $sessionId, $startTime) {
                // Validate all fields
                foreach ($fieldData as $field => $value) {
                    $this->validateFieldAndValue($rack, $field, $value);
                }
                
                // Prepare save data
                $saveData = [];
                foreach ($fieldData as $field => $value) {
                    $saveData = array_merge($saveData, $this->prepareSaveData($field, $value));
                }
                
                // Perform optimistic save
                $result = $this->performOptimisticSave($rack, $saveData, $clientVersion, $sessionId);
                
                // Update session tracking for all fields
                foreach ($fieldData as $field => $value) {
                    $this->updateSessionTracking($rack, $sessionId, $field, $value);
                }
                
                $this->logMultipleSaveSuccess($rack, array_keys($fieldData), microtime(true) - $startTime, $result['version']);
                
                return [
                    'success' => true,
                    'version' => $result['version'],
                    'timestamp' => $result['timestamp'],
                    'fields' => array_keys($fieldData),
                    'session_id' => $sessionId,
                    'save_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'analysis_status' => $this->getAnalysisStatus($rack),
                    'conflicts' => []
                ];
            });
            
        } catch (ConcurrencyConflictException $e) {
            // For multiple fields, we need more sophisticated conflict handling
            return $this->handleMultipleFieldConflict($rack, $fieldData, $e, $sessionId);
            
        } catch (\Exception $e) {
            $this->logSaveError($rack, 'multiple_fields', $e, microtime(true) - $startTime);
            
            throw new AutoSaveException(
                "Multi-field auto-save failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Get current save state for client synchronization
     *
     * @param Rack $rack
     * @param string $sessionId
     * @return array Current state
     */
    public function getCurrentState(Rack $rack, string $sessionId): array
    {
        $freshRack = $rack->fresh();
        
        return [
            'version' => $freshRack->version ?? 0,
            'last_modified' => $freshRack->updated_at,
            'analysis_status' => $this->getAnalysisStatus($freshRack),
            'active_sessions' => $this->getActiveEditingSessions($rack),
            'pending_conflicts' => $this->getPendingConflicts($rack, $sessionId),
            'fields' => $this->getCurrentFieldValues($freshRack)
        ];
    }
    
    /**
     * Handle connection recovery after network issues
     *
     * @param Rack $rack
     * @param string $sessionId
     * @param array $clientState
     * @return array Recovery result
     */
    public function handleConnectionRecovery(Rack $rack, string $sessionId, array $clientState): array
    {
        $currentState = $this->getCurrentState($rack, $sessionId);
        $clientVersion = $clientState['version'] ?? 0;
        
        if ($currentState['version'] === $clientVersion) {
            return [
                'recovery_needed' => false,
                'current_state' => $currentState
            ];
        }
        
        // Detect what changed while client was offline
        $changes = $this->detectChangesSinceVersion($rack, $clientVersion);
        
        return [
            'recovery_needed' => true,
            'current_state' => $currentState,
            'missed_changes' => $changes,
            'suggested_action' => $this->suggestRecoveryAction($changes, $clientState)
        ];
    }
    
    /**
     * Clean up expired sessions and temporary data
     *
     * @param Rack $rack
     * @return array Cleanup result
     */
    public function cleanupExpiredSessions(Rack $rack): array
    {
        $sessionKey = $this->getSessionCacheKey($rack);
        $sessions = Cache::get($sessionKey, []);
        $now = Carbon::now();
        $expiredSessions = [];
        
        foreach ($sessions as $sessionId => $data) {
            $lastActivity = Carbon::parse($data['last_activity']);
            
            if ($now->diffInMinutes($lastActivity) > 30) { // 30 minute timeout
                $expiredSessions[] = $sessionId;
                unset($sessions[$sessionId]);
            }
        }
        
        Cache::put($sessionKey, $sessions, 3600); // 1 hour cache
        
        return [
            'expired_sessions' => $expiredSessions,
            'active_sessions' => count($sessions),
            'cleanup_time' => $now
        ];
    }
    
    /**
     * Validate field and value
     */
    private function validateFieldAndValue(Rack $rack, string $field, $value): void
    {
        $allowedFields = ['title', 'description', 'category', 'tags', 'how_to_article'];
        
        if (!in_array($field, $allowedFields)) {
            throw new AutoSaveException("Field '{$field}' is not allowed for auto-save");
        }
        
        $maxLengths = [
            'title' => 255,
            'description' => 1000,
            'category' => 100,
            'tags' => 500,
            'how_to_article' => 10000
        ];
        
        if (is_string($value) && isset($maxLengths[$field]) && strlen($value) > $maxLengths[$field]) {
            throw new AutoSaveException(
                "Field '{$field}' exceeds maximum length of {$maxLengths[$field]} characters"
            );
        }
    }
    
    /**
     * Prepare save data based on field type
     */
    private function prepareSaveData(string $field, $value): array
    {
        if ($field === 'tags') {
            // Tags are handled specially - we'll return them as-is for now
            // The controller will handle the tag relationship updates
            return ['tags' => $value];
        }
        
        return [$field => $value];
    }
    
    /**
     * Perform optimistic save with locking
     */
    private function performOptimisticSave(Rack $rack, array $saveData, ?int $clientVersion, string $sessionId): array
    {
        // Special handling for tags
        if (isset($saveData['tags'])) {
            $this->handleTagsSave($rack, $saveData['tags']);
            unset($saveData['tags']);
        }
        
        // If no other data to save, return current state
        if (empty($saveData)) {
            return [
                'model' => $rack->fresh(),
                'version' => $rack->fresh()->version ?? 0,
                'timestamp' => $rack->fresh()->updated_at
            ];
        }
        
        return $this->lockingService->updateWithVersion($rack, $saveData, $clientVersion);
    }
    
    /**
     * Handle tags save separately due to relationship complexity
     */
    private function handleTagsSave(Rack $rack, ?string $tagString): void
    {
        $rack->tags()->detach();
        
        if (!empty($tagString)) {
            $tagNames = array_filter(array_map('trim', explode(',', $tagString)));
            
            foreach ($tagNames as $tagName) {
                if (strlen($tagName) > 2) {
                    $tag = \App\Models\Tag::firstOrCreate([
                        'name' => $tagName,
                        'slug' => \Illuminate\Support\Str::slug($tagName)
                    ]);
                    
                    $rack->tags()->attach($tag->id);
                }
            }
        }
    }
    
    /**
     * Handle concurrency conflict
     */
    private function handleConcurrencyConflict(
        Rack $rack, 
        string $field, 
        $value, 
        ConcurrencyConflictException $e, 
        string $sessionId
    ): array {
        $conflicts = $this->lockingService->detectConflicts($rack, [$field => $value]);
        
        // Store conflict for user resolution
        $this->storeConflictForResolution($rack, $sessionId, $conflicts);
        
        return [
            'success' => false,
            'conflict_detected' => true,
            'conflicts' => $conflicts,
            'current_version' => $e->getCurrentVersion(),
            'expected_version' => $e->getExpectedVersion(),
            'resolution_required' => true,
            'session_id' => $sessionId,
            'field' => $field,
            'user_value' => $value
        ];
    }
    
    /**
     * Handle multiple field conflict
     */
    private function handleMultipleFieldConflict(
        Rack $rack, 
        array $fieldData, 
        ConcurrencyConflictException $e, 
        string $sessionId
    ): array {
        $conflicts = $this->lockingService->detectConflicts($rack, $fieldData);
        
        $this->storeConflictForResolution($rack, $sessionId, $conflicts);
        
        return [
            'success' => false,
            'conflict_detected' => true,
            'conflicts' => $conflicts,
            'current_version' => $e->getCurrentVersion(),
            'expected_version' => $e->getExpectedVersion(),
            'resolution_required' => true,
            'session_id' => $sessionId,
            'fields' => array_keys($fieldData),
            'user_values' => $fieldData
        ];
    }
    
    /**
     * Check for active editing sessions
     */
    private function checkActiveEditingSessions(Rack $rack, string $currentSessionId, string $field): void
    {
        $sessionKey = $this->getSessionCacheKey($rack);
        $sessions = Cache::get($sessionKey, []);
        
        foreach ($sessions as $sessionId => $data) {
            if ($sessionId === $currentSessionId) continue;
            
            $lastActivity = Carbon::parse($data['last_activity']);
            $isRecent = Carbon::now()->diffInMinutes($lastActivity) < 5;
            
            if ($isRecent && in_array($field, $data['active_fields'] ?? [])) {
                Log::info('Concurrent editing detected', [
                    'rack_id' => $rack->id,
                    'field' => $field,
                    'sessions' => array_keys($sessions)
                ]);
            }
        }
    }
    
    /**
     * Update session tracking
     */
    private function updateSessionTracking(Rack $rack, string $sessionId, string $field, $value): void
    {
        $sessionKey = $this->getSessionCacheKey($rack);
        $sessions = Cache::get($sessionKey, []);
        
        $sessions[$sessionId] = [
            'last_activity' => Carbon::now(),
            'active_fields' => array_unique(array_merge(
                $sessions[$sessionId]['active_fields'] ?? [],
                [$field]
            )),
            'last_values' => array_merge(
                $sessions[$sessionId]['last_values'] ?? [],
                [$field => $value]
            )
        ];
        
        Cache::put($sessionKey, $sessions, 3600); // 1 hour cache
    }
    
    /**
     * Store conflict for user resolution
     */
    private function storeConflictForResolution(Rack $rack, string $sessionId, array $conflicts): void
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        
        Cache::put($conflictKey, [
            'conflicts' => $conflicts,
            'timestamp' => Carbon::now(),
            'resolved' => false
        ], 1800); // 30 minute cache
    }
    
    /**
     * Get active editing sessions
     */
    private function getActiveEditingSessions(Rack $rack): array
    {
        $sessionKey = $this->getSessionCacheKey($rack);
        return Cache::get($sessionKey, []);
    }
    
    /**
     * Get pending conflicts for session
     */
    private function getPendingConflicts(Rack $rack, string $sessionId): array
    {
        $conflictKey = $this->getConflictCacheKey($rack, $sessionId);
        $conflictData = Cache::get($conflictKey, null);
        
        return $conflictData && !$conflictData['resolved'] ? $conflictData['conflicts'] : [];
    }
    
    /**
     * Get current field values
     */
    private function getCurrentFieldValues(Rack $rack): array
    {
        return [
            'title' => $rack->title,
            'description' => $rack->description,
            'category' => $rack->category,
            'tags' => $rack->tags->pluck('name')->join(', '),
            'how_to_article' => $rack->how_to_article
        ];
    }
    
    /**
     * Detect changes since version
     */
    private function detectChangesSinceVersion(Rack $rack, int $sinceVersion): array
    {
        // This is a simplified implementation
        // In production, you might want to track change history
        return [
            'version_gap' => ($rack->version ?? 0) - $sinceVersion,
            'last_modified' => $rack->updated_at,
            'potentially_changed_fields' => ['title', 'description', 'category', 'tags', 'how_to_article']
        ];
    }
    
    /**
     * Suggest recovery action
     */
    private function suggestRecoveryAction(array $changes, array $clientState): string
    {
        if ($changes['version_gap'] > 3) {
            return 'reload_required';
        }
        
        return 'sync_recommended';
    }
    
    /**
     * Get analysis status
     */
    private function getAnalysisStatus(Rack $rack): array
    {
        return [
            'status' => $rack->status,
            'is_complete' => in_array($rack->status, ['pending', 'approved', 'failed']),
            'has_error' => $rack->status === 'failed',
            'error_message' => $rack->processing_error
        ];
    }
    
    /**
     * Generate session ID
     */
    private function generateSessionId(): string
    {
        return 'autosave_' . uniqid() . '_' . time();
    }
    
    /**
     * Get session cache key
     */
    private function getSessionCacheKey(Rack $rack): string
    {
        return "rack_editing_sessions_{$rack->id}";
    }
    
    /**
     * Get conflict cache key
     */
    private function getConflictCacheKey(Rack $rack, string $sessionId): string
    {
        return "rack_conflicts_{$rack->id}_{$sessionId}";
    }
    
    /**
     * Log successful save
     */
    private function logSaveSuccess(Rack $rack, string $field, float $duration, int $version): void
    {
        Log::info('Auto-save succeeded', [
            'rack_id' => $rack->id,
            'field' => $field,
            'duration_ms' => round($duration * 1000, 2),
            'new_version' => $version
        ]);
    }
    
    /**
     * Log multiple field save success
     */
    private function logMultipleSaveSuccess(Rack $rack, array $fields, float $duration, int $version): void
    {
        Log::info('Multi-field auto-save succeeded', [
            'rack_id' => $rack->id,
            'fields' => $fields,
            'field_count' => count($fields),
            'duration_ms' => round($duration * 1000, 2),
            'new_version' => $version
        ]);
    }
    
    /**
     * Log save error
     */
    private function logSaveError(Rack $rack, string $field, \Exception $e, float $duration): void
    {
        Log::error('Auto-save failed', [
            'rack_id' => $rack->id,
            'field' => $field,
            'error' => $e->getMessage(),
            'duration_ms' => round($duration * 1000, 2)
        ]);
    }
}