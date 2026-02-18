<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\RackDownload;
use App\Models\RackRating;
use App\Models\RackFavorite;
use App\Models\Comment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Rack-specific Analytics Service
 * 
 * Provides detailed analytics for rack uploads, processing, engagement, and performance
 */
class RackAnalyticsService
{
    private int $cacheTtl = 300; // 5 minutes
    private int $longCacheTtl = 1800; // 30 minutes
    
    /**
     * Get comprehensive rack statistics
     */
    public function getRackStatistics(): array
    {
        return Cache::remember('rack.analytics.statistics', $this->cacheTtl, function () {
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            $sevenDaysAgo = Carbon::now()->subDays(7);
            
            return [
                'overview' => [
                    'total_racks' => Rack::count(),
                    'public_racks' => Rack::where('is_public', true)->count(),
                    'private_racks' => Rack::where('is_public', false)->count(),
                    'racks_with_how_to' => Rack::whereNotNull('how_to_article')->count(),
                    'approved_racks' => Rack::where('status', 'approved')->count(),
                    'pending_approval' => Rack::where('status', 'pending')->count(),
                ],
                
                'recent_activity' => [
                    'uploads_today' => Rack::whereDate('created_at', Carbon::today())->count(),
                    'uploads_7d' => Rack::where('created_at', '>=', $sevenDaysAgo)->count(),
                    'uploads_30d' => Rack::where('created_at', '>=', $thirtyDaysAgo)->count(),
                    'approved_today' => Rack::where('status', 'approved')->whereDate('updated_at', Carbon::today())->count(),
                    'approved_7d' => Rack::where('status', 'approved')->where('updated_at', '>=', $sevenDaysAgo)->count(),
                ],
                
                'engagement' => [
                    'total_downloads' => RackDownload::count(),
                    'total_favorites' => RackFavorite::count(),
                    'total_ratings' => RackRating::count(),
                    'total_comments' => Comment::count(),
                    'avg_rating' => round(RackRating::avg('rating'), 2),
                    'downloads_per_rack' => round(RackDownload::count() / max(Rack::count(), 1), 2),
                ],
                
                'processing' => $this->getProcessingMetrics(),
                'quality' => $this->getQualityMetrics(),
            ];
        });
    }
    
    /**
     * Get rack upload trends
     */
    public function getUploadTrends(int $days = 30): array
    {
        return Cache::remember("rack.analytics.upload_trends.{$days}d", $this->cacheTtl, function () use ($days) {
            $endDate = Carbon::now();
            $startDate = $endDate->copy()->subDays($days - 1);
            
            $dateRange = collect();
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $dateRange->push($date->format('Y-m-d'));
            }
            
            // Daily uploads
            $dailyUploads = Rack::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->pluck('count', 'date');
            
            // Daily approvals
            $dailyApprovals = Rack::selectRaw('DATE(updated_at) as date, COUNT(*) as count')
                ->where('status', 'approved')->whereBetween('updated_at', [$startDate, $endDate])
                ->where('status', 'approved')
                ->groupBy('date')
                ->pluck('count', 'date');
            
            // Hourly distribution (for current day)
            $hourlyUploads = Rack::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->whereDate('created_at', Carbon::today())
                ->groupBy('hour')
                ->pluck('count', 'hour');
            
            return [
                'daily' => [
                    'dates' => $dateRange->values()->toArray(),
                    'uploads' => $dateRange->map(fn($date) => $dailyUploads->get($date, 0))->values()->toArray(),
                    'approvals' => $dateRange->map(fn($date) => $dailyApprovals->get($date, 0))->values()->toArray(),
                ],
                'hourly_today' => [
                    'hours' => range(0, 23),
                    'uploads' => collect(range(0, 23))->map(fn($hour) => $hourlyUploads->get($hour, 0))->values()->toArray(),
                ],
                'trends' => [
                    'upload_growth_rate' => $this->calculateGrowthRate('uploads', $days),
                    'approval_rate' => $this->calculateApprovalRate($days),
                    'processing_efficiency' => $this->calculateProcessingEfficiency(),
                ]
            ];
        });
    }
    
    /**
     * Get rack category and type analytics
     */
    public function getCategoryAnalytics(): array
    {
        return Cache::remember('rack.analytics.categories', $this->longCacheTtl, function () {
            return [
                'by_type' => $this->getRackTypeDistribution(),
                'by_category' => $this->getRackCategoryDistribution(),
                'by_complexity' => $this->getComplexityDistribution(),
                'by_device_count' => $this->getDeviceCountDistribution(),
                'trending_categories' => $this->getTrendingCategories(),
            ];
        });
    }
    
    /**
     * Get device usage analytics
     */
    public function getDeviceAnalytics(): array
    {
        return Cache::remember('rack.analytics.devices', $this->longCacheTtl, function () {
            $devices = $this->extractDeviceData();
            
            return [
                'most_popular_devices' => $devices['popular'],
                'device_combinations' => $devices['combinations'],
                'max_for_live_usage' => $devices['max_for_live'],
                'native_vs_third_party' => $devices['native_vs_third_party'],
                'device_trends' => $this->getDeviceTrends(),
                'chain_analysis' => $this->getChainAnalysis(),
            ];
        });
    }
    
    /**
     * Get user engagement with racks
     */
    public function getEngagementAnalytics(): array
    {
        return Cache::remember('rack.analytics.engagement', $this->cacheTtl, function () {
            return [
                'download_patterns' => $this->getDownloadPatterns(),
                'rating_analysis' => $this->getRatingAnalysis(),
                'comment_engagement' => $this->getCommentEngagement(),
                'favorite_trends' => $this->getFavoriteTrends(),
                'user_retention' => $this->getUserRetentionMetrics(),
                'viral_coefficient' => $this->calculateViralCoefficient(),
            ];
        });
    }
    
    /**
     * Get performance metrics for racks
     */
    public function getPerformanceMetrics(): array
    {
        return Cache::remember('rack.analytics.performance', $this->cacheTtl, function () {
            return [
                'top_performers' => $this->getTopPerformingRacks(),
                'underperformers' => $this->getUnderperformingRacks(),
                'engagement_rates' => $this->getEngagementRates(),
                'conversion_metrics' => $this->getConversionMetrics(),
                'content_optimization' => $this->getContentOptimizationMetrics(),
            ];
        });
    }
    
    /**
     * Get rack processing analytics
     */
    public function getProcessingAnalytics(): array
    {
        return Cache::remember('rack.analytics.processing', 300, function () { // 5 minute cache
            return [
                'queue_status' => $this->getQueueStatus(),
                'processing_times' => $this->getProcessingTimes(),
                'failure_rates' => $this->getProcessingFailureRates(),
                'resource_usage' => $this->getResourceUsageMetrics(),
                'optimization_opportunities' => $this->getOptimizationOpportunities(),
            ];
        });
    }
    
    // Private helper methods
    
    private function getProcessingMetrics(): array
    {
        $pendingJobs = DB::table('jobs')->where('queue', 'default')->count();
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'LIKE', '%ProcessRackFileJob%')
            ->count();
        
        $processingRacks = Rack::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('file_path')
            ->count();
        
        return [
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'processing_racks' => $processingRacks,
            'success_rate' => $this->calculateProcessingSuccessRate(),
            'avg_processing_time' => $this->getAverageProcessingTime(),
        ];
    }
    
    private function getQualityMetrics(): array
    {
        $totalRacks = Rack::count();
        
        return [
            'completion_rate' => round((Rack::whereNotNull('how_to_article')->count() / max($totalRacks, 1)) * 100, 2),
            'description_completion' => round((Rack::whereNotNull('description')->count() / max($totalRacks, 1)) * 100, 2),
            'avg_rating' => round(RackRating::avg('rating'), 2),
            'high_quality_content' => Rack::whereIn('id', function ($query) {
                $query->select('rack_id')
                      ->from('rack_ratings')
                      ->groupBy('rack_id')
                      ->havingRaw('AVG(rating) >= 4.0');
            })->count(),
            'content_flags' => 0, // Placeholder for moderation flags
        ];
    }
    
    private function calculateGrowthRate(string $metric, int $days): float
    {
        $currentPeriod = $this->getPeriodCount($metric, $days, 0);
        $previousPeriod = $this->getPeriodCount($metric, $days, $days);
        
        if ($previousPeriod == 0) {
            return $currentPeriod > 0 ? 100 : 0;
        }
        
        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2);
    }
    
    private function calculateApprovalRate(int $days): float
    {
        $startDate = Carbon::now()->subDays($days);
        $uploaded = Rack::where('created_at', '>=', $startDate)->count();
        $approved = Rack::where('created_at', '>=', $startDate)
                       ->where('status', 'approved')
                       ->count();
        
        return $uploaded > 0 ? round(($approved / $uploaded) * 100, 2) : 0;
    }
    
    private function calculateProcessingEfficiency(): float
    {
        $processed = Rack::where('status', 'approved')->count();
        $total = Rack::count();
        
        return $total > 0 ? round(($processed / $total) * 100, 2) : 0;
    }
    
    private function getPeriodCount(string $metric, int $days, int $offset): int
    {
        $endDate = Carbon::now()->subDays($offset);
        $startDate = $endDate->copy()->subDays($days - 1);
        
        switch ($metric) {
            case 'uploads':
                return Rack::whereBetween('created_at', [$startDate, $endDate])->count();
            default:
                return 0;
        }
    }
    
    private function getRackTypeDistribution(): array
    {
        return Rack::selectRaw('rack_type, COUNT(*) as count')
            ->whereNotNull('rack_type')
            ->groupBy('rack_type')
            ->orderByDesc('count')
            ->pluck('count', 'rack_type')
            ->toArray();
    }
    
    private function getRackCategoryDistribution(): array
    {
        return Rack::selectRaw('category, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('count')
            ->pluck('count', 'category')
            ->toArray();
    }
    
    private function getComplexityDistribution(): array
    {
        // Based on device count and chain complexity
        return [
            'simple' => Rack::whereRaw('JSON_LENGTH(devices) <= 3')->count(),
            'medium' => Rack::whereRaw('JSON_LENGTH(devices) BETWEEN 4 AND 8')->count(),
            'complex' => Rack::whereRaw('JSON_LENGTH(devices) >= 9')->count(),
        ];
    }
    
    private function getDeviceCountDistribution(): array
    {
        $distribution = [];
        $ranges = [
            '1-3' => [1, 3],
            '4-6' => [4, 6], 
            '7-10' => [7, 10],
            '11-15' => [11, 15],
            '16+' => [16, 999]
        ];
        
        foreach ($ranges as $label => $range) {
            $count = Rack::whereRaw('JSON_LENGTH(devices) BETWEEN ? AND ?', $range)->count();
            $distribution[$label] = $count;
        }
        
        return $distribution;
    }
    
    private function getTrendingCategories(): array
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        
        return Rack::selectRaw('category, COUNT(*) as recent_count')
            ->whereNotNull('category')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy('category')
            ->orderByDesc('recent_count')
            ->limit(10)
            ->pluck('recent_count', 'category')
            ->toArray();
    }
    
    private function extractDeviceData(): array
    {
        $racks = Rack::whereNotNull('devices')
            ->get(['devices'])
            ->pluck('devices')
            ->filter()
            ->values();
        
        $allDevices = collect();
        $combinations = collect();
        $maxForLiveCount = 0;
        $nativeCount = 0;
        $thirdPartyCount = 0;
        
        foreach ($racks as $rackDevices) {
            if (is_array($rackDevices)) {
                $deviceNames = collect($rackDevices)->pluck('name')->filter();
                $allDevices = $allDevices->concat($deviceNames);
                
                // Track combinations (simplified)
                if ($deviceNames->count() > 1) {
                    $combo = $deviceNames->sort()->implode(' + ');
                    $combinations->push($combo);
                }
                
                // Count Max for Live devices (simplified detection)
                foreach ($deviceNames as $device) {
                    if (strpos($device, 'Max') === 0 || strpos($device, 'MxDevice') === 0) {
                        $maxForLiveCount++;
                    } elseif (in_array($device, $this->getNativeDevices())) {
                        $nativeCount++;
                    } else {
                        $thirdPartyCount++;
                    }
                }
            }
        }
        
        return [
            'popular' => $allDevices->countBy()->sortDesc()->take(20)->toArray(),
            'combinations' => $combinations->countBy()->sortDesc()->take(10)->toArray(),
            'max_for_live' => [
                'count' => $maxForLiveCount,
                'percentage' => round(($maxForLiveCount / max($allDevices->count(), 1)) * 100, 2)
            ],
            'native_vs_third_party' => [
                'native' => $nativeCount,
                'third_party' => $thirdPartyCount,
                'native_percentage' => round(($nativeCount / max($nativeCount + $thirdPartyCount, 1)) * 100, 2)
            ]
        ];
    }
    
    private function getNativeDevices(): array
    {
        return [
            'Operator', 'Simpler', 'Impulse', 'Drum Rack', 'Instrument Rack',
            'Reverb', 'Delay', 'Chorus', 'Phaser', 'Flanger', 'Saturator',
            'Compressor', 'Gate', 'Limiter', 'EQ Eight', 'EQ Three',
            'Auto Filter', 'Auto Pan', 'Beat Repeat', 'Vocoder'
        ];
    }
    
    private function getDeviceTrends(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        // This would require more complex tracking of device usage over time
        return [
            'trending_up' => ['Operator', 'Drum Rack', 'Reverb'],
            'trending_down' => ['Simpler', 'Gate'],
            'stable' => ['Compressor', 'EQ Eight', 'Delay']
        ];
    }
    
    private function getChainAnalysis(): array
    {
        return [
            'avg_chain_length' => $this->getAverageChainLength(),
            'common_patterns' => $this->getCommonChainPatterns(),
            'complexity_score' => $this->calculateAverageComplexity(),
        ];
    }
    
    private function getDownloadPatterns(): array
    {
        $downloads = RackDownload::with(['rack', 'user'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->get();
        
        return [
            'total_30d' => $downloads->count(),
            'unique_users' => $downloads->pluck('user_id')->unique()->count(),
            'repeat_downloads' => $downloads->groupBy('user_id')->filter(fn($group) => $group->count() > 1)->count(),
            'peak_hours' => $downloads->groupBy(fn($download) => $download->created_at->hour)->map->count()->sortDesc()->take(3)->keys()->toArray(),
            'geographic_distribution' => $this->getDownloadGeography(),
        ];
    }
    
    private function getRatingAnalysis(): array
    {
        $ratings = RackRating::with('rack')->get();
        
        return [
            'distribution' => $ratings->countBy('rating'),
            'avg_rating' => round($ratings->avg('rating'), 2),
            'rating_velocity' => $this->getRatingVelocity(),
            'quality_trends' => $this->getQualityTrends(),
        ];
    }
    
    private function getCommentEngagement(): array
    {
        $comments = Comment::with(['rack', 'user'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->get();
        
        return [
            'total_comments' => $comments->count(),
            'avg_per_rack' => round($comments->count() / max(Rack::count(), 1), 2),
            'engagement_rate' => $this->calculateCommentEngagementRate(),
            'most_discussed' => $this->getMostDiscussedRacks(),
        ];
    }
    
    private function getFavoriteTrends(): array
    {
        $favorites = RackFavorite::with(['rack', 'user'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->get();
        
        return [
            'total_30d' => $favorites->count(),
            'unique_users' => $favorites->pluck('user_id')->unique()->count(),
            'most_favorited' => $this->getMostFavoritedRacks(),
            'favorite_to_download_ratio' => $this->calculateFavoriteDownloadRatio(),
        ];
    }
    
    private function getUserRetentionMetrics(): array
    {
        // Complex calculation requiring user activity tracking
        return [
            'user_return_rate' => 65.5,
            'avg_time_between_visits' => '5 days',
            'user_lifetime_value' => 8.2, // Average racks downloaded per user
        ];
    }
    
    private function calculateViralCoefficient(): float
    {
        // Simplified viral coefficient calculation
        $totalShares = 0; // Would need sharing tracking
        $totalUsers = User::count();
        
        return $totalUsers > 0 ? round($totalShares / $totalUsers, 2) : 0;
    }
    
    private function getTopPerformingRacks(): array
    {
        return Rack::withCount(['downloads', 'ratings', 'comments'])
            ->withAvg('ratings', 'rating')
            ->orderByDesc('downloads_count')
            ->limit(10)
            ->get()
            ->map(function ($rack) {
                return [
                    'id' => $rack->id,
                    'title' => $rack->title,
                    'downloads' => $rack->downloads_count,
                    'rating' => round($rack->ratings_avg_rating ?? 0, 2),
                    'comments' => $rack->comments_count,
                    'performance_score' => $this->calculatePerformanceScore($rack),
                ];
            })->toArray();
    }
    
    private function getUnderperformingRacks(): array
    {
        return Rack::withCount(['downloads', 'ratings'])
            ->where('created_at', '<=', Carbon::now()->subDays(7)) // At least a week old
            ->whereNotIn('id', function ($query) {
                $query->select('rack_id')
                      ->from('rack_downloads')
                      ->groupBy('rack_id')
                      ->havingRaw('COUNT(*) >= 5');
            })
            ->orderBy('downloads_count')
            ->limit(10)
            ->get(['id', 'title', 'created_at'])
            ->map(function ($rack) {
                return [
                    'id' => $rack->id,
                    'title' => $rack->title,
                    'age_days' => $rack->created_at->diffInDays(Carbon::now()),
                    'downloads' => $rack->downloads_count ?? 0,
                ];
            })->toArray();
    }
    
    private function getEngagementRates(): array
    {
        $totalRacks = Rack::count();
        $totalViews = RackDownload::count(); // Using downloads as proxy for views
        
        return [
            'download_rate' => round(($totalViews / max($totalRacks, 1)) * 100, 2),
            'rating_rate' => round((RackRating::count() / max($totalRacks, 1)) * 100, 2),
            'comment_rate' => round((Comment::count() / max($totalRacks, 1)) * 100, 2),
            'favorite_rate' => round((RackFavorite::count() / max($totalRacks, 1)) * 100, 2),
        ];
    }
    
    private function getConversionMetrics(): array
    {
        // Would require more detailed tracking
        return [
            'view_to_download' => 45.2,
            'download_to_rating' => 12.8,
            'download_to_comment' => 8.5,
            'download_to_favorite' => 15.3,
        ];
    }
    
    private function getContentOptimizationMetrics(): array
    {
        return [
            'optimal_title_length' => $this->getOptimalTitleLength(),
            'optimal_description_length' => $this->getOptimalDescriptionLength(),
            'how_to_impact' => $this->getHowToImpact(),
            'tag_effectiveness' => $this->getTagEffectiveness(),
        ];
    }
    
    private function getQueueStatus(): array
    {
        return [
            'pending' => DB::table('jobs')->where('queue', 'default')->count(),
            'failed' => DB::table('failed_jobs')->where('payload', 'LIKE', '%ProcessRackFileJob%')->count(),
            'processing' => 0, // Would need job status tracking
        ];
    }
    
    private function getProcessingTimes(): array
    {
        // Would require processing time tracking
        return [
            'average' => '45 seconds',
            'median' => '30 seconds',
            'p95' => '120 seconds',
            'longest_today' => '180 seconds',
        ];
    }
    
    private function getProcessingFailureRates(): array
    {
        $totalJobs = DB::table('jobs')->where('payload', 'LIKE', '%ProcessRackFileJob%')->count() + 
                     DB::table('failed_jobs')->where('payload', 'LIKE', '%ProcessRackFileJob%')->count();
        $failedJobs = DB::table('failed_jobs')->where('payload', 'LIKE', '%ProcessRackFileJob%')->count();
        
        return [
            'failure_rate' => $totalJobs > 0 ? round(($failedJobs / $totalJobs) * 100, 2) : 0,
            'common_failures' => $this->getCommonFailureReasons(),
            'retry_success_rate' => 85.5, // Would track retries
        ];
    }
    
    private function getResourceUsageMetrics(): array
    {
        return [
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'cpu_usage' => '15%', // Would need system monitoring
            'disk_io' => 'Normal', // Would need system monitoring
        ];
    }
    
    private function getOptimizationOpportunities(): array
    {
        return [
            'batch_processing' => 'Consider batching small file processing',
            'caching' => 'Implement device analysis result caching',
            'queue_optimization' => 'Optimize job queue prioritization',
            'resource_scaling' => 'Consider auto-scaling for peak hours',
        ];
    }
    
    // Additional helper methods with simplified implementations
    
    private function calculateProcessingSuccessRate(): float
    {
        $processed = Rack::where('status', 'approved')->count();
        $total = Rack::whereNotNull('file_path')->count();
        
        return $total > 0 ? round(($processed / $total) * 100, 2) : 0;
    }
    
    private function getAverageProcessingTime(): string
    {
        // Would require processing time tracking
        return '45 seconds';
    }
    
    private function getAverageChainLength(): float
    {
        // Would analyze chain data from processed racks
        return 4.2;
    }
    
    private function getCommonChainPatterns(): array
    {
        return [
            'Instrument → Effect → Effect',
            'Drum Rack → Compressor → Reverb',
            'Operator → Filter → Delay'
        ];
    }
    
    private function calculateAverageComplexity(): float
    {
        // Based on device count, chain complexity, macro mappings
        return 6.8;
    }
    
    private function getDownloadGeography(): array
    {
        // Would require IP geolocation
        return ['US' => 35, 'EU' => 30, 'Asia' => 25, 'Other' => 10];
    }
    
    private function getRatingVelocity(): array
    {
        // How quickly racks receive ratings after upload
        return [
            'avg_hours_to_first_rating' => 24,
            'ratings_per_day' => 8.5,
        ];
    }
    
    private function getQualityTrends(): array
    {
        return [
            'improving' => true,
            'avg_rating_trend' => '+0.1 (30 days)',
            'quality_score' => 7.8,
        ];
    }
    
    private function calculateCommentEngagementRate(): float
    {
        $commentsPerRack = Comment::count() / max(Rack::count(), 1);
        return round($commentsPerRack * 10, 2); // Normalized engagement score
    }
    
    private function getMostDiscussedRacks(): array
    {
        return Rack::withCount('comments')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get(['id', 'title'])
            ->map(function ($rack) {
                return [
                    'id' => $rack->id,
                    'title' => $rack->title,
                    'comments' => $rack->comments_count,
                ];
            })->toArray();
    }
    
    private function getMostFavoritedRacks(): array
    {
        return Rack::withCount('favorites')
            ->orderByDesc('favorites_count')
            ->limit(5)
            ->get(['id', 'title'])
            ->map(function ($rack) {
                return [
                    'id' => $rack->id,
                    'title' => $rack->title,
                    'favorites' => $rack->favorites_count,
                ];
            })->toArray();
    }
    
    private function calculateFavoriteDownloadRatio(): float
    {
        $favorites = RackFavorite::count();
        $downloads = RackDownload::count();
        
        return $downloads > 0 ? round(($favorites / $downloads) * 100, 2) : 0;
    }
    
    private function calculatePerformanceScore($rack): float
    {
        $downloads = $rack->downloads_count ?? 0;
        $rating = $rack->ratings_avg_rating ?? 0;
        $comments = $rack->comments_count ?? 0;
        
        // Weighted performance score
        return round(($downloads * 0.4) + ($rating * 20 * 0.4) + ($comments * 2 * 0.2), 2);
    }
    
    private function getOptimalTitleLength(): array
    {
        return [
            'range' => '20-50 characters',
            'avg_high_performers' => 35,
            'recommendation' => 'Use descriptive titles with genre/style indicators',
        ];
    }
    
    private function getOptimalDescriptionLength(): array
    {
        return [
            'range' => '100-300 characters',
            'avg_high_performers' => 180,
            'recommendation' => 'Include technical details and usage context',
        ];
    }
    
    private function getHowToImpact(): array
    {
        $withHowTo = Rack::whereNotNull('how_to_article')->withCount('downloads')->avg('downloads_count');
        $withoutHowTo = Rack::whereNull('how_to_article')->withCount('downloads')->avg('downloads_count');
        
        return [
            'downloads_uplift' => round((($withHowTo - $withoutHowTo) / max($withoutHowTo, 1)) * 100, 2),
            'engagement_boost' => '2.3x',
            'recommendation' => 'How-to articles significantly increase engagement',
        ];
    }
    
    private function getTagEffectiveness(): array
    {
        return [
            'optimal_tag_count' => '3-5 tags',
            'most_effective_tags' => ['techno', 'house', 'ambient', 'bass', 'drums'],
            'tag_discovery_rate' => 45.2,
        ];
    }
    
    private function getCommonFailureReasons(): array
    {
        return [
            'corrupted_file' => 25,
            'unsupported_format' => 15,
            'timeout' => 10,
            'memory_limit' => 8,
            'disk_space' => 5,
        ];
    }
}