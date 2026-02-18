<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * ConstitutionalComplianceService
 *
 * Validates and reports on constitutional compliance across the platform.
 * Enforces the constitutional requirement that "ALL CHAINS must be detected and included in analysis."
 */
class ConstitutionalComplianceService
{
    private const CONSTITUTIONAL_VERSION = '1.1.0';
    private const MAX_ANALYSIS_DURATION_MS = 5000;
    private const COMPLIANCE_CACHE_TTL = 3600; // 1 hour

    /**
     * Validate constitutional compliance for a specific rack
     */
    public function validateRackCompliance(Rack $rack): array
    {
        $cacheKey = "rack_compliance_{$rack->uuid}";

        return Cache::remember($cacheKey, self::COMPLIANCE_CACHE_TTL, function () use ($rack) {
            Log::info('Validating constitutional compliance for rack', [
                'rack_uuid' => $rack->uuid,
                'constitutional_version' => self::CONSTITUTIONAL_VERSION
            ]);

            $compliance = [
                'compliant' => true,
                'issues' => [],
                'validation_timestamp' => now(),
                'constitutional_version' => self::CONSTITUTIONAL_VERSION
            ];

            // Check if enhanced analysis exists
            if (!$rack->hasEnhancedAnalysis()) {
                $compliance['compliant'] = false;
                $compliance['issues'][] = 'Enhanced analysis not completed - constitutional requirement violated';
                return $compliance;
            }

            $analysis = $rack->enhancedAnalysis;

            // Constitutional Requirement 1: ALL CHAINS must be detected
            $chainCompliance = $this->validateAllChainsDetected($rack, $analysis);
            if (!$chainCompliance['compliant']) {
                $compliance['compliant'] = false;
                $compliance['issues'] = array_merge($compliance['issues'], $chainCompliance['issues']);
            }

            // Constitutional Requirement 2: Analysis must complete within 5 seconds
            $performanceCompliance = $this->validatePerformanceRequirements($analysis);
            if (!$performanceCompliance['compliant']) {
                $compliance['compliant'] = false;
                $compliance['issues'] = array_merge($compliance['issues'], $performanceCompliance['issues']);
            }

            // Constitutional Requirement 3: Analysis completeness and accuracy
            $completenessCompliance = $this->validateAnalysisCompleteness($rack, $analysis);
            if (!$completenessCompliance['compliant']) {
                $compliance['compliant'] = false;
                $compliance['issues'] = array_merge($compliance['issues'], $completenessCompliance['issues']);
            }

            Log::info('Constitutional compliance validation completed', [
                'rack_uuid' => $rack->uuid,
                'compliant' => $compliance['compliant'],
                'issues_count' => count($compliance['issues'])
            ]);

            return $compliance;
        });
    }

    /**
     * Validate that ALL CHAINS have been detected (constitutional requirement)
     */
    private function validateAllChainsDetected(Rack $rack, EnhancedRackAnalysis $analysis): array
    {
        $compliance = ['compliant' => true, 'issues' => []];

        // Check if any chains were detected
        $detectedChains = NestedChain::where('rack_id', $rack->id)->get();
        $chainCount = $detectedChains->count();

        // Validate chain detection completeness
        if ($analysis->has_nested_chains && $chainCount === 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Analysis indicates nested chains exist but none were stored - constitutional violation';
        }

        if ($analysis->total_chains_detected !== $chainCount) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = "Chain count mismatch: analysis reports {$analysis->total_chains_detected} but {$chainCount} stored";
        }

        // Validate individual chain completeness
        foreach ($detectedChains as $chain) {
            if (!$chain->isConstitutionalCompliant()) {
                $compliance['compliant'] = false;
                $compliance['issues'][] = "Chain {$chain->chain_identifier} fails constitutional compliance";
            }

            // Check for missing required data
            if (empty($chain->xml_path) || empty($chain->chain_identifier)) {
                $compliance['compliant'] = false;
                $compliance['issues'][] = "Chain {$chain->chain_identifier} missing required identification data";
            }
        }

        // Validate hierarchical integrity
        $hierarchyValidation = $this->validateChainHierarchy($detectedChains);
        if (!$hierarchyValidation['valid']) {
            $compliance['compliant'] = false;
            $compliance['issues'] = array_merge($compliance['issues'], $hierarchyValidation['issues']);
        }

        return $compliance;
    }

    /**
     * Validate performance requirements (constitutional 5-second limit)
     */
    private function validatePerformanceRequirements(EnhancedRackAnalysis $analysis): array
    {
        $compliance = ['compliant' => true, 'issues' => []];

        // Constitutional requirement: analysis must complete within 5 seconds
        if ($analysis->analysis_duration_ms > self::MAX_ANALYSIS_DURATION_MS) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = "Analysis duration ({$analysis->analysis_duration_ms}ms) exceeds constitutional limit (" . self::MAX_ANALYSIS_DURATION_MS . "ms)";
        }

        // Validate analysis was actually completed
        if (!$analysis->processed_at) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Analysis completion timestamp missing';
        }

        return $compliance;
    }

    /**
     * Validate analysis completeness and accuracy
     */
    private function validateAnalysisCompleteness(Rack $rack, EnhancedRackAnalysis $analysis): array
    {
        $compliance = ['compliant' => true, 'issues' => []];

        // Check for required analysis fields
        if ($analysis->total_chains_detected < 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Invalid chain count: cannot be negative';
        }

        if ($analysis->max_nesting_depth < 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Invalid nesting depth: cannot be negative';
        }

        if ($analysis->total_devices < 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Invalid device count: cannot be negative';
        }

        // Validate logical consistency
        if ($analysis->has_nested_chains && $analysis->total_chains_detected === 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Logical inconsistency: has_nested_chains is true but total_chains_detected is 0';
        }

        if (!$analysis->has_nested_chains && $analysis->total_chains_detected > 0) {
            $compliance['compliant'] = false;
            $compliance['issues'][] = 'Logical inconsistency: has_nested_chains is false but chains were detected';
        }

        return $compliance;
    }

    /**
     * Validate chain hierarchy integrity
     */
    private function validateChainHierarchy(Collection $chains): array
    {
        $validation = ['valid' => true, 'issues' => []];

        // Group chains by depth level
        $chainsByDepth = $chains->groupBy('depth_level');

        // Validate depth progression
        $maxDepth = $chainsByDepth->keys()->max();
        for ($depth = 0; $depth <= $maxDepth; $depth++) {
            if (!$chainsByDepth->has($depth)) {
                $validation['valid'] = false;
                $validation['issues'][] = "Missing chains at depth level {$depth} - hierarchy gap detected";
            }
        }

        // Validate parent-child relationships
        foreach ($chains as $chain) {
            if ($chain->parent_chain_id) {
                $parent = $chains->firstWhere('id', $chain->parent_chain_id);
                if (!$parent) {
                    $validation['valid'] = false;
                    $validation['issues'][] = "Chain {$chain->chain_identifier} references non-existent parent";
                } elseif ($parent->depth_level >= $chain->depth_level) {
                    $validation['valid'] = false;
                    $validation['issues'][] = "Chain {$chain->chain_identifier} has invalid depth relative to parent";
                }
            }
        }

        // Check for circular references
        $circularCheck = $this->detectCircularReferences($chains);
        if (!$circularCheck['valid']) {
            $validation['valid'] = false;
            $validation['issues'] = array_merge($validation['issues'], $circularCheck['issues']);
        }

        return $validation;
    }

    /**
     * Detect circular references in chain hierarchy
     */
    private function detectCircularReferences(Collection $chains): array
    {
        $validation = ['valid' => true, 'issues' => []];

        foreach ($chains as $chain) {
            $visited = [];
            $current = $chain;

            while ($current && $current->parent_chain_id) {
                if (in_array($current->id, $visited)) {
                    $validation['valid'] = false;
                    $validation['issues'][] = "Circular reference detected in chain hierarchy starting from {$chain->chain_identifier}";
                    break;
                }

                $visited[] = $current->id;
                $current = $chains->firstWhere('id', $current->parent_chain_id);
            }
        }

        return $validation;
    }

    /**
     * Generate platform-wide constitutional compliance report
     */
    public function generateComplianceReport(): array
    {
        $cacheKey = 'platform_constitutional_compliance_report';

        return Cache::remember($cacheKey, self::COMPLIANCE_CACHE_TTL, function () {
            Log::info('Generating platform-wide constitutional compliance report');

            $totalRacks = Rack::count();
            $analyzedRacks = Rack::enhancedAnalysisComplete()->count();

            $complianceStats = EnhancedRackAnalysis::getComplianceStatistics();

            // Detailed compliance breakdown
            $complianceBreakdown = $this->getDetailedComplianceBreakdown();

            // Performance statistics
            $performanceStats = $this->getPerformanceStatistics();

            // Recent trends
            $recentTrends = $this->getComplianceTrends();

            return [
                'report_generated_at' => now(),
                'constitutional_version' => self::CONSTITUTIONAL_VERSION,
                'platform_summary' => [
                    'total_racks' => $totalRacks,
                    'analyzed_racks' => $analyzedRacks,
                    'analysis_coverage_percentage' => $totalRacks > 0 ? round(($analyzedRacks / $totalRacks) * 100, 1) : 0,
                    'overall_compliance_rate' => $complianceStats['compliance_percentage']
                ],
                'compliance_statistics' => $complianceStats,
                'compliance_breakdown' => $complianceBreakdown,
                'performance_statistics' => $performanceStats,
                'recent_trends' => $recentTrends,
                'constitutional_requirements' => [
                    'all_chains_detected' => 'ALL CHAINS within uploaded rack files MUST be detected and included in analysis',
                    'performance_limit' => 'Analysis must complete within 5 seconds (5000ms)',
                    'completeness_requirement' => 'Analysis must be comprehensive and accurate'
                ]
            ];
        });
    }

    /**
     * Get detailed compliance breakdown
     */
    private function getDetailedComplianceBreakdown(): array
    {
        $analyses = EnhancedRackAnalysis::with('rack')->get();

        $breakdown = [
            'compliant_racks' => 0,
            'non_compliant_racks' => 0,
            'common_issues' => [],
            'performance_violations' => 0,
            'chain_detection_issues' => 0,
            'completeness_issues' => 0
        ];

        $issueFrequency = [];

        foreach ($analyses as $analysis) {
            if ($analysis->constitutional_compliant) {
                $breakdown['compliant_racks']++;
            } else {
                $breakdown['non_compliant_racks']++;

                // Categorize issues
                $complianceCheck = $this->validateRackCompliance($analysis->rack);
                foreach ($complianceCheck['issues'] as $issue) {
                    $issueFrequency[$issue] = ($issueFrequency[$issue] ?? 0) + 1;

                    if (str_contains(strtolower($issue), 'duration') || str_contains(strtolower($issue), 'performance')) {
                        $breakdown['performance_violations']++;
                    } elseif (str_contains(strtolower($issue), 'chain') || str_contains(strtolower($issue), 'detect')) {
                        $breakdown['chain_detection_issues']++;
                    } else {
                        $breakdown['completeness_issues']++;
                    }
                }
            }
        }

        // Get most common issues
        arsort($issueFrequency);
        $breakdown['common_issues'] = array_slice($issueFrequency, 0, 10);

        return $breakdown;
    }

    /**
     * Get performance statistics
     */
    private function getPerformanceStatistics(): array
    {
        return [
            'average_analysis_duration_ms' => (int) EnhancedRackAnalysis::avg('analysis_duration_ms'),
            'fastest_analysis_ms' => (int) EnhancedRackAnalysis::min('analysis_duration_ms'),
            'slowest_analysis_ms' => (int) EnhancedRackAnalysis::max('analysis_duration_ms'),
            'constitutional_violations' => EnhancedRackAnalysis::where('analysis_duration_ms', '>', self::MAX_ANALYSIS_DURATION_MS)->count(),
            'fast_analyses_count' => EnhancedRackAnalysis::fastAnalyses()->count(),
            'performance_distribution' => $this->getPerformanceDistribution()
        ];
    }

    /**
     * Get performance distribution
     */
    private function getPerformanceDistribution(): array
    {
        $distribution = [
            'excellent_0_1s' => EnhancedRackAnalysis::where('analysis_duration_ms', '<=', 1000)->count(),
            'good_1_2_5s' => EnhancedRackAnalysis::whereBetween('analysis_duration_ms', [1001, 2500])->count(),
            'acceptable_2_5_5s' => EnhancedRackAnalysis::whereBetween('analysis_duration_ms', [2501, 5000])->count(),
            'constitutional_violation_over_5s' => EnhancedRackAnalysis::where('analysis_duration_ms', '>', 5000)->count()
        ];

        $total = array_sum($distribution);

        if ($total > 0) {
            foreach ($distribution as $key => $count) {
                $distribution[$key . '_percentage'] = round(($count / $total) * 100, 1);
            }
        }

        return $distribution;
    }

    /**
     * Get compliance trends over time
     */
    private function getComplianceTrends(): array
    {
        $recent30Days = EnhancedRackAnalysis::where('processed_at', '>=', now()->subDays(30))->get();
        $recent7Days = EnhancedRackAnalysis::where('processed_at', '>=', now()->subDays(7))->get();
        $recent24Hours = EnhancedRackAnalysis::where('processed_at', '>=', now()->subDay())->get();

        return [
            'last_30_days' => $this->calculateComplianceRate($recent30Days),
            'last_7_days' => $this->calculateComplianceRate($recent7Days),
            'last_24_hours' => $this->calculateComplianceRate($recent24Hours),
            'trend_direction' => $this->calculateTrendDirection($recent30Days, $recent7Days)
        ];
    }

    /**
     * Calculate compliance rate for a collection
     */
    private function calculateComplianceRate(Collection $analyses): array
    {
        $total = $analyses->count();
        $compliant = $analyses->where('constitutional_compliant', true)->count();

        return [
            'total_analyses' => $total,
            'compliant_count' => $compliant,
            'compliance_rate' => $total > 0 ? round(($compliant / $total) * 100, 1) : 0
        ];
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrendDirection(Collection $month, Collection $week): string
    {
        $monthRate = $month->count() > 0 ? ($month->where('constitutional_compliant', true)->count() / $month->count()) * 100 : 0;
        $weekRate = $week->count() > 0 ? ($week->where('constitutional_compliant', true)->count() / $week->count()) * 100 : 0;

        $difference = $weekRate - $monthRate;

        if ($difference > 5) {
            return 'improving';
        } elseif ($difference < -5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Clear compliance cache for a rack
     */
    public function clearComplianceCache(Rack $rack): void
    {
        Cache::forget("rack_compliance_{$rack->uuid}");
        Cache::forget('platform_constitutional_compliance_report');
    }

    /**
     * Bulk validate compliance for multiple racks
     */
    public function bulkValidateCompliance(array $rackIds): array
    {
        $results = [];

        foreach ($rackIds as $rackId) {
            $rack = Rack::find($rackId);
            if ($rack) {
                $results[$rackId] = $this->validateRackCompliance($rack);
            }
        }

        return $results;
    }

    /**
     * Get constitutional requirements
     */
    public function getConstitutionalRequirements(): array
    {
        return [
            'version' => self::CONSTITUTIONAL_VERSION,
            'requirements' => [
                [
                    'requirement' => 'ALL CHAINS Detection',
                    'description' => 'ALL CHAINS within uploaded rack files MUST be detected and included in analysis, regardless of nesting depth',
                    'validation' => 'Comprehensive XPath scanning and hierarchical validation'
                ],
                [
                    'requirement' => 'Performance Limit',
                    'description' => 'Analysis must complete within 5 seconds (5000ms)',
                    'validation' => 'Analysis duration tracking and enforcement'
                ],
                [
                    'requirement' => 'Analysis Completeness',
                    'description' => 'Analysis must be comprehensive, accurate, and properly stored',
                    'validation' => 'Data integrity checks and logical consistency validation'
                ]
            ],
            'enforcement' => [
                'automatic_validation' => true,
                'compliance_reporting' => true,
                'performance_monitoring' => true,
                'hierarchical_integrity_checks' => true
            ]
        ];
    }
}