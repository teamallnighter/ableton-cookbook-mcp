<?php

namespace App\Console\Commands;

use App\Models\EnhancedRackAnalysis;
use App\Models\NestedChain;
use App\Models\Rack;
use App\Services\ConstitutionalComplianceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class EnhancedAnalysisHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'enhanced-analysis:health-check
                           {--detailed : Show detailed diagnostics}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'Perform health check for Enhanced Nested Chain Analysis system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $checks = [];
        $overallHealthy = true;

        $this->info('🔍 Enhanced Nested Chain Analysis - Health Check');
        $this->info('Constitutional Version: 1.1.0');
        $this->newLine();

        // 1. Database Health Check
        $this->info('📊 Database Health...');
        $dbCheck = $this->checkDatabase();
        $checks['database'] = $dbCheck;
        if (!$dbCheck['healthy']) $overallHealthy = false;

        // 2. Queue System Health Check
        $this->info('⚡ Queue System Health...');
        $queueCheck = $this->checkQueueSystem();
        $checks['queue_system'] = $queueCheck;
        if (!$queueCheck['healthy']) $overallHealthy = false;

        // 3. Constitutional Compliance Health Check
        $this->info('⚖️ Constitutional Compliance Health...');
        $complianceCheck = $this->checkConstitutionalCompliance();
        $checks['constitutional_compliance'] = $complianceCheck;
        if (!$complianceCheck['healthy']) $overallHealthy = false;

        // 4. Performance Health Check
        $this->info('🚀 Performance Health...');
        $performanceCheck = $this->checkPerformance();
        $checks['performance'] = $performanceCheck;
        if (!$performanceCheck['healthy']) $overallHealthy = false;

        // 5. API Endpoints Health Check
        $this->info('🌐 API Endpoints Health...');
        $apiCheck = $this->checkApiEndpoints();
        $checks['api_endpoints'] = $apiCheck;
        if (!$apiCheck['healthy']) $overallHealthy = false;

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Output results
        if ($this->option('json')) {
            $this->line(json_encode([
                'overall_healthy' => $overallHealthy,
                'checks' => $checks,
                'duration_ms' => $duration,
                'timestamp' => now()->toISOString()
            ], JSON_PRETTY_PRINT));
        } else {
            $this->displayResults($checks, $overallHealthy, $duration);
        }

        return $overallHealthy ? 0 : 1;
    }

    private function checkDatabase(): array
    {
        $result = ['healthy' => true, 'details' => [], 'issues' => []];

        try {
            // Check required tables exist
            $requiredTables = ['enhanced_rack_analysis', 'nested_chains', 'racks'];
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    $result['healthy'] = false;
                    $result['issues'][] = "Missing table: {$table}";
                } else {
                    $result['details'][] = "Table {$table}: ✅ EXISTS";
                }
            }

            // Check data integrity
            $enhancedCount = EnhancedRackAnalysis::count();
            $chainCount = NestedChain::count();
            $rackCount = Rack::count();

            $result['details'][] = "Enhanced analyses: {$enhancedCount}";
            $result['details'][] = "Nested chains: {$chainCount}";
            $result['details'][] = "Total racks: {$rackCount}";

            // Check for orphaned records
            $orphanedAnalyses = DB::table('enhanced_rack_analysis')
                ->leftJoin('racks', 'enhanced_rack_analysis.rack_id', '=', 'racks.id')
                ->whereNull('racks.id')
                ->count();

            if ($orphanedAnalyses > 0) {
                $result['issues'][] = "{$orphanedAnalyses} orphaned enhanced analyses found";
            }

            // Check database connection performance
            $dbStart = microtime(true);
            DB::select('SELECT 1');
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);

            if ($dbDuration > 100) {
                $result['issues'][] = "Slow database response: {$dbDuration}ms";
            }
            $result['details'][] = "Database response time: {$dbDuration}ms";

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['issues'][] = "Database error: " . $e->getMessage();
        }

        return $result;
    }

    private function checkQueueSystem(): array
    {
        $result = ['healthy' => true, 'details' => [], 'issues' => []];

        try {
            // Check queue connection
            $queueSize = Queue::size();
            $result['details'][] = "Default queue size: {$queueSize}";

            // Check batch processing queues
            $batchQueues = ['batch-reprocess-high', 'batch-reprocess-normal', 'batch-reprocess-low'];
            foreach ($batchQueues as $queue) {
                try {
                    $size = Queue::size($queue);
                    $result['details'][] = "Queue {$queue}: {$size} jobs";
                } catch (\Exception $e) {
                    $result['issues'][] = "Queue {$queue} not accessible: " . $e->getMessage();
                }
            }

            // Check failed jobs
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 10) {
                $result['issues'][] = "{$failedJobs} failed jobs in queue";
            } else {
                $result['details'][] = "Failed jobs: {$failedJobs}";
            }

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['issues'][] = "Queue system error: " . $e->getMessage();
        }

        return $result;
    }

    private function checkConstitutionalCompliance(): array
    {
        $result = ['healthy' => true, 'details' => [], 'issues' => []];

        try {
            // Check constitutional compliance service
            $complianceService = app(ConstitutionalComplianceService::class);
            $requirements = $complianceService->getConstitutionalRequirements();

            $result['details'][] = "Constitutional version: 1.1.0";
            $result['details'][] = "Requirements loaded: " . count($requirements);

            // Check compliance rates
            $totalAnalyses = EnhancedRackAnalysis::count();
            $compliantAnalyses = EnhancedRackAnalysis::where('constitutional_compliant', true)->count();

            if ($totalAnalyses > 0) {
                $complianceRate = round(($compliantAnalyses / $totalAnalyses) * 100, 2);
                $result['details'][] = "Compliance rate: {$complianceRate}% ({$compliantAnalyses}/{$totalAnalyses})";

                if ($complianceRate < 90) {
                    $result['issues'][] = "Low compliance rate: {$complianceRate}%";
                }
            } else {
                $result['details'][] = "No analyses to check compliance";
            }

            // Check recent analyses for constitutional adherence
            $recentAnalyses = EnhancedRackAnalysis::where('created_at', '>=', now()->subHours(24))->count();
            $result['details'][] = "Recent analyses (24h): {$recentAnalyses}";

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['issues'][] = "Constitutional compliance error: " . $e->getMessage();
        }

        return $result;
    }

    private function checkPerformance(): array
    {
        $result = ['healthy' => true, 'details' => [], 'issues' => []];

        try {
            // Check cache system
            $cacheKey = 'health_check_' . time();
            $cacheStart = microtime(true);
            Cache::put($cacheKey, 'test', 60);
            $cacheValue = Cache::get($cacheKey);
            Cache::forget($cacheKey);
            $cacheDuration = round((microtime(true) - $cacheStart) * 1000, 2);

            if ($cacheValue !== 'test') {
                $result['healthy'] = false;
                $result['issues'][] = "Cache system not working properly";
            } else {
                $result['details'][] = "Cache response time: {$cacheDuration}ms";
                if ($cacheDuration > 50) {
                    $result['issues'][] = "Slow cache response: {$cacheDuration}ms";
                }
            }

            // Check average analysis performance
            $avgAnalysisTime = EnhancedRackAnalysis::where('created_at', '>=', now()->subDays(7))
                ->avg('analysis_duration_ms');

            if ($avgAnalysisTime) {
                $avgAnalysisSeconds = round($avgAnalysisTime / 1000, 2);
                $result['details'][] = "Avg analysis time (7d): {$avgAnalysisSeconds}s";

                // Constitutional requirement: sub-5-second analysis
                if ($avgAnalysisSeconds > 5) {
                    $result['issues'][] = "Analysis time exceeds constitutional limit: {$avgAnalysisSeconds}s > 5s";
                }
            }

            // Check memory usage
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $result['details'][] = "Current memory usage: {$memoryUsage}MB";

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['issues'][] = "Performance check error: " . $e->getMessage();
        }

        return $result;
    }

    private function checkApiEndpoints(): array
    {
        $result = ['healthy' => true, 'details' => [], 'issues' => []];

        try {
            // Count available API endpoints
            $routes = app('router')->getRoutes();
            $enhancedAnalysisRoutes = 0;
            $complianceRoutes = 0;

            foreach ($routes as $route) {
                $uri = $route->uri();
                if (str_contains($uri, 'api/v1/analysis/')) {
                    $enhancedAnalysisRoutes++;
                }
                if (str_contains($uri, 'api/v1/compliance/')) {
                    $complianceRoutes++;
                }
            }

            $result['details'][] = "Enhanced analysis routes: {$enhancedAnalysisRoutes}";
            $result['details'][] = "Compliance routes: {$complianceRoutes}";

            // Expected route counts based on our implementation
            if ($enhancedAnalysisRoutes < 10) {
                $result['issues'][] = "Missing enhanced analysis routes (expected 10+, found {$enhancedAnalysisRoutes})";
            }
            if ($complianceRoutes < 7) {
                $result['issues'][] = "Missing compliance routes (expected 7+, found {$complianceRoutes})";
            }

        } catch (\Exception $e) {
            $result['healthy'] = false;
            $result['issues'][] = "API endpoints check error: " . $e->getMessage();
        }

        return $result;
    }

    private function displayResults(array $checks, bool $overallHealthy, float $duration): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════');

        if ($overallHealthy) {
            $this->info('✅ OVERALL SYSTEM STATUS: HEALTHY');
        } else {
            $this->error('❌ OVERALL SYSTEM STATUS: ISSUES DETECTED');
        }

        $this->line("🕒 Health check completed in {$duration}ms");
        $this->line('═══════════════════════════════════════════════════');

        foreach ($checks as $component => $check) {
            $this->newLine();
            $status = $check['healthy'] ? '✅ HEALTHY' : '❌ ISSUES';
            $this->line("🔧 " . strtoupper(str_replace('_', ' ', $component)) . ": {$status}");

            if ($this->option('detailed') && !empty($check['details'])) {
                foreach ($check['details'] as $detail) {
                    $this->line("   ℹ️  {$detail}");
                }
            }

            if (!empty($check['issues'])) {
                foreach ($check['issues'] as $issue) {
                    $this->line("   ⚠️  {$issue}");
                }
            }
        }

        $this->newLine();
        if (!$overallHealthy) {
            $this->error('🚨 Action required: Please resolve the issues above');
            $this->line('📋 For support, check logs in storage/logs/ or contact system administrators');
        } else {
            $this->info('🎉 Enhanced Nested Chain Analysis system is operating normally');
        }
    }
}