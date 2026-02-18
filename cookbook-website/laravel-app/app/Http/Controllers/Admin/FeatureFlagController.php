<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Feature Flag Admin Controller
 * 
 * Provides admin interface for managing feature flags including:
 * - Viewing all feature flags
 * - Creating new feature flags
 * - Updating existing flags
 * - Bulk operations
 * - Analytics and reporting
 */
class FeatureFlagController extends Controller
{
    private FeatureFlagService $featureFlagService;
    
    public function __construct(FeatureFlagService $featureFlagService)
    {
        $this->featureFlagService = $featureFlagService;
        $this->middleware(['auth', 'admin']);
    }
    
    /**
     * Display feature flags management interface
     */
    public function index(): View
    {
        $flags = $this->featureFlagService->getAllFlags();
        $analytics = $this->featureFlagService->getAnalytics();
        $categories = config('features.categories', []);
        
        return view('admin.feature-flags.index', compact('flags', 'analytics', 'categories'));
    }
    
    /**
     * Get all feature flags as JSON
     */
    public function api(): JsonResponse
    {
        try {
            $flags = $this->featureFlagService->getAllFlags();
            $analytics = $this->featureFlagService->getAnalytics();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'flags' => $flags,
                    'analytics' => $analytics,
                    'categories' => config('features.categories', [])
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feature flags', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve feature flags'
            ], 500);
        }
    }
    
    /**
     * Create a new feature flag
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|alpha_dash',
            'description' => 'required|string|max:500',
            'category' => 'required|string|max:100',
            'enabled' => 'boolean',
            'environments' => 'array',
            'environments.*' => 'string|in:production,staging,local,testing',
            'rollout_percentage' => 'integer|min:0|max:100',
            'target_users' => 'array',
            'target_users.*' => 'integer',
            'exclude_users' => 'array', 
            'exclude_users.*' => 'integer'
        ]);
        
        try {
            $flagData = [
                'enabled' => $request->boolean('enabled', false),
                'description' => $request->get('description'),
                'category' => $request->get('category'),
                'environments' => $request->get('environments', ['*']),
                'rollout_percentage' => $request->get('rollout_percentage', 0),
                'target_users' => $request->get('target_users', []),
                'exclude_users' => $request->get('exclude_users', []),
                'conditions' => $request->get('conditions', [])
            ];
            
            $success = $this->featureFlagService->createFlag($request->get('name'), $flagData);
            
            if ($success) {
                Log::info('Feature flag created via admin interface', [
                    'flag_name' => $request->get('name'),
                    'created_by' => auth()->id()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Feature flag created successfully',
                    'flag' => $this->featureFlagService->getAllFlags()[strtolower($request->get('name'))] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to create feature flag'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to create feature flag', [
                'flag_name' => $request->get('name'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to create feature flag: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update an existing feature flag
     */
    public function update(Request $request, string $flagName): JsonResponse
    {
        $request->validate([
            'description' => 'sometimes|string|max:500',
            'category' => 'sometimes|string|max:100',
            'enabled' => 'sometimes|boolean',
            'environments' => 'sometimes|array',
            'environments.*' => 'string|in:production,staging,local,testing',
            'rollout_percentage' => 'sometimes|integer|min:0|max:100',
            'target_users' => 'sometimes|array',
            'target_users.*' => 'integer',
            'exclude_users' => 'sometimes|array',
            'exclude_users.*' => 'integer'
        ]);
        
        try {
            $updateData = [];
            
            if ($request->has('enabled')) {
                $updateData['enabled'] = $request->boolean('enabled');
            }
            
            if ($request->has('description')) {
                $updateData['description'] = $request->get('description');
            }
            
            if ($request->has('category')) {
                $updateData['category'] = $request->get('category');
            }
            
            if ($request->has('environments')) {
                $updateData['environments'] = $request->get('environments');
            }
            
            if ($request->has('rollout_percentage')) {
                $updateData['rollout_percentage'] = $request->get('rollout_percentage');
            }
            
            if ($request->has('target_users')) {
                $updateData['target_users'] = $request->get('target_users');
            }
            
            if ($request->has('exclude_users')) {
                $updateData['exclude_users'] = $request->get('exclude_users');
            }
            
            if ($request->has('conditions')) {
                $updateData['conditions'] = $request->get('conditions');
            }
            
            $success = $this->featureFlagService->updateFlag($flagName, $updateData);
            
            if ($success) {
                Log::info('Feature flag updated via admin interface', [
                    'flag_name' => $flagName,
                    'updated_by' => auth()->id(),
                    'changes' => array_keys($updateData)
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Feature flag updated successfully',
                    'flag' => $this->featureFlagService->getAllFlags()[$flagName] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update feature flag'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to update feature flag', [
                'flag_name' => $flagName,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to update feature flag: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a feature flag
     */
    public function destroy(string $flagName): JsonResponse
    {
        try {
            $success = $this->featureFlagService->deleteFlag($flagName);
            
            if ($success) {
                Log::info('Feature flag deleted via admin interface', [
                    'flag_name' => $flagName,
                    'deleted_by' => auth()->id()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Feature flag deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete feature flag'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to delete feature flag', [
                'flag_name' => $flagName,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete feature flag: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Bulk update multiple feature flags
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => 'required|array',
            'updates.*' => 'array'
        ]);
        
        try {
            $updates = $request->get('updates');
            $results = $this->featureFlagService->bulkUpdate($updates);
            
            $successCount = count(array_filter($results));
            $totalCount = count($results);
            
            Log::info('Bulk feature flag update completed', [
                'total_flags' => $totalCount,
                'successful_updates' => $successCount,
                'updated_by' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$successCount} of {$totalCount} feature flags",
                'results' => $results,
                'summary' => [
                    'total' => $totalCount,
                    'successful' => $successCount,
                    'failed' => $totalCount - $successCount
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to perform bulk update', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to perform bulk update: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get feature flag analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->get('period', '24h');
        
        // Validate period
        $allowedPeriods = ['1h', '6h', '24h', '7d', '30d'];
        if (!in_array($period, $allowedPeriods)) {
            $period = '24h';
        }
        
        try {
            $analytics = $this->featureFlagService->getAnalytics($period);
            
            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve feature flag analytics', [
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve analytics'
            ], 500);
        }
    }
    
    /**
     * Toggle a feature flag on/off quickly
     */
    public function toggle(string $flagName): JsonResponse
    {
        try {
            $flags = $this->featureFlagService->getAllFlags();
            $currentFlag = $flags[$flagName] ?? null;
            
            if (!$currentFlag) {
                return response()->json([
                    'success' => false,
                    'error' => 'Feature flag not found'
                ], 404);
            }
            
            $newState = !$currentFlag['enabled'];
            
            $success = $this->featureFlagService->updateFlag($flagName, [
                'enabled' => $newState
            ]);
            
            if ($success) {
                Log::info('Feature flag toggled via admin interface', [
                    'flag_name' => $flagName,
                    'new_state' => $newState,
                    'toggled_by' => auth()->id()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => "Feature flag " . ($newState ? 'enabled' : 'disabled'),
                    'new_state' => $newState,
                    'flag' => $this->featureFlagService->getAllFlags()[$flagName] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to toggle feature flag'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to toggle feature flag', [
                'flag_name' => $flagName,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to toggle feature flag: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export feature flags configuration
     */
    public function export(): JsonResponse
    {
        try {
            $flags = $this->featureFlagService->getAllFlags();
            $analytics = $this->featureFlagService->getAnalytics('30d');
            
            $exportData = [
                'exported_at' => now()->toISOString(),
                'exported_by' => auth()->id(),
                'version' => '1.0',
                'flags' => $flags,
                'analytics_summary' => $analytics,
                'categories' => config('features.categories', [])
            ];
            
            Log::info('Feature flags configuration exported', [
                'exported_by' => auth()->id(),
                'flag_count' => count($flags)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to export feature flags', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to export feature flags: ' . $e->getMessage()
            ], 500);
        }
    }
}