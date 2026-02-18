<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Feature Flag Service
 * 
 * Provides comprehensive feature flag management including:
 * - Environment-based configuration
 * - Runtime feature toggling
 * - Percentage-based rollouts
 * - User-specific feature targeting
 * - Admin interface integration
 * - Real-time flag updates
 * - Feature analytics and reporting
 */
class FeatureFlagService
{
    private const CACHE_PREFIX = 'feature_flag_';
    private const CACHE_TTL = 300; // 5 minutes
    private const CONFIG_CACHE_KEY = 'feature_flags_config';
    
    private array $flags = [];
    private bool $initialized = false;
    
    /**
     * Initialize feature flags from configuration and cache
     */
    public function __construct()
    {
        $this->loadFeatureFlags();
    }
    
    /**
     * Check if a feature is enabled for the current context
     */
    public function isEnabled(string $flagName, ?int $userId = null, array $context = []): bool
    {
        try {
            if (!$this->initialized) {
                $this->loadFeatureFlags();
            }
            
            $flag = $this->getFlag($flagName);
            
            if (!$flag) {
                Log::warning("Feature flag not found: {$flagName}");
                return false;
            }
            
            // Check if flag is globally disabled
            if (!$flag['enabled']) {
                return false;
            }
            
            // Check environment restrictions
            if (!$this->checkEnvironmentAccess($flag)) {
                return false;
            }
            
            // Check percentage rollout
            if (!$this->checkPercentageRollout($flag, $userId, $context)) {
                return false;
            }
            
            // Check user-specific targeting
            if (!$this->checkUserTargeting($flag, $userId, $context)) {
                return false;
            }
            
            // Check custom conditions
            if (!$this->checkCustomConditions($flag, $userId, $context)) {
                return false;
            }
            
            // Log flag usage for analytics
            $this->logFeatureUsage($flagName, $userId, $context, true);
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Feature flag evaluation failed", [
                'flag' => $flagName,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get all available feature flags with their current states
     */
    public function getAllFlags(?int $userId = null, array $context = []): array
    {
        try {
            $results = [];
            
            foreach ($this->flags as $flagName => $flag) {
                $results[$flagName] = [
                    'name' => $flagName,
                    'enabled' => $this->isEnabled($flagName, $userId, $context),
                    'description' => $flag['description'] ?? '',
                    'category' => $flag['category'] ?? 'general',
                    'rollout_percentage' => $flag['rollout_percentage'] ?? 100,
                    'environments' => $flag['environments'] ?? ['*'],
                    'last_updated' => $flag['last_updated'] ?? null
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            Log::error("Failed to get all feature flags", [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Update a feature flag configuration
     */
    public function updateFlag(string $flagName, array $config): bool
    {
        try {
            $currentFlag = $this->getFlag($flagName);
            
            if (!$currentFlag) {
                Log::warning("Attempted to update non-existent flag: {$flagName}");
                return false;
            }
            
            // Merge with existing configuration
            $updatedFlag = array_merge($currentFlag, $config, [
                'last_updated' => now()->toISOString(),
                'updated_by' => auth()->id() ?? 'system'
            ]);
            
            // Validate configuration
            if (!$this->validateFlagConfiguration($updatedFlag)) {
                Log::error("Invalid feature flag configuration", [
                    'flag' => $flagName,
                    'config' => $config
                ]);
                return false;
            }
            
            // Update in memory
            $this->flags[$flagName] = $updatedFlag;
            
            // Update in cache
            $this->cacheFlag($flagName, $updatedFlag);
            
            // Log the change
            Log::info("Feature flag updated", [
                'flag' => $flagName,
                'updated_by' => auth()->id() ?? 'system',
                'changes' => array_keys($config)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Failed to update feature flag", [
                'flag' => $flagName,
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Create a new feature flag
     */
    public function createFlag(string $flagName, array $config): bool
    {
        try {
            if ($this->getFlag($flagName)) {
                Log::warning("Feature flag already exists: {$flagName}");
                return false;
            }
            
            $defaultConfig = [
                'enabled' => false,
                'description' => '',
                'category' => 'general',
                'environments' => ['*'],
                'rollout_percentage' => 0,
                'target_users' => [],
                'exclude_users' => [],
                'conditions' => [],
                'created_at' => now()->toISOString(),
                'created_by' => auth()->id() ?? 'system',
                'last_updated' => now()->toISOString()
            ];
            
            $flagConfig = array_merge($defaultConfig, $config);
            
            // Validate configuration
            if (!$this->validateFlagConfiguration($flagConfig)) {
                Log::error("Invalid feature flag configuration for creation", [
                    'flag' => $flagName,
                    'config' => $config
                ]);
                return false;
            }
            
            // Add to memory
            $this->flags[$flagName] = $flagConfig;
            
            // Cache the flag
            $this->cacheFlag($flagName, $flagConfig);
            
            Log::info("Feature flag created", [
                'flag' => $flagName,
                'created_by' => auth()->id() ?? 'system'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Failed to create feature flag", [
                'flag' => $flagName,
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Delete a feature flag
     */
    public function deleteFlag(string $flagName): bool
    {
        try {
            if (!$this->getFlag($flagName)) {
                Log::warning("Attempted to delete non-existent flag: {$flagName}");
                return false;
            }
            
            // Remove from memory
            unset($this->flags[$flagName]);
            
            // Remove from cache
            Cache::forget(self::CACHE_PREFIX . $flagName);
            
            Log::info("Feature flag deleted", [
                'flag' => $flagName,
                'deleted_by' => auth()->id() ?? 'system'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error("Failed to delete feature flag", [
                'flag' => $flagName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get feature flag analytics and usage statistics
     */
    public function getAnalytics(string $period = '24h'): array
    {
        try {
            $analyticsKey = "feature_flag_analytics_{$period}";
            
            return Cache::remember($analyticsKey, 300, function () use ($period) {
                return [
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                    'total_flags' => count($this->flags),
                    'enabled_flags' => count(array_filter($this->flags, fn($flag) => $flag['enabled'])),
                    'flag_usage' => $this->calculateFlagUsage($period),
                    'rollout_stats' => $this->calculateRolloutStats(),
                    'environment_distribution' => $this->calculateEnvironmentDistribution(),
                    'category_breakdown' => $this->calculateCategoryBreakdown()
                ];
            });
            
        } catch (Exception $e) {
            Log::error("Failed to generate feature flag analytics", [
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            
            return [
                'period' => $period,
                'error' => $e->getMessage(),
                'total_flags' => 0
            ];
        }
    }
    
    /**
     * Bulk update multiple feature flags
     */
    public function bulkUpdate(array $updates): array
    {
        $results = [];
        
        foreach ($updates as $flagName => $config) {
            $results[$flagName] = $this->updateFlag($flagName, $config);
        }
        
        Log::info("Bulk feature flag update completed", [
            'flags_updated' => count(array_filter($results)),
            'total_flags' => count($updates),
            'updated_by' => auth()->id() ?? 'system'
        ]);
        
        return $results;
    }
    
    /**
     * Private helper methods
     */
    private function loadFeatureFlags(): void
    {
        try {
            // Load from configuration file
            $configFlags = Config::get('features.flags', []);
            
            // Load cached flags (for runtime updates)
            $cachedFlags = Cache::get(self::CONFIG_CACHE_KEY, []);
            
            // Merge configuration and cached flags
            $this->flags = array_merge($configFlags, $cachedFlags);
            
            // Load individual cached flags
            foreach ($this->flags as $flagName => $flag) {
                $cachedFlag = Cache::get(self::CACHE_PREFIX . $flagName);
                if ($cachedFlag) {
                    $this->flags[$flagName] = $cachedFlag;
                }
            }
            
            $this->initialized = true;
            
        } catch (Exception $e) {
            Log::error("Failed to load feature flags", [
                'error' => $e->getMessage()
            ]);
            
            $this->flags = [];
            $this->initialized = true;
        }
    }
    
    private function getFlag(string $flagName): ?array
    {
        return $this->flags[$flagName] ?? null;
    }
    
    private function checkEnvironmentAccess(array $flag): bool
    {
        $environments = $flag['environments'] ?? ['*'];
        
        if (in_array('*', $environments)) {
            return true;
        }
        
        $currentEnv = app()->environment();
        return in_array($currentEnv, $environments);
    }
    
    private function checkPercentageRollout(array $flag, ?int $userId, array $context): bool
    {
        $percentage = $flag['rollout_percentage'] ?? 100;
        
        if ($percentage >= 100) {
            return true;
        }
        
        if ($percentage <= 0) {
            return false;
        }
        
        // Use user ID or IP for consistent rollout
        $identifier = $userId ?? ($context['ip_address'] ?? session()->getId());
        $hash = hexdec(substr(md5($identifier), 0, 8));
        
        return ($hash % 100) < $percentage;
    }
    
    private function checkUserTargeting(array $flag, ?int $userId, array $context): bool
    {
        if (!$userId) {
            return true; // No user targeting rules apply to anonymous users
        }
        
        $targetUsers = $flag['target_users'] ?? [];
        $excludeUsers = $flag['exclude_users'] ?? [];
        
        // Check if user is explicitly excluded
        if (in_array($userId, $excludeUsers)) {
            return false;
        }
        
        // Check if user is explicitly targeted
        if (!empty($targetUsers) && !in_array($userId, $targetUsers)) {
            return false;
        }
        
        return true;
    }
    
    private function checkCustomConditions(array $flag, ?int $userId, array $context): bool
    {
        $conditions = $flag['conditions'] ?? [];
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $userId, $context)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function evaluateCondition(array $condition, ?int $userId, array $context): bool
    {
        $type = $condition['type'] ?? '';
        
        switch ($type) {
            case 'user_attribute':
                return $this->evaluateUserAttributeCondition($condition, $userId);
            
            case 'context_value':
                return $this->evaluateContextCondition($condition, $context);
            
            case 'time_range':
                return $this->evaluateTimeRangeCondition($condition);
            
            default:
                return true;
        }
    }
    
    private function evaluateUserAttributeCondition(array $condition, ?int $userId): bool
    {
        // Placeholder - would evaluate user-specific conditions
        return true;
    }
    
    private function evaluateContextCondition(array $condition, array $context): bool
    {
        // Placeholder - would evaluate context-specific conditions
        return true;
    }
    
    private function evaluateTimeRangeCondition(array $condition): bool
    {
        // Placeholder - would evaluate time-based conditions
        return true;
    }
    
    private function validateFlagConfiguration(array $config): bool
    {
        // Basic validation
        if (!isset($config['enabled']) || !is_bool($config['enabled'])) {
            return false;
        }
        
        if (isset($config['rollout_percentage'])) {
            $percentage = $config['rollout_percentage'];
            if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
                return false;
            }
        }
        
        return true;
    }
    
    private function cacheFlag(string $flagName, array $flagConfig): void
    {
        Cache::put(self::CACHE_PREFIX . $flagName, $flagConfig, self::CACHE_TTL);
    }
    
    private function logFeatureUsage(string $flagName, ?int $userId, array $context, bool $enabled): void
    {
        // Log for analytics - in production, this might go to a dedicated analytics service
        Log::info("Feature flag accessed", [
            'flag' => $flagName,
            'user_id' => $userId,
            'enabled' => $enabled,
            'context' => $context,
            'timestamp' => now()->toISOString()
        ]);
    }
    
    private function calculateFlagUsage(string $period): array
    {
        // Placeholder - would calculate actual usage statistics
        return [
            'total_accesses' => 0,
            'enabled_accesses' => 0,
            'unique_users' => 0
        ];
    }
    
    private function calculateRolloutStats(): array
    {
        $stats = [
            'full_rollout' => 0,
            'partial_rollout' => 0,
            'disabled' => 0
        ];
        
        foreach ($this->flags as $flag) {
            if (!$flag['enabled']) {
                $stats['disabled']++;
            } elseif (($flag['rollout_percentage'] ?? 100) >= 100) {
                $stats['full_rollout']++;
            } else {
                $stats['partial_rollout']++;
            }
        }
        
        return $stats;
    }
    
    private function calculateEnvironmentDistribution(): array
    {
        $distribution = [];
        
        foreach ($this->flags as $flag) {
            $environments = $flag['environments'] ?? ['*'];
            foreach ($environments as $env) {
                $distribution[$env] = ($distribution[$env] ?? 0) + 1;
            }
        }
        
        return $distribution;
    }
    
    private function calculateCategoryBreakdown(): array
    {
        $breakdown = [];
        
        foreach ($this->flags as $flag) {
            $category = $flag['category'] ?? 'general';
            $breakdown[$category] = ($breakdown[$category] ?? 0) + 1;
        }
        
        return $breakdown;
    }
}