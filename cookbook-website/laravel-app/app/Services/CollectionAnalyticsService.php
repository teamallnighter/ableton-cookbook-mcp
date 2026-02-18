<?php

namespace App\Services;

use App\Models\EnhancedCollection;
use App\Models\LearningPath;
use App\Models\CollectionAnalytics;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Service for tracking and analyzing collection performance
 */
class CollectionAnalyticsService
{
    /**
     * Initialize analytics tracking for a collection
     */
    public function initializeCollectionTracking(EnhancedCollection $collection): void
    {
        // Create initial analytics record for today
        CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => EnhancedCollection::class,
            'analyticsable_id' => $collection->id,
            'date' => now()->toDateString(),
        ], [
            'unique_views' => 0,
            'total_views' => 0,
            'downloads' => 0,
            'saves' => 0,
            'shares' => 0,
            'likes' => 0,
            'comments' => 0,
            'ratings' => 0,
            'average_rating' => 0,
        ]);
    }

    /**
     * Initialize analytics tracking for a learning path
     */
    public function initializeLearningPathTracking(LearningPath $learningPath): void
    {
        CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => LearningPath::class,
            'analyticsable_id' => $learningPath->id,
            'date' => now()->toDateString(),
        ], [
            'new_enrollments' => 0,
            'completions' => 0,
            'dropouts' => 0,
            'completion_rate' => 0,
            'average_progress' => 0,
            'total_time_spent' => 0,
            'active_learners' => 0,
        ]);
    }

    /**
     * Record a collection view
     */
    public function recordCollectionView(
        EnhancedCollection $collection,
        array $metadata = []
    ): void {
        $today = now()->toDateString();
        $ipAddress = $metadata['ip_address'] ?? request()->ip();
        $userId = $metadata['user_id'] ?? null;

        // Check if this is a unique view (same IP/user today)
        $cacheKey = "collection_view:{$collection->id}:{$today}:" . ($userId ?? $ipAddress);
        $isUniqueView = !Cache::has($cacheKey);

        if ($isUniqueView) {
            Cache::put($cacheKey, true, now()->endOfDay());
        }

        // Update analytics
        $analytics = CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => EnhancedCollection::class,
            'analyticsable_id' => $collection->id,
            'date' => $today,
        ]);

        $analytics->increment('total_views');
        
        if ($isUniqueView) {
            $analytics->increment('unique_views');
        }

        // Update geographic data if available
        if (isset($metadata['country'])) {
            $this->updateGeographicData($analytics, $metadata);
        }

        // Update device/browser data if available
        if (isset($metadata['user_agent'])) {
            $this->updateDeviceData($analytics, $metadata['user_agent']);
        }

        // Update referrer data
        if (isset($metadata['referrer'])) {
            $this->updateReferrerData($analytics, $metadata['referrer']);
        }
    }

    /**
     * Record a download
     */
    public function recordDownload(
        EnhancedCollection $collection,
        $download
    ): void {
        $today = now()->toDateString();

        $analytics = CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => EnhancedCollection::class,
            'analyticsable_id' => $collection->id,
            'date' => $today,
        ]);

        $analytics->increment('downloads');
    }

    /**
     * Record user engagement (save, share, like, comment)
     */
    public function recordEngagement(
        $subject,
        string $engagementType,
        array $metadata = []
    ): void {
        $today = now()->toDateString();
        $subjectType = get_class($subject);

        $analytics = CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => $subjectType,
            'analyticsable_id' => $subject->id,
            'date' => $today,
        ]);

        match ($engagementType) {
            'save' => $analytics->increment('saves'),
            'share' => $analytics->increment('shares'),
            'like' => $analytics->increment('likes'),
            'comment' => $analytics->increment('comments'),
            'rating' => $this->recordRating($analytics, $metadata['rating'] ?? 0),
            default => null,
        };
    }

    /**
     * Record learning path progress events
     */
    public function recordLearningProgress(
        LearningPath $learningPath,
        string $eventType,
        array $metadata = []
    ): void {
        $today = now()->toDateString();

        $analytics = CollectionAnalytics::firstOrCreate([
            'analyticsable_type' => LearningPath::class,
            'analyticsable_id' => $learningPath->id,
            'date' => $today,
        ]);

        match ($eventType) {
            'enrollment' => $analytics->increment('new_enrollments'),
            'completion' => $analytics->increment('completions'),
            'dropout' => $analytics->increment('dropouts'),
            'time_spent' => $analytics->increment('total_time_spent', $metadata['time'] ?? 0),
            default => null,
        };

        // Update derived metrics
        if (in_array($eventType, ['completion', 'dropout'])) {
            $this->updateCompletionRate($analytics, $learningPath);
        }
    }

    /**
     * Get analytics summary for a collection
     */
    public function getCollectionAnalyticsSummary(
        EnhancedCollection $collection,
        int $days = 30
    ): array {
        $cacheKey = "collection_analytics:{$collection->id}:{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($collection, $days) {
            $startDate = now()->subDays($days)->toDateString();
            
            $analytics = CollectionAnalytics::where('analyticsable_type', EnhancedCollection::class)
                ->where('analyticsable_id', $collection->id)
                ->where('date', '>=', $startDate)
                ->orderBy('date', 'asc')
                ->get();

            $summary = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => now()->toDateString(),
                    'days' => $days,
                ],
                'totals' => [
                    'unique_views' => $analytics->sum('unique_views'),
                    'total_views' => $analytics->sum('total_views'),
                    'downloads' => $analytics->sum('downloads'),
                    'saves' => $analytics->sum('saves'),
                    'shares' => $analytics->sum('shares'),
                    'likes' => $analytics->sum('likes'),
                    'comments' => $analytics->sum('comments'),
                    'ratings' => $analytics->sum('ratings'),
                ],
                'averages' => [
                    'daily_unique_views' => round($analytics->avg('unique_views'), 1),
                    'daily_total_views' => round($analytics->avg('total_views'), 1),
                    'average_rating' => round($analytics->whereNotNull('average_rating')->avg('average_rating'), 2),
                ],
                'trends' => $this->calculateTrends($analytics),
                'top_countries' => $this->getTopCountries($analytics),
                'top_referrers' => $this->getTopReferrers($analytics),
            ];

            return $summary;
        });
    }

    /**
     * Get learning path analytics summary
     */
    public function getLearningPathAnalyticsSummary(
        LearningPath $learningPath,
        int $days = 30
    ): array {
        $cacheKey = "learning_path_analytics:{$learningPath->id}:{$days}";
        
        return Cache::remember($cacheKey, 1800, function () use ($learningPath, $days) {
            $startDate = now()->subDays($days)->toDateString();
            
            $analytics = CollectionAnalytics::where('analyticsable_type', LearningPath::class)
                ->where('analyticsable_id', $learningPath->id)
                ->where('date', '>=', $startDate)
                ->orderBy('date', 'asc')
                ->get();

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => now()->toDateString(),
                    'days' => $days,
                ],
                'totals' => [
                    'new_enrollments' => $analytics->sum('new_enrollments'),
                    'completions' => $analytics->sum('completions'),
                    'dropouts' => $analytics->sum('dropouts'),
                    'total_time_spent' => $analytics->sum('total_time_spent'),
                ],
                'averages' => [
                    'daily_enrollments' => round($analytics->avg('new_enrollments'), 1),
                    'completion_rate' => round($analytics->whereNotNull('completion_rate')->avg('completion_rate'), 1),
                    'average_progress' => round($analytics->whereNotNull('average_progress')->avg('average_progress'), 1),
                    'session_duration' => round($analytics->whereNotNull('average_session_duration')->avg('average_session_duration'), 1),
                ],
                'trends' => $this->calculateLearningTrends($analytics),
                'problem_areas' => $this->identifyProblemAreas($learningPath),
            ];
        });
    }

    /**
     * Get real-time analytics for dashboard
     */
    public function getRealTimeAnalytics($subject): array
    {
        $subjectType = get_class($subject);
        $today = now()->toDateString();

        $todayAnalytics = CollectionAnalytics::where('analyticsable_type', $subjectType)
            ->where('analyticsable_id', $subject->id)
            ->where('date', $today)
            ->first();

        $yesterday = now()->subDay()->toDateString();
        $yesterdayAnalytics = CollectionAnalytics::where('analyticsable_type', $subjectType)
            ->where('analyticsable_id', $subject->id)
            ->where('date', $yesterday)
            ->first();

        return [
            'today' => [
                'views' => $todayAnalytics->total_views ?? 0,
                'unique_views' => $todayAnalytics->unique_views ?? 0,
                'downloads' => $todayAnalytics->downloads ?? 0,
                'engagements' => ($todayAnalytics->saves ?? 0) + ($todayAnalytics->likes ?? 0) + ($todayAnalytics->comments ?? 0),
            ],
            'yesterday' => [
                'views' => $yesterdayAnalytics->total_views ?? 0,
                'unique_views' => $yesterdayAnalytics->unique_views ?? 0,
                'downloads' => $yesterdayAnalytics->downloads ?? 0,
                'engagements' => ($yesterdayAnalytics->saves ?? 0) + ($yesterdayAnalytics->likes ?? 0) + ($yesterdayAnalytics->comments ?? 0),
            ],
            'changes' => [
                'views' => $this->calculatePercentageChange(
                    $yesterdayAnalytics->total_views ?? 0,
                    $todayAnalytics->total_views ?? 0
                ),
                'downloads' => $this->calculatePercentageChange(
                    $yesterdayAnalytics->downloads ?? 0,
                    $todayAnalytics->downloads ?? 0
                ),
            ],
        ];
    }

    /**
     * Get comparative analytics between collections
     */
    public function getComparativeAnalytics(array $collectionIds, int $days = 30): array
    {
        $startDate = now()->subDays($days)->toDateString();
        
        $analytics = CollectionAnalytics::where('analyticsable_type', EnhancedCollection::class)
            ->whereIn('analyticsable_id', $collectionIds)
            ->where('date', '>=', $startDate)
            ->with('analyticsable:id,title')
            ->get()
            ->groupBy('analyticsable_id');

        $comparison = [];
        
        foreach ($analytics as $collectionId => $records) {
            $collection = $records->first()->analyticsable;
            
            $comparison[] = [
                'collection' => [
                    'id' => $collection->id,
                    'title' => $collection->title,
                ],
                'metrics' => [
                    'unique_views' => $records->sum('unique_views'),
                    'downloads' => $records->sum('downloads'),
                    'engagement_rate' => $this->calculateEngagementRate($records),
                    'average_rating' => round($records->whereNotNull('average_rating')->avg('average_rating'), 2),
                ],
            ];
        }

        // Sort by a combined performance score
        usort($comparison, function ($a, $b) {
            $scoreA = $a['metrics']['unique_views'] + ($a['metrics']['downloads'] * 2) + ($a['metrics']['engagement_rate'] * 10);
            $scoreB = $b['metrics']['unique_views'] + ($b['metrics']['downloads'] * 2) + ($b['metrics']['engagement_rate'] * 10);
            
            return $scoreB <=> $scoreA;
        });

        return $comparison;
    }

    /**
     * Get unique views for today
     */
    public function getUniqueViewsToday($subject): int
    {
        $today = now()->toDateString();
        $subjectType = get_class($subject);

        $analytics = CollectionAnalytics::where('analyticsable_type', $subjectType)
            ->where('analyticsable_id', $subject->id)
            ->where('date', $today)
            ->first();

        return $analytics->unique_views ?? 0;
    }

    /**
     * Get views trend for specified days
     */
    public function getViewsTrend($subject, int $days): array
    {
        $subjectType = get_class($subject);
        $startDate = now()->subDays($days)->toDateString();

        return CollectionAnalytics::where('analyticsable_type', $subjectType)
            ->where('analyticsable_id', $subject->id)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->pluck('total_views', 'date')
            ->toArray();
    }

    /**
     * Record a rating
     */
    protected function recordRating(CollectionAnalytics $analytics, float $rating): void
    {
        $analytics->increment('ratings');
        
        // Update average rating
        $currentAvg = $analytics->average_rating ?? 0;
        $currentCount = $analytics->ratings;
        
        $newAvg = (($currentAvg * ($currentCount - 1)) + $rating) / $currentCount;
        $analytics->update(['average_rating' => round($newAvg, 2)]);
    }

    /**
     * Update completion rate for learning path
     */
    protected function updateCompletionRate(CollectionAnalytics $analytics, LearningPath $learningPath): void
    {
        $totalEnrollments = $analytics->new_enrollments;
        $totalCompletions = $analytics->completions;
        
        if ($totalEnrollments > 0) {
            $completionRate = ($totalCompletions / $totalEnrollments) * 100;
            $analytics->update(['completion_rate' => round($completionRate, 2)]);
        }
    }

    /**
     * Update geographic data
     */
    protected function updateGeographicData(CollectionAnalytics $analytics, array $metadata): void
    {
        $countryBreakdown = $analytics->country_breakdown ?? [];
        $country = $metadata['country'];
        
        $countryBreakdown[$country] = ($countryBreakdown[$country] ?? 0) + 1;
        $analytics->update(['country_breakdown' => $countryBreakdown]);
    }

    /**
     * Update device/browser data
     */
    protected function updateDeviceData(CollectionAnalytics $analytics, string $userAgent): void
    {
        // Simple device detection (in production, use a proper library)
        $deviceType = $this->detectDeviceType($userAgent);
        $browser = $this->detectBrowser($userAgent);
        
        $deviceBreakdown = $analytics->device_breakdown ?? [];
        $browserBreakdown = $analytics->browser_breakdown ?? [];
        
        $deviceBreakdown[$deviceType] = ($deviceBreakdown[$deviceType] ?? 0) + 1;
        $browserBreakdown[$browser] = ($browserBreakdown[$browser] ?? 0) + 1;
        
        $analytics->update([
            'device_breakdown' => $deviceBreakdown,
            'browser_breakdown' => $browserBreakdown,
        ]);
    }

    /**
     * Update referrer data
     */
    protected function updateReferrerData(CollectionAnalytics $analytics, string $referrer): void
    {
        $domain = parse_url($referrer, PHP_URL_HOST);
        if (!$domain) return;

        $referrerBreakdown = $analytics->referrer_breakdown ?? [];
        $referrerBreakdown[$domain] = ($referrerBreakdown[$domain] ?? 0) + 1;
        
        $analytics->update(['referrer_breakdown' => $referrerBreakdown]);
    }

    /**
     * Calculate trends from analytics data
     */
    protected function calculateTrends($analytics): array
    {
        if ($analytics->count() < 2) {
            return ['views' => 0, 'downloads' => 0, 'engagement' => 0];
        }

        $firstHalf = $analytics->take(intval($analytics->count() / 2));
        $secondHalf = $analytics->skip(intval($analytics->count() / 2));

        return [
            'views' => $this->calculatePercentageChange(
                $firstHalf->avg('total_views'),
                $secondHalf->avg('total_views')
            ),
            'downloads' => $this->calculatePercentageChange(
                $firstHalf->avg('downloads'),
                $secondHalf->avg('downloads')
            ),
            'engagement' => $this->calculatePercentageChange(
                $firstHalf->sum('saves') + $firstHalf->sum('likes'),
                $secondHalf->sum('saves') + $secondHalf->sum('likes')
            ),
        ];
    }

    /**
     * Calculate learning-specific trends
     */
    protected function calculateLearningTrends($analytics): array
    {
        if ($analytics->count() < 2) {
            return ['enrollments' => 0, 'completion_rate' => 0];
        }

        $firstHalf = $analytics->take(intval($analytics->count() / 2));
        $secondHalf = $analytics->skip(intval($analytics->count() / 2));

        return [
            'enrollments' => $this->calculatePercentageChange(
                $firstHalf->avg('new_enrollments'),
                $secondHalf->avg('new_enrollments')
            ),
            'completion_rate' => $this->calculatePercentageChange(
                $firstHalf->avg('completion_rate'),
                $secondHalf->avg('completion_rate')
            ),
        ];
    }

    /**
     * Get top countries from analytics data
     */
    protected function getTopCountries($analytics): array
    {
        $allCountries = [];
        
        foreach ($analytics as $record) {
            if ($record->country_breakdown) {
                foreach ($record->country_breakdown as $country => $count) {
                    $allCountries[$country] = ($allCountries[$country] ?? 0) + $count;
                }
            }
        }

        arsort($allCountries);
        return array_slice($allCountries, 0, 5, true);
    }

    /**
     * Get top referrers from analytics data
     */
    protected function getTopReferrers($analytics): array
    {
        $allReferrers = [];
        
        foreach ($analytics as $record) {
            if ($record->referrer_breakdown) {
                foreach ($record->referrer_breakdown as $referrer => $count) {
                    $allReferrers[$referrer] = ($allReferrers[$referrer] ?? 0) + $count;
                }
            }
        }

        arsort($allReferrers);
        return array_slice($allReferrers, 0, 5, true);
    }

    /**
     * Calculate engagement rate
     */
    protected function calculateEngagementRate($analytics): float
    {
        $totalViews = $analytics->sum('total_views');
        $totalEngagements = $analytics->sum('saves') + $analytics->sum('likes') + $analytics->sum('comments');
        
        return $totalViews > 0 ? round(($totalEngagements / $totalViews) * 100, 2) : 0;
    }

    /**
     * Identify problem areas in learning path
     */
    protected function identifyProblemAreas(LearningPath $learningPath): array
    {
        // This would analyze step completion rates, time spent, etc.
        // Simplified implementation for now
        return [
            'high_dropout_steps' => [],
            'time_consuming_steps' => [],
            'low_rating_steps' => [],
        ];
    }

    /**
     * Calculate percentage change
     */
    protected function calculatePercentageChange($oldValue, $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 1);
    }

    /**
     * Simple device type detection
     */
    protected function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    /**
     * Simple browser detection
     */
    protected function detectBrowser(string $userAgent): string
    {
        if (preg_match('/chrome/i', $userAgent)) {
            return 'Chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            return 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            return 'Safari';
        } elseif (preg_match('/edge/i', $userAgent)) {
            return 'Edge';
        } else {
            return 'Other';
        }
    }
}