<?php

namespace App\Console\Commands;

use App\Services\AdminAnalyticsService;
use App\Services\RackAnalyticsService;
use App\Services\EmailAnalyticsService;
use App\Services\AdminDashboardCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Performance testing command for admin dashboard
 */
class TestDashboardPerformance extends Command
{
    protected $signature = 'admin:test-performance 
                            {--iterations=10 : Number of test iterations}
                            {--cache : Test with cache enabled}
                            {--no-cache : Test without cache}
                            {--section=* : Specific sections to test}
                            {--detailed : Show detailed performance breakdown}
                            {--export= : Export results to file}';

    protected $description = 'Test admin dashboard performance and identify optimization opportunities';

    protected AdminAnalyticsService $adminAnalytics;
    protected RackAnalyticsService $rackAnalytics;
    protected EmailAnalyticsService $emailAnalytics;
    protected AdminDashboardCacheService $cacheService;

    public function __construct(
        AdminAnalyticsService $adminAnalytics,
        RackAnalyticsService $rackAnalytics,
        EmailAnalyticsService $emailAnalytics,
        AdminDashboardCacheService $cacheService
    ) {
        parent::__construct();
        $this->adminAnalytics = $adminAnalytics;
        $this->rackAnalytics = $rackAnalytics;
        $this->emailAnalytics = $emailAnalytics;
        $this->cacheService = $cacheService;
    }

    public function handle(): int
    {
        $this->info('🚀 Starting admin dashboard performance tests...');
        
        $iterations = (int) $this->option('iterations');
        $testCache = $this->option('cache');
        $testNoCache = $this->option('no-cache');
        $sections = $this->option('section') ?: ['overview', 'racks', 'email', 'users', 'system'];
        $detailed = $this->option('detailed');
        
        // If neither cache option is specified, test both
        $cacheScenarios = [];
        if (!$testCache && !$testNoCache) {
            $cacheScenarios = ['cached', 'uncached'];
        } elseif ($testCache) {
            $cacheScenarios = ['cached'];
        } else {
            $cacheScenarios = ['uncached'];
        }
        
        $results = [];
        
        foreach ($cacheScenarios as $scenario) {
            $this->line("");
            $this->info("📊 Testing scenario: {$scenario}");
            
            $results[$scenario] = $this->runPerformanceTests($sections, $iterations, $scenario === 'cached', $detailed);
        }
        
        $this->displayResults($results, $detailed);
        
        if ($exportPath = $this->option('export')) {
            $this->exportResults($results, $exportPath);
        }
        
        $this->generateRecommendations($results);
        
        return Command::SUCCESS;
    }

    private function runPerformanceTests(array $sections, int $iterations, bool $useCache, bool $detailed): array
    {
        $results = [];
        
        foreach ($sections as $section) {
            $this->line("  🔄 Testing section: {$section}");
            
            $sectionResults = [];
            $queryCountResults = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                if (!$useCache) {
                    // Clear cache for this test
                    Cache::flush();
                }
                
                // Reset query log
                DB::enableQueryLog();
                DB::flushQueryLog();
                
                $startTime = microtime(true);
                $startMemory = memory_get_usage(true);
                
                try {
                    $data = $this->runSectionTest($section);
                    
                    $endTime = microtime(true);
                    $endMemory = memory_get_usage(true);
                    
                    $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
                    $memoryUsage = $endMemory - $startMemory;
                    $queryCount = count(DB::getQueryLog());
                    
                    $sectionResults[] = [
                        'duration' => $duration,
                        'memory' => $memoryUsage,
                        'queries' => $queryCount,
                        'data_size' => strlen(json_encode($data)),
                        'success' => true,
                    ];
                    
                    if ($detailed && $i === 0) {
                        $queryCountResults = $this->analyzeQueries(DB::getQueryLog());
                    }
                    
                } catch (\Exception $e) {
                    $sectionResults[] = [
                        'error' => $e->getMessage(),
                        'success' => false,
                    ];
                    $this->error("    ❌ Iteration " . ($i + 1) . " failed: {$e->getMessage()}");
                }
            }
            
            $successfulResults = array_filter($sectionResults, fn($r) => $r['success']);
            
            if (!empty($successfulResults)) {
                $results[$section] = [
                    'avg_duration' => round(array_sum(array_column($successfulResults, 'duration')) / count($successfulResults), 2),
                    'min_duration' => round(min(array_column($successfulResults, 'duration')), 2),
                    'max_duration' => round(max(array_column($successfulResults, 'duration')), 2),
                    'avg_memory' => round(array_sum(array_column($successfulResults, 'memory')) / count($successfulResults), 2),
                    'avg_queries' => round(array_sum(array_column($successfulResults, 'queries')) / count($successfulResults), 1),
                    'avg_data_size' => round(array_sum(array_column($successfulResults, 'data_size')) / count($successfulResults), 2),
                    'success_rate' => (count($successfulResults) / $iterations) * 100,
                    'iterations' => $iterations,
                    'query_analysis' => $queryCountResults,
                ];
                
                $this->line("    ✅ Avg: {$results[$section]['avg_duration']}ms, Queries: {$results[$section]['avg_queries']}, Memory: {$this->formatBytes($results[$section]['avg_memory'])}");
            } else {
                $results[$section] = [
                    'success_rate' => 0,
                    'error' => 'All iterations failed',
                ];
                $this->error("    ❌ All iterations failed for {$section}");
            }
        }
        
        return $results;
    }

    private function runSectionTest(string $section)
    {
        return match ($section) {
            'overview' => $this->adminAnalytics->getOverviewStats(),
            'racks' => $this->rackAnalytics->getRackStatistics(),
            'email' => $this->emailAnalytics->getEmailOverview(),
            'users' => $this->adminAnalytics->getUserAnalytics(),
            'system' => $this->adminAnalytics->getHealthMetrics(),
            'rack-trends' => $this->rackAnalytics->getUploadTrends(30),
            'rack-devices' => $this->rackAnalytics->getDeviceAnalytics(),
            'email-trends' => $this->emailAnalytics->getEmailTrends(30),
            default => throw new \InvalidArgumentException("Unknown section: {$section}")
        };
    }

    private function analyzeQueries(array $queries): array
    {
        $analysis = [
            'total' => count($queries),
            'slow_queries' => [],
            'duplicate_queries' => [],
            'table_access' => [],
            'query_types' => [],
        ];
        
        $queryHashes = [];
        
        foreach ($queries as $query) {
            $time = $query['time'];
            $sql = $query['query'];
            
            // Identify slow queries (>100ms)
            if ($time > 100) {
                $analysis['slow_queries'][] = [
                    'sql' => $sql,
                    'time' => $time,
                    'bindings' => $query['bindings'],
                ];
            }
            
            // Track duplicate queries
            $normalizedSql = preg_replace('/\?/', 'PARAM', $sql);
            $hash = md5($normalizedSql);
            
            if (isset($queryHashes[$hash])) {
                $queryHashes[$hash]['count']++;
            } else {
                $queryHashes[$hash] = [
                    'sql' => $normalizedSql,
                    'count' => 1,
                    'total_time' => $time,
                ];
            }
            
            // Analyze table access patterns
            preg_match_all('/(?:FROM|JOIN)\s+`?(\w+)`?/i', $sql, $matches);
            foreach ($matches[1] as $table) {
                $analysis['table_access'][$table] = ($analysis['table_access'][$table] ?? 0) + 1;
            }
            
            // Query types
            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE)/i', $sql, $matches)) {
                $type = strtoupper($matches[1]);
                $analysis['query_types'][$type] = ($analysis['query_types'][$type] ?? 0) + 1;
            }
        }
        
        // Find duplicates (queries executed more than once)
        $analysis['duplicate_queries'] = array_filter($queryHashes, fn($q) => $q['count'] > 1);
        
        return $analysis;
    }

    private function displayResults(array $results, bool $detailed): void
    {
        $this->line('');
        $this->info('📈 Performance Test Results');
        $this->line('============================');
        
        foreach ($results as $scenario => $sections) {
            $this->line('');
            $this->info("Scenario: " . strtoupper($scenario));
            $this->line(str_repeat('-', 40));
            
            $table = [];
            $headers = ['Section', 'Avg Time (ms)', 'Min Time (ms)', 'Max Time (ms)', 'Queries', 'Memory', 'Success Rate'];
            
            foreach ($sections as $section => $data) {
                if ($data['success_rate'] > 0) {
                    $table[] = [
                        $section,
                        $data['avg_duration'],
                        $data['min_duration'],
                        $data['max_duration'],
                        $data['avg_queries'],
                        $this->formatBytes($data['avg_memory']),
                        $data['success_rate'] . '%',
                    ];
                } else {
                    $table[] = [
                        $section,
                        'FAILED',
                        '-',
                        '-',
                        '-',
                        '-',
                        '0%',
                    ];
                }
            }
            
            $this->table($headers, $table);
            
            if ($detailed) {
                $this->displayDetailedAnalysis($sections);
            }
        }
        
        // Performance comparison
        if (count($results) > 1) {
            $this->displayPerformanceComparison($results);
        }
    }

    private function displayDetailedAnalysis(array $sections): void
    {
        foreach ($sections as $section => $data) {
            if ($data['success_rate'] === 0 || empty($data['query_analysis'])) {
                continue;
            }
            
            $analysis = $data['query_analysis'];
            
            $this->line('');
            $this->warn("Detailed Analysis: {$section}");
            
            if (!empty($analysis['slow_queries'])) {
                $this->line("  🐌 Slow Queries (" . count($analysis['slow_queries']) . "):");
                foreach (array_slice($analysis['slow_queries'], 0, 3) as $query) {
                    $this->line("    • {$query['time']}ms: " . substr($query['sql'], 0, 80) . '...');
                }
            }
            
            if (!empty($analysis['duplicate_queries'])) {
                $this->line("  🔄 Duplicate Queries:");
                foreach (array_slice($analysis['duplicate_queries'], 0, 3, true) as $query) {
                    $this->line("    • {$query['count']}x: " . substr($query['sql'], 0, 60) . '...');
                }
            }
            
            if (!empty($analysis['table_access'])) {
                $topTables = array_slice(arsort($analysis['table_access']) ? $analysis['table_access'] : [], 0, 5, true);
                $this->line("  📊 Most Accessed Tables: " . implode(', ', array_map(
                    fn($table, $count) => "{$table}({$count})",
                    array_keys($topTables),
                    array_values($topTables)
                )));
            }
        }
    }

    private function displayPerformanceComparison(array $results): void
    {
        if (!isset($results['cached']) || !isset($results['uncached'])) {
            return;
        }
        
        $this->line('');
        $this->info('🔄 Performance Comparison (Cached vs Uncached)');
        $this->line('================================================');
        
        $table = [];
        $headers = ['Section', 'Cached (ms)', 'Uncached (ms)', 'Improvement', 'Cache Hit Impact'];
        
        foreach ($results['cached'] as $section => $cachedData) {
            if (!isset($results['uncached'][$section])) {
                continue;
            }
            
            $uncachedData = $results['uncached'][$section];
            
            if ($cachedData['success_rate'] > 0 && $uncachedData['success_rate'] > 0) {
                $improvement = round((($uncachedData['avg_duration'] - $cachedData['avg_duration']) / $uncachedData['avg_duration']) * 100, 1);
                $queryReduction = $uncachedData['avg_queries'] - $cachedData['avg_queries'];
                
                $table[] = [
                    $section,
                    $cachedData['avg_duration'],
                    $uncachedData['avg_duration'],
                    ($improvement > 0 ? '+' : '') . $improvement . '%',
                    "-{$queryReduction} queries",
                ];
            }
        }
        
        $this->table($headers, $table);
    }

    private function exportResults(array $results, string $path): void
    {
        $exportData = [
            'test_metadata' => [
                'timestamp' => now()->toISOString(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'environment' => app()->environment(),
            ],
            'results' => $results,
            'system_info' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'cache_driver' => config('cache.default'),
                'database_driver' => config('database.default'),
            ],
        ];
        
        file_put_contents($path, json_encode($exportData, JSON_PRETTY_PRINT));
        $this->info("📄 Results exported to: {$path}");
    }

    private function generateRecommendations(array $results): void
    {
        $this->line('');
        $this->info('💡 Performance Recommendations');
        $this->line('==============================');
        
        $recommendations = [];
        
        // Analyze cache impact
        if (isset($results['cached']) && isset($results['uncached'])) {
            $cacheImpact = $this->analyzeCacheImpact($results['cached'], $results['uncached']);
            if ($cacheImpact['significant_improvement']) {
                $recommendations[] = "✅ Caching is working well (avg {$cacheImpact['avg_improvement']}% improvement)";
            } else {
                $recommendations[] = "⚠️ Consider optimizing cache strategy (only {$cacheImpact['avg_improvement']}% improvement)";
            }
        }
        
        // Analyze query patterns
        foreach ($results as $scenario => $sections) {
            foreach ($sections as $section => $data) {
                if ($data['success_rate'] === 0) {
                    continue;
                }
                
                // High query count
                if ($data['avg_queries'] > 50) {
                    $recommendations[] = "🔍 {$section} ({$scenario}): High query count ({$data['avg_queries']}) - consider query optimization";
                }
                
                // Slow response time
                if ($data['avg_duration'] > 1000) {
                    $recommendations[] = "🐌 {$section} ({$scenario}): Slow response time ({$data['avg_duration']}ms) - needs optimization";
                }
                
                // High memory usage
                if ($data['avg_memory'] > 10 * 1024 * 1024) { // 10MB
                    $recommendations[] = "💾 {$section} ({$scenario}): High memory usage ({$this->formatBytes($data['avg_memory'])}) - optimize data loading";
                }
                
                // Analyze query issues
                if (!empty($data['query_analysis']['slow_queries'])) {
                    $recommendations[] = "⚡ {$section}: Has slow queries - add database indexes or optimize queries";
                }
                
                if (!empty($data['query_analysis']['duplicate_queries'])) {
                    $recommendations[] = "🔄 {$section}: Has duplicate queries - implement query result caching";
                }
            }
        }
        
        // General recommendations
        $recommendations[] = "📊 Run this test regularly to monitor performance trends";
        $recommendations[] = "🔧 Consider implementing query result caching for expensive operations";
        $recommendations[] = "📈 Use database query profiling to identify bottlenecks";
        
        foreach ($recommendations as $recommendation) {
            $this->line("  {$recommendation}");
        }
    }

    private function analyzeCacheImpact(array $cached, array $uncached): array
    {
        $improvements = [];
        
        foreach ($cached as $section => $cachedData) {
            if (!isset($uncached[$section]) || $cachedData['success_rate'] === 0 || $uncached[$section]['success_rate'] === 0) {
                continue;
            }
            
            $uncachedData = $uncached[$section];
            $improvement = (($uncachedData['avg_duration'] - $cachedData['avg_duration']) / $uncachedData['avg_duration']) * 100;
            $improvements[] = $improvement;
        }
        
        $avgImprovement = empty($improvements) ? 0 : round(array_sum($improvements) / count($improvements), 1);
        
        return [
            'avg_improvement' => $avgImprovement,
            'significant_improvement' => $avgImprovement > 30,
            'individual_improvements' => $improvements,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . $units[$i];
    }
}