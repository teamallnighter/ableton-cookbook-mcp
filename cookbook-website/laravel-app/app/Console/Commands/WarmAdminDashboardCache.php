<?php

namespace App\Console\Commands;

use App\Services\AdminDashboardCacheService;
use App\Services\AdminAnalyticsService;
use App\Services\RackAnalyticsService;
use App\Services\EmailAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Artisan command to warm admin dashboard caches
 */
class WarmAdminDashboardCache extends Command
{
    protected $signature = 'admin:warm-cache 
                            {--sections=* : Specific sections to warm (overview,racks,email,users,system)}
                            {--force : Force refresh even if cached}
                            {--schedule : Run scheduled warming strategy}
                            {--stats : Show cache statistics after warming}';

    protected $description = 'Warm admin dashboard caches for better performance';

    protected AdminDashboardCacheService $cacheService;
    protected AdminAnalyticsService $adminAnalytics;
    protected RackAnalyticsService $rackAnalytics;
    protected EmailAnalyticsService $emailAnalytics;

    public function __construct(
        AdminDashboardCacheService $cacheService,
        AdminAnalyticsService $adminAnalytics,
        RackAnalyticsService $rackAnalytics,
        EmailAnalyticsService $emailAnalytics
    ) {
        parent::__construct();
        $this->cacheService = $cacheService;
        $this->adminAnalytics = $adminAnalytics;
        $this->rackAnalytics = $rackAnalytics;
        $this->emailAnalytics = $emailAnalytics;
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        
        $this->info('🚀 Starting admin dashboard cache warming...');
        
        if ($this->option('schedule')) {
            return $this->runScheduledWarming();
        }
        
        $sections = $this->option('sections');
        $force = $this->option('force');
        
        if (empty($sections)) {
            $sections = ['overview', 'racks', 'email', 'users', 'system'];
        }
        
        $this->line('');
        $this->info("📊 Warming cache for sections: " . implode(', ', $sections));
        
        if ($force) {
            $this->warn('⚡ Force mode enabled - clearing existing caches');
            $this->clearExistingCaches($sections);
        }
        
        $results = [];
        
        foreach ($sections as $section) {
            $this->line("");
            $this->info("🔄 Processing section: {$section}");
            
            try {
                $sectionResult = $this->warmSection($section);
                $results[$section] = $sectionResult;
                
                if ($sectionResult['success']) {
                    $this->info("  ✅ {$section} cache warmed successfully");
                    if (isset($sectionResult['duration'])) {
                        $this->line("  ⏱️  Duration: {$sectionResult['duration']}ms");
                    }
                    if (isset($sectionResult['size'])) {
                        $this->line("  📦 Data size: {$sectionResult['size']}");
                    }
                } else {
                    $this->error("  ❌ {$section} cache warming failed: {$sectionResult['error']}");
                }
                
            } catch (\Exception $e) {
                $this->error("  💥 Exception in {$section}: {$e->getMessage()}");
                $results[$section] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        $this->line('');
        $this->displaySummary($results, microtime(true) - $startTime);
        
        if ($this->option('stats')) {
            $this->line('');
            $this->displayCacheStats();
        }
        
        return Command::SUCCESS;
    }

    private function runScheduledWarming(): int
    {
        $this->info('⏰ Running scheduled cache warming...');
        
        try {
            $this->cacheService->scheduleWarmup();
            $this->info('✅ Scheduled warming initiated successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Scheduled warming failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function clearExistingCaches(array $sections): void
    {
        $keysCleared = 0;
        
        foreach ($sections as $section) {
            $keys = $this->getSectionCacheKeys($section);
            foreach ($keys as $key) {
                Cache::forget($key);
                $keysCleared++;
            }
        }
        
        $this->line("  🧹 Cleared {$keysCleared} cache keys");
    }

    private function warmSection(string $section): array
    {
        $startTime = microtime(true);
        $result = ['success' => false];
        
        try {
            switch ($section) {
                case 'overview':
                    $data = $this->adminAnalytics->getOverviewStats();
                    break;
                    
                case 'racks':
                    $data = $this->rackAnalytics->getRackStatistics();
                    // Also warm related rack analytics
                    $this->rackAnalytics->getUploadTrends(30);
                    $this->rackAnalytics->getCategoryAnalytics();
                    $this->rackAnalytics->getEngagementAnalytics();
                    break;
                    
                case 'email':
                    $data = $this->emailAnalytics->getEmailOverview();
                    // Also warm related email analytics
                    $this->emailAnalytics->getNewsletterAnalytics();
                    $this->emailAnalytics->getSubscriberAnalytics();
                    break;
                    
                case 'users':
                    $data = $this->adminAnalytics->getUserAnalytics();
                    break;
                    
                case 'system':
                    $data = $this->adminAnalytics->getHealthMetrics();
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Unknown section: {$section}");
            }
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $dataSize = $this->formatDataSize(strlen(json_encode($data)));
            
            $result = [
                'success' => true,
                'duration' => $duration,
                'size' => $dataSize,
                'data_points' => $this->countDataPoints($data),
            ];
            
        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
        
        return $result;
    }

    private function getSectionCacheKeys(string $section): array
    {
        $baseKeys = [
            'overview' => [
                'admin.analytics.overview',
                'admin.analytics.timeseries.30d',
                'admin.analytics.top_performers',
            ],
            'racks' => [
                'rack.analytics.statistics',
                'rack.analytics.upload_trends.30d',
                'rack.analytics.categories',
                'rack.analytics.devices',
                'rack.analytics.engagement',
                'rack.analytics.performance',
                'rack.analytics.processing',
            ],
            'email' => [
                'email.analytics.overview',
                'email.analytics.newsletter',
                'email.analytics.transactional',
                'email.analytics.deliverability',
                'email.analytics.trends.30d',
                'email.analytics.subscribers',
                'email.analytics.automation',
            ],
            'users' => [
                'admin.analytics.users',
            ],
            'system' => [
                'admin.analytics.health',
            ],
        ];
        
        return $baseKeys[$section] ?? [];
    }

    private function displaySummary(array $results, float $totalTime): void
    {
        $this->info('📈 Cache Warming Summary');
        $this->line('========================');
        
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);
        
        $this->line("✅ Successful: " . count($successful));
        $this->line("❌ Failed: " . count($failed));
        $this->line("⏱️  Total time: " . round($totalTime * 1000, 2) . "ms");
        
        if (!empty($successful)) {
            $this->line('');
            $this->info('Successful sections:');
            foreach ($successful as $section => $result) {
                $this->line("  • {$section}: {$result['size']} in {$result['duration']}ms");
            }
        }
        
        if (!empty($failed)) {
            $this->line('');
            $this->error('Failed sections:');
            foreach ($failed as $section => $result) {
                $this->line("  • {$section}: {$result['error']}");
            }
        }
    }

    private function displayCacheStats(): void
    {
        $this->info('📊 Cache Statistics');
        $this->line('==================');
        
        try {
            $stats = $this->cacheService->getCacheStats();
            
            $this->line("Memory Usage: " . ($stats['memory_usage']['used_memory_human'] ?? 'N/A'));
            $this->line("Hit Rate: {$stats['hit_rate']}%");
            $this->line("Key Count: " . number_format($stats['key_count']));
            $this->line("Health Score: {$stats['health_score']}/10");
            
            if (!empty($stats['top_keys'])) {
                $this->line('');
                $this->info('Top Cache Keys:');
                foreach (array_slice($stats['top_keys'], 0, 5) as $key => $data) {
                    $this->line("  • {$key}: {$data['hits']} hits, {$data['size']}");
                }
            }
            
            if (!empty($stats['expiring_soon'])) {
                $this->line('');
                $this->warn('Keys Expiring Soon:');
                foreach ($stats['expiring_soon'] as $key => $data) {
                    $this->line("  ⚠️  {$key}: {$data['expires_in']}s");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to retrieve cache stats: {$e->getMessage()}");
        }
    }

    private function formatDataSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . $units[$i];
    }

    private function countDataPoints($data): int
    {
        if (is_array($data)) {
            return array_sum(array_map([$this, 'countDataPoints'], $data));
        }
        
        return 1;
    }
}