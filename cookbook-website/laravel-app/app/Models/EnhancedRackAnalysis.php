<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnhancedRackAnalysis extends Model
{
    use HasFactory;

    protected $table = 'enhanced_rack_analysis';

    protected $fillable = [
        'rack_id',
        'constitutional_compliant',
        'compliance_issues',
        'has_nested_chains',
        'total_chains_detected',
        'max_nesting_depth',
        'total_devices',
        'device_type_breakdown',
        'analysis_duration_ms',
        'processed_at',
        'analyzer_version',
        'analysis_metadata',
    ];

    protected $casts = [
        'constitutional_compliant' => 'boolean',
        'has_nested_chains' => 'boolean',
        'compliance_issues' => 'array',
        'device_type_breakdown' => 'array',
        'analysis_metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the rack that owns this analysis
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Scope for constitutionally compliant analyses
     */
    public function scopeConstitutionallyCompliant($query)
    {
        return $query->where('constitutional_compliant', true);
    }

    /**
     * Scope for analyses with nested chains
     */
    public function scopeWithNestedChains($query)
    {
        return $query->where('has_nested_chains', true);
    }

    /**
     * Scope for analyses processed after a date
     */
    public function scopeProcessedAfter($query, $date)
    {
        return $query->where('processed_at', '>=', $date);
    }

    /**
     * Scope for analyses with specific analyzer version
     */
    public function scopeWithAnalyzerVersion($query, string $version)
    {
        return $query->where('analyzer_version', $version);
    }

    /**
     * Scope for fast analyses (under specified duration)
     */
    public function scopeFastAnalyses($query, int $maxDurationMs = 5000)
    {
        return $query->where('analysis_duration_ms', '<=', $maxDurationMs);
    }

    /**
     * Scope for analyses with minimum chain count
     */
    public function scopeWithMinimumChains($query, int $minChains)
    {
        return $query->where('total_chains_detected', '>=', $minChains);
    }

    /**
     * Scope for deep nesting analyses (3+ levels)
     */
    public function scopeDeepNesting($query, int $minDepth = 3)
    {
        return $query->where('max_nesting_depth', '>=', $minDepth);
    }

    /**
     * Check if this analysis meets constitutional requirements
     */
    public function meetsConstitutionalRequirements(): bool
    {
        // Constitutional requirement: ALL CHAINS must be included
        return $this->constitutional_compliant &&
               $this->analysis_duration_ms < 5000 && // Sub-5 second requirement
               $this->processed_at !== null;
    }

    /**
     * Get the analysis performance rating
     */
    public function getPerformanceRating(): string
    {
        if ($this->analysis_duration_ms <= 1000) {
            return 'excellent';
        } elseif ($this->analysis_duration_ms <= 2500) {
            return 'good';
        } elseif ($this->analysis_duration_ms <= 5000) {
            return 'acceptable';
        } else {
            return 'slow';
        }
    }

    /**
     * Get the complexity rating based on chains and devices
     */
    public function getComplexityRating(): string
    {
        $chainDeviceRatio = $this->total_devices > 0 ?
            $this->total_chains_detected / $this->total_devices : 0;

        if ($this->max_nesting_depth >= 4 || $chainDeviceRatio > 0.5) {
            return 'high';
        } elseif ($this->max_nesting_depth >= 2 || $chainDeviceRatio > 0.2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get compliance issues as a formatted string
     */
    public function getComplianceIssuesFormatted(): string
    {
        if (!$this->compliance_issues || empty($this->compliance_issues)) {
            return 'No issues';
        }

        return implode('; ', $this->compliance_issues);
    }

    /**
     * Get device type breakdown as percentages
     */
    public function getDeviceTypePercentages(): array
    {
        if (!$this->device_type_breakdown || $this->total_devices === 0) {
            return [];
        }

        $percentages = [];
        foreach ($this->device_type_breakdown as $type => $count) {
            $percentages[$type] = round(($count / $this->total_devices) * 100, 1);
        }

        return $percentages;
    }

    /**
     * Check if analysis indicates a complex rack structure
     */
    public function isComplexStructure(): bool
    {
        return $this->has_nested_chains &&
               ($this->max_nesting_depth >= 3 ||
                $this->total_chains_detected >= 5 ||
                $this->total_devices >= 20);
    }

    /**
     * Check if analysis was performed recently
     */
    public function isRecentAnalysis(int $hours = 24): bool
    {
        return $this->processed_at &&
               $this->processed_at->isAfter(now()->subHours($hours));
    }

    /**
     * Get the efficiency score (devices per millisecond)
     */
    public function getEfficiencyScore(): float
    {
        if ($this->analysis_duration_ms === 0) {
            return 0;
        }

        return round($this->total_devices / $this->analysis_duration_ms, 4);
    }

    /**
     * Get analysis summary
     */
    public function getSummary(): array
    {
        return [
            'constitutional_compliant' => $this->constitutional_compliant,
            'performance_rating' => $this->getPerformanceRating(),
            'complexity_rating' => $this->getComplexityRating(),
            'efficiency_score' => $this->getEfficiencyScore(),
            'is_complex_structure' => $this->isComplexStructure(),
            'chain_to_device_ratio' => $this->total_devices > 0 ?
                round($this->total_chains_detected / $this->total_devices, 2) : 0,
            'compliance_issues' => $this->getComplianceIssuesFormatted(),
        ];
    }

    /**
     * Static method to get constitutional compliance statistics
     */
    public static function getComplianceStatistics(): array
    {
        $total = self::count();
        $compliant = self::constitutionallyCompliant()->count();
        $withChains = self::withNestedChains()->count();
        $fastAnalyses = self::fastAnalyses()->count();
        $recentAnalyses = self::where('processed_at', '>=', now()->subWeek())->count();

        return [
            'total_analyses' => $total,
            'compliant_count' => $compliant,
            'compliance_percentage' => $total > 0 ? round(($compliant / $total) * 100, 1) : 0,
            'chains_detected_count' => $withChains,
            'chains_percentage' => $total > 0 ? round(($withChains / $total) * 100, 1) : 0,
            'fast_analyses_count' => $fastAnalyses,
            'fast_percentage' => $total > 0 ? round(($fastAnalyses / $total) * 100, 1) : 0,
            'recent_analyses_count' => $recentAnalyses,
            'average_duration_ms' => (int) self::avg('analysis_duration_ms'),
            'average_chains_detected' => (float) self::avg('total_chains_detected'),
            'average_devices' => (float) self::avg('total_devices'),
        ];
    }
}
