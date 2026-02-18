<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * EnhancedRackAnalysisService
 *
 * Orchestrates the enhanced analysis process with constitutional compliance validation.
 * Manages the complete workflow from triggering analysis to storing results.
 */
class EnhancedRackAnalysisService
{
    private NestedChainAnalyzer $chainAnalyzer;
    private ConstitutionalComplianceService $complianceService;

    public function __construct(
        NestedChainAnalyzer $chainAnalyzer,
        ConstitutionalComplianceService $complianceService
    ) {
        $this->chainAnalyzer = $chainAnalyzer;
        $this->complianceService = $complianceService;
    }

    /**
     * Perform complete enhanced analysis on a rack
     */
    public function analyzeRack(Rack $rack, bool $force = false): array
    {
        // Check if analysis is needed
        if (!$force && $this->isAnalysisUpToDate($rack)) {
            Log::info('Enhanced analysis skipped - already up to date', [
                'rack_uuid' => $rack->uuid
            ]);

            return $this->getExistingAnalysisResults($rack);
        }

        // Start analysis workflow
        $rack->startEnhancedAnalysis();

        Log::info('Starting enhanced rack analysis workflow', [
            'rack_uuid' => $rack->uuid,
            'force_reanalysis' => $force,
            'constitutional_requirement' => 'ALL CHAINS must be detected'
        ]);

        DB::beginTransaction();

        try {
            // Clear any existing enhanced analysis data
            if ($force) {
                $this->clearExistingAnalysis($rack);
            }

            // Perform nested chain analysis
            $analysisResults = $this->chainAnalyzer->analyzeRack($rack);

            if (!$analysisResults['analysis_complete']) {
                throw new Exception('Chain analysis failed: ' . ($analysisResults['error'] ?? 'Unknown error'));
            }

            // Store analysis results
            $enhancedAnalysis = $this->storeAnalysisResults($rack, $analysisResults);

            // Store detected chains
            $this->storeNestedChains($rack, $this->chainAnalyzer->getDetectedChains());

            // Complete the analysis workflow
            $rack->completeEnhancedAnalysis([
                'has_nested_chains' => $analysisResults['nested_chains_detected'] > 0,
                'total_chains_detected' => $analysisResults['nested_chains_detected'],
                'max_nesting_depth' => $analysisResults['max_nesting_depth']
            ]);

            // Validate constitutional compliance
            $complianceValidation = $this->complianceService->validateRackCompliance($rack);

            // Update compliance status if needed
            if ($complianceValidation['compliant'] !== $enhancedAnalysis->constitutional_compliant) {
                $enhancedAnalysis->update([
                    'constitutional_compliant' => $complianceValidation['compliant'],
                    'compliance_issues' => $complianceValidation['issues']
                ]);
            }

            DB::commit();

            // Clear analysis cache
            $this->clearAnalysisCache($rack);

            Log::info('Enhanced rack analysis completed successfully', [
                'rack_uuid' => $rack->uuid,
                'chains_detected' => $analysisResults['nested_chains_detected'],
                'constitutional_compliant' => $complianceValidation['compliant'],
                'duration_ms' => $analysisResults['analysis_duration_ms']
            ]);

            return array_merge($analysisResults, [
                'compliance_validation' => $complianceValidation,
                'analysis_id' => $enhancedAnalysis->id
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            $rack->failEnhancedAnalysis($e->getMessage());

            Log::error('Enhanced rack analysis failed', [
                'rack_uuid' => $rack->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'rack_uuid' => $rack->uuid,
                'analysis_complete' => false,
                'constitutional_compliant' => false,
                'error' => $e->getMessage(),
                'processed_at' => now()
            ];
        }
    }

    /**
     * Get nested chain hierarchy for a rack
     */
    public function getNestedChainHierarchy(Rack $rack, bool $includeDevices = false): array
    {
        $cacheKey = "rack_hierarchy_{$rack->uuid}_devices_" . ($includeDevices ? 'true' : 'false');

        return Cache::remember($cacheKey, 3600, function () use ($rack, $includeDevices) {
            if (!$rack->hasEnhancedAnalysis()) {
                return [
                    'rack_uuid' => $rack->uuid,
                    'total_chains' => 0,
                    'max_depth' => 0,
                    'root_chains' => []
                ];
            }

            $analysis = $rack->enhancedAnalysis;
            $hierarchy = $rack->getNestedChainHierarchy();

            // Add device information if requested
            if ($includeDevices) {
                $hierarchy = $this->enrichHierarchyWithDevices($hierarchy);
            }

            return [
                'rack_uuid' => $rack->uuid,
                'total_chains' => $analysis->total_chains_detected,
                'max_depth' => $analysis->max_nesting_depth,
                'root_chains' => $hierarchy,
                'constitutional_compliant' => $analysis->constitutional_compliant,
                'analysis_completed_at' => $analysis->processed_at->toISOString()
            ];
        });
    }

    /**
     * Get specific nested chain details
     */
    public function getNestedChainDetails(Rack $rack, int $chainId): ?array
    {
        $chain = NestedChain::where('rack_id', $rack->id)
            ->where('id', $chainId)
            ->with(['parentChain', 'childChains'])
            ->first();

        if (!$chain) {
            return null;
        }

        return [
            'id' => $chain->id,
            'chain_identifier' => $chain->chain_identifier,
            'xml_path' => $chain->xml_path,
            'depth_level' => $chain->depth_level,
            'device_count' => $chain->device_count,
            'is_empty' => $chain->is_empty,
            'chain_type' => $chain->chain_type,
            'devices' => $chain->devices,
            'parameters' => $chain->parameters,
            'chain_metadata' => $chain->chain_metadata,
            'parent_chain' => $chain->parentChain ? [
                'id' => $chain->parentChain->id,
                'chain_identifier' => $chain->parentChain->chain_identifier,
                'depth_level' => $chain->parentChain->depth_level
            ] : null,
            'child_chains' => $chain->childChains->map(function ($child) {
                return [
                    'id' => $child->id,
                    'chain_identifier' => $child->chain_identifier,
                    'depth_level' => $child->depth_level,
                    'device_count' => $child->device_count,
                    'chain_type' => $child->chain_type
                ];
            })->toArray(),
            'hierarchical_path' => $chain->getHierarchicalPath(),
            'constitutional_compliant' => $chain->isConstitutionalCompliant(),
            'analyzed_at' => $chain->analyzed_at->toISOString()
        ];
    }

    /**
     * Reanalyze rack with enhanced settings
     */
    public function reanalyzeRack(Rack $rack, array $options = []): array
    {
        $force = $options['force'] ?? true;

        Log::info('Reanalyzing rack with enhanced analysis', [
            'rack_uuid' => $rack->uuid,
            'options' => $options
        ]);

        return $this->analyzeRack($rack, $force);
    }

    /**
     * Get analysis summary for a rack
     */
    public function getAnalysisSummary(Rack $rack): array
    {
        if (!$rack->hasEnhancedAnalysis()) {
            return [
                'analyzed' => false,
                'constitutional_compliant' => false,
                'message' => 'Enhanced analysis not completed'
            ];
        }

        $analysis = $rack->enhancedAnalysis;
        $summary = $analysis->getSummary();

        return array_merge($summary, [
            'rack_uuid' => $rack->uuid,
            'analyzed' => true,
            'analysis_age_days' => $rack->getAnalysisAgeInDays(),
            'recent_analysis' => $rack->hasRecentEnhancedAnalysis(),
            'chain_hierarchy_available' => $analysis->has_nested_chains,
            'total_devices_including_chains' => $rack->total_device_count_including_chains
        ]);
    }

    /**
     * Check if analysis is up to date
     */
    private function isAnalysisUpToDate(Rack $rack): bool
    {
        if (!$rack->enhanced_analysis_complete || !$rack->enhancedAnalysis) {
            return false;
        }

        // Check if file was modified after analysis
        $fileModified = $rack->updated_at;
        $analysisCompleted = $rack->enhanced_analysis_completed_at;

        return $analysisCompleted && $analysisCompleted->isAfter($fileModified);
    }

    /**
     * Get existing analysis results
     */
    private function getExistingAnalysisResults(Rack $rack): array
    {
        $analysis = $rack->enhancedAnalysis;

        return [
            'rack_uuid' => $rack->uuid,
            'analysis_complete' => true,
            'constitutional_compliant' => $analysis->constitutional_compliant,
            'nested_chains_detected' => $analysis->total_chains_detected,
            'max_nesting_depth' => $analysis->max_nesting_depth,
            'total_devices' => $analysis->total_devices,
            'analysis_duration_ms' => $analysis->analysis_duration_ms,
            'processed_at' => $analysis->processed_at,
            'from_cache' => true
        ];
    }

    /**
     * Clear existing analysis data
     */
    private function clearExistingAnalysis(Rack $rack): void
    {
        // Clear nested chains
        NestedChain::where('rack_id', $rack->id)->delete();

        // Clear enhanced analysis
        EnhancedRackAnalysis::where('rack_id', $rack->id)->delete();

        // Clear cache
        $this->clearAnalysisCache($rack);
    }

    /**
     * Store analysis results in enhanced_rack_analysis table
     */
    private function storeAnalysisResults(Rack $rack, array $results): EnhancedRackAnalysis
    {
        return EnhancedRackAnalysis::create([
            'rack_id' => $rack->id,
            'constitutional_compliant' => $results['constitutional_compliant'],
            'compliance_issues' => $results['compliance_issues'] ?? [],
            'has_nested_chains' => $results['nested_chains_detected'] > 0,
            'total_chains_detected' => $results['nested_chains_detected'],
            'max_nesting_depth' => $results['max_nesting_depth'],
            'total_devices' => $results['total_devices'],
            'device_type_breakdown' => $results['device_type_breakdown'] ?? [],
            'analysis_duration_ms' => $results['analysis_duration_ms'],
            'analyzer_version' => $results['analyzer_version'],
            'analysis_metadata' => [
                'hierarchy_preview' => $results['hierarchy_preview'] ?? [],
                'analysis_timestamp' => $results['processed_at']->toISOString()
            ],
            'processed_at' => $results['processed_at']
        ]);
    }

    /**
     * Store detected nested chains
     */
    private function storeNestedChains(Rack $rack, array $detectedChains): void
    {
        // First pass: create all chains without parent relationships
        $chainIdMap = [];

        foreach ($detectedChains as $chainData) {
            $chain = NestedChain::create(array_merge($chainData, [
                'parent_chain_id' => null // Will be set in second pass
            ]));

            $chainIdMap[$chainData['chain_identifier']] = $chain->id;
        }

        // Second pass: establish parent-child relationships
        foreach ($detectedChains as $chainData) {
            if ($chainData['parent_chain_id'] && isset($chainIdMap[$chainData['parent_chain_id']])) {
                $chainId = $chainIdMap[$chainData['chain_identifier']];
                $parentId = $chainIdMap[$chainData['parent_chain_id']];

                NestedChain::where('id', $chainId)->update([
                    'parent_chain_id' => $parentId
                ]);
            }
        }
    }

    /**
     * Enrich hierarchy with device information
     */
    private function enrichHierarchyWithDevices(array $hierarchy): array
    {
        return array_map(function ($chain) {
            if (!empty($chain['child_chains'])) {
                $chain['child_chains'] = $this->enrichHierarchyWithDevices($chain['child_chains']);
            }

            // Add device details if requested
            if ($chain['device_count'] > 0 && !empty($chain['devices'])) {
                $chain['device_details'] = $chain['devices'];
            }

            return $chain;
        }, $hierarchy);
    }

    /**
     * Clear analysis cache for a rack
     */
    private function clearAnalysisCache(Rack $rack): void
    {
        $cacheKeys = [
            "rack_hierarchy_{$rack->uuid}_devices_true",
            "rack_hierarchy_{$rack->uuid}_devices_false",
            "rack_analysis_summary_{$rack->uuid}",
            "rack_compliance_status_{$rack->uuid}"
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get analysis statistics for multiple racks
     */
    public function getBulkAnalysisStatistics(array $rackIds): array
    {
        $statistics = EnhancedRackAnalysis::whereIn('rack_id', $rackIds)
            ->selectRaw('
                COUNT(*) as total_analyzed,
                AVG(analysis_duration_ms) as avg_duration_ms,
                AVG(total_chains_detected) as avg_chains,
                AVG(max_nesting_depth) as avg_depth,
                AVG(total_devices) as avg_devices,
                SUM(CASE WHEN constitutional_compliant = 1 THEN 1 ELSE 0 END) as compliant_count
            ')
            ->first();

        return [
            'total_analyzed' => (int) $statistics->total_analyzed,
            'average_duration_ms' => (int) $statistics->avg_duration_ms,
            'average_chains_detected' => round($statistics->avg_chains, 1),
            'average_max_depth' => round($statistics->avg_depth, 1),
            'average_devices' => round($statistics->avg_devices, 1),
            'constitutional_compliance_rate' => $statistics->total_analyzed > 0 ?
                round(($statistics->compliant_count / $statistics->total_analyzed) * 100, 1) : 0
        ];
    }

    /**
     * Check if rack needs reanalysis
     */
    public function needsReanalysis(Rack $rack, int $maxAgeDays = 30): bool
    {
        if (!$rack->enhanced_analysis_complete) {
            return true;
        }

        if (!$rack->hasRecentEnhancedAnalysis($maxAgeDays * 24)) {
            return true;
        }

        // Check if analysis was performed with older analyzer version
        $analysis = $rack->enhancedAnalysis;
        if ($analysis && version_compare($analysis->analyzer_version, NestedChainAnalyzer::class::ANALYZER_VERSION, '<')) {
            return true;
        }

        return false;
    }

    /**
     * Validate analysis results against constitutional requirements
     */
    public function validateAnalysisResults(array $results): array
    {
        $validation = [
            'valid' => true,
            'issues' => []
        ];

        // Constitutional requirement validations
        if (!isset($results['analysis_complete']) || !$results['analysis_complete']) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Analysis not completed';
        }

        if (!isset($results['constitutional_compliant'])) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Constitutional compliance status missing';
        }

        if (isset($results['analysis_duration_ms']) && $results['analysis_duration_ms'] > 5000) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Analysis duration exceeds constitutional 5-second limit';
        }

        if (!isset($results['nested_chains_detected']) || !is_numeric($results['nested_chains_detected'])) {
            $validation['valid'] = false;
            $validation['issues'][] = 'Invalid or missing chain detection count';
        }

        return $validation;
    }
}