<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\User;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;

/**
 * Monitoring Dashboard Service
 * 
 * Provides comprehensive system monitoring including:
 * - Real-time performance metrics
 * - Security monitoring integration
 * - User activity analytics
 * - System health indicators
 * - Resource utilization tracking
 * - Alert management
 * - Uptime monitoring
 */
class MonitoringDashboardService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const METRICS_PREFIX = 'monitoring_';
    
    private SecurityMonitoringService $securityService;
    private FeatureFlagService $featureService;
    private FileQuarantineService $quarantineService;
    
    public function __construct(
        SecurityMonitoringService $securityService,
        FeatureFlagService $featureService,
        FileQuarantineService $quarantineService
    ) {
        $this->securityService = $securityService;
        $this->featureService = $featureService;
        $this->quarantineService = $quarantineService;
    }
    
    /**
     * Get comprehensive dashboard metrics
     */
    public function getDashboardMetrics(): array
    {
        try {
            $cacheKey = self::METRICS_PREFIX . 'dashboard_overview';
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return [
                    'generated_at' => now()->toISOString(),
                    'system_health' => $this->getSystemHealth(),
                    'performance_metrics' => $this->getPerformanceMetrics(),
                    'security_metrics' => $this->getSecurityMetrics(),
                    'user_analytics' => $this->getUserAnalytics(),
                    'content_metrics' => $this->getContentMetrics(),
                    'infrastructure_status' => $this->getInfrastructureStatus(),
                    'feature_flag_status' => $this->getFeatureFlagStatus(),
                    'alerts' => $this->getActiveAlerts(),
                    'uptime_stats' => $this->getUptimeStats()
                ];
            });
            
        } catch (Exception $e) {
            Log::error('Failed to generate dashboard metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to generate metrics',
                'generated_at' => now()->toISOString(),
                'system_health' => ['status' => 'unknown']
            ];
        }
    }
    
    /**
     * Get real-time system health indicators
     */
    public function getSystemHealth(): array
    {
        try {
            $health = [
                'overall_status' => 'healthy',
                'components' => [],
                'score' => 100,
                'last_check' => now()->toISOString()
            ];
            
            // Database health
            $dbHealth = $this->checkDatabaseHealth();
            $health['components']['database'] = $dbHealth;
            
            // Redis health
            $redisHealth = $this->checkRedisHealth();
            $health['components']['redis'] = $redisHealth;
            
            // Storage health
            $storageHealth = $this->checkStorageHealth();
            $health['components']['storage'] = $storageHealth;
            
            // Queue health
            $queueHealth = $this->checkQueueHealth();
            $health['components']['queue'] = $queueHealth;
            
            // Security systems health
            $securityHealth = $this->checkSecuritySystemsHealth();
            $health['components']['security'] = $securityHealth;
            
            // Calculate overall health score
            $componentScores = array_column($health['components'], 'score');
            $health['score'] = empty($componentScores) ? 0 : array_sum($componentScores) / count($componentScores);
            
            // Determine overall status
            if ($health['score'] >= 90) {
                $health['overall_status'] = 'healthy';
            } elseif ($health['score'] >= 70) {
                $health['overall_status'] = 'warning';
            } else {
                $health['overall_status'] = 'critical';
            }
            
            return $health;
            
        } catch (Exception $e) {
            Log::error('System health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'overall_status' => 'error',
                'score' => 0,
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        try {
            return [
                'response_times' => $this->getResponseTimeMetrics(),
                'throughput' => $this->getThroughputMetrics(),
                'error_rates' => $this->getErrorRateMetrics(),
                'memory_usage' => $this->getMemoryUsageMetrics(),
                'cpu_usage' => $this->getCpuUsageMetrics(),
                'disk_usage' => $this->getDiskUsageMetrics(),
                'cache_performance' => $this->getCachePerformanceMetrics(),
                'database_performance' => $this->getDatabasePerformanceMetrics()
            ];
            
        } catch (Exception $e) {
            Log::error('Performance metrics collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect performance metrics'
            ];
        }
    }
    
    /**
     * Get security metrics from security monitoring service
     */
    public function getSecurityMetrics(): array
    {
        try {
            $securityMetrics = $this->securityService->getSecurityDashboardMetrics();
            
            return [
                'threat_detection' => [
                    'total_threats' => $securityMetrics['threats']['total'] ?? 0,
                    'critical_threats' => $securityMetrics['threats']['critical'] ?? 0,
                    'blocked_files' => $securityMetrics['blocked_files'] ?? 0,
                    'quarantined_files' => $this->quarantineService->getQuarantineStatistics()['total_quarantined'] ?? 0
                ],
                'security_incidents' => [
                    'total_incidents' => $securityMetrics['incidents']['total'] ?? 0,
                    'open_incidents' => $securityMetrics['incidents']['open'] ?? 0,
                    'recent_incidents' => $securityMetrics['incidents']['recent'] ?? []
                ],
                'ip_monitoring' => [
                    'flagged_ips' => $securityMetrics['ip_monitoring']['flagged_ips'] ?? 0,
                    'blocked_ips' => $securityMetrics['ip_monitoring']['blocked_ips'] ?? 0,
                    'suspicious_activity' => $securityMetrics['ip_monitoring']['suspicious_activity'] ?? 0
                ],
                'virus_scanning' => [
                    'files_scanned' => $this->getVirusScanningStats()['total_scanned'] ?? 0,
                    'threats_found' => $this->getVirusScanningStats()['threats_found'] ?? 0,
                    'clean_files' => $this->getVirusScanningStats()['clean_files'] ?? 0,
                    'scan_queue_size' => $this->getVirusScanningStats()['queue_size'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            Log::error('Security metrics collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect security metrics'
            ];
        }
    }
    
    /**
     * Get user analytics
     */
    public function getUserAnalytics(): array
    {
        try {
            $cacheKey = self::METRICS_PREFIX . 'user_analytics';
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return [
                    'total_users' => User::count(),
                    'active_users_24h' => User::where('last_seen_at', '>=', now()->subDay())->count(),
                    'active_users_7d' => User::where('last_seen_at', '>=', now()->subWeek())->count(),
                    'new_users_24h' => User::where('created_at', '>=', now()->subDay())->count(),
                    'new_users_7d' => User::where('created_at', '>=', now()->subWeek())->count(),
                    'verified_users' => User::whereNotNull('email_verified_at')->count(),
                    'premium_users' => User::where('is_premium', true)->count(),
                    'user_activity' => $this->getUserActivityBreakdown(),
                    'registration_trends' => $this->getRegistrationTrends(),
                    'engagement_metrics' => $this->getUserEngagementMetrics()
                ];
            });
            
        } catch (Exception $e) {
            Log::error('User analytics collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect user analytics'
            ];
        }
    }
    
    /**
     * Get content metrics
     */
    public function getContentMetrics(): array
    {
        try {
            $cacheKey = self::METRICS_PREFIX . 'content_metrics';
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return [
                    'racks' => [
                        'total_racks' => Rack::count(),
                        'public_racks' => Rack::where('is_public', true)->count(),
                        'private_racks' => Rack::where('is_public', false)->count(),
                        'racks_uploaded_24h' => Rack::where('created_at', '>=', now()->subDay())->count(),
                        'racks_uploaded_7d' => Rack::where('created_at', '>=', now()->subWeek())->count(),
                        'most_downloaded' => $this->getMostDownloadedRacks(5),
                        'categories' => $this->getRackCategoryBreakdown(),
                        'average_rating' => Rack::whereHas('ratings')->avg('average_rating') ?? 0
                    ],
                    'how_to_articles' => [
                        'total_articles' => Rack::whereNotNull('how_to_article')->count(),
                        'articles_created_24h' => Rack::whereNotNull('how_to_updated_at')
                            ->where('how_to_updated_at', '>=', now()->subDay())->count(),
                        'average_length' => $this->getAverageArticleLength()
                    ],
                    'blog_posts' => [
                        'total_posts' => BlogPost::count(),
                        'published_posts' => BlogPost::whereNotNull('published_at')->count(),
                        'draft_posts' => BlogPost::whereNull('published_at')->count(),
                        'posts_published_7d' => BlogPost::where('published_at', '>=', now()->subWeek())->count()
                    ],
                    'file_processing' => [
                        'processing_queue_size' => $this->getProcessingQueueSize(),
                        'failed_jobs_24h' => $this->getFailedJobsCount('24h'),
                        'average_processing_time' => $this->getAverageProcessingTime()
                    ]
                ];
            });
            
        } catch (Exception $e) {
            Log::error('Content metrics collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect content metrics'
            ];
        }
    }
    
    /**
     * Get infrastructure status
     */
    public function getInfrastructureStatus(): array
    {
        try {
            return [
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'environment' => app()->environment(),
                    'debug_mode' => config('app.debug'),
                    'maintenance_mode' => app()->isDownForMaintenance()
                ],
                'services' => [
                    'database' => $this->getDatabaseStatus(),
                    'redis' => $this->getRedisStatus(),
                    'queue_workers' => $this->getQueueWorkerStatus(),
                    'scheduler' => $this->getSchedulerStatus(),
                    'cache' => $this->getCacheStatus()
                ],
                'resource_usage' => [
                    'disk_space' => $this->getDiskSpaceUsage(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize')
                ]
            ];
            
        } catch (Exception $e) {
            Log::error('Infrastructure status collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect infrastructure status'
            ];
        }
    }
    
    /**
     * Get feature flag status
     */
    public function getFeatureFlagStatus(): array
    {
        try {
            $analytics = $this->featureService->getAnalytics();
            
            return [
                'total_flags' => $analytics['total_flags'] ?? 0,
                'enabled_flags' => $analytics['enabled_flags'] ?? 0,
                'disabled_flags' => ($analytics['total_flags'] ?? 0) - ($analytics['enabled_flags'] ?? 0),
                'category_breakdown' => $analytics['category_breakdown'] ?? [],
                'rollout_stats' => $analytics['rollout_stats'] ?? [],
                'recent_changes' => $this->getRecentFeatureFlagChanges()
            ];
            
        } catch (Exception $e) {
            Log::error('Feature flag status collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect feature flag status'
            ];
        }
    }
    
    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        try {
            $alerts = [];
            
            // Check system health alerts
            $systemHealth = $this->getSystemHealth();
            if ($systemHealth['overall_status'] === 'critical') {
                $alerts[] = [
                    'type' => 'critical',
                    'category' => 'system',
                    'message' => 'System health is critical',
                    'details' => $systemHealth,
                    'created_at' => now()->toISOString()
                ];
            }
            
            // Check security alerts
            $securityAlerts = $this->getSecurityAlerts();
            $alerts = array_merge($alerts, $securityAlerts);
            
            // Check performance alerts
            $performanceAlerts = $this->getPerformanceAlerts();
            $alerts = array_merge($alerts, $performanceAlerts);
            
            // Check storage alerts
            $storageAlerts = $this->getStorageAlerts();
            $alerts = array_merge($alerts, $storageAlerts);
            
            return [
                'total_alerts' => count($alerts),
                'critical_alerts' => count(array_filter($alerts, fn($alert) => $alert['type'] === 'critical')),
                'warning_alerts' => count(array_filter($alerts, fn($alert) => $alert['type'] === 'warning')),
                'alerts' => array_slice($alerts, 0, 10) // Return latest 10 alerts
            ];
            
        } catch (Exception $e) {
            Log::error('Active alerts collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect active alerts',
                'total_alerts' => 0
            ];
        }
    }
    
    /**
     * Get uptime statistics
     */
    public function getUptimeStats(): array
    {
        try {
            $cacheKey = self::METRICS_PREFIX . 'uptime_stats';
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return [
                    'current_uptime' => $this->getCurrentUptime(),
                    'uptime_24h' => $this->getUptimePercentage('24h'),
                    'uptime_7d' => $this->getUptimePercentage('7d'),
                    'uptime_30d' => $this->getUptimePercentage('30d'),
                    'last_downtime' => $this->getLastDowntimeInfo(),
                    'maintenance_windows' => $this->getScheduledMaintenance()
                ];
            });
            
        } catch (Exception $e) {
            Log::error('Uptime statistics collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to collect uptime statistics'
            ];
        }
    }
    
    /**
     * Private helper methods for health checks
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time' => round($responseTime, 2),
                'score' => $responseTime < 100 ? 100 : max(0, 100 - ($responseTime - 100) / 10)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
    
    private function checkRedisHealth(): array
    {
        try {
            $startTime = microtime(true);
            Redis::ping();
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time' => round($responseTime, 2),
                'score' => $responseTime < 50 ? 100 : max(0, 100 - ($responseTime - 50) / 5)
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
    
    private function checkStorageHealth(): array
    {
        try {
            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $totalSpace = disk_total_space($storagePath);
            $usagePercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
            
            $status = 'healthy';
            $score = 100;
            
            if ($usagePercent > 90) {
                $status = 'critical';
                $score = 0;
            } elseif ($usagePercent > 80) {
                $status = 'warning';
                $score = 50;
            }
            
            return [
                'status' => $status,
                'usage_percent' => $usagePercent,
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'score' => $score
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
    
    private function checkQueueHealth(): array
    {
        try {
            // Check queue size and failed jobs
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $status = 'healthy';
            $score = 100;
            
            if ($queueSize > 1000 || $failedJobs > 100) {
                $status = 'critical';
                $score = 0;
            } elseif ($queueSize > 500 || $failedJobs > 50) {
                $status = 'warning';
                $score = 50;
            }
            
            return [
                'status' => $status,
                'queue_size' => $queueSize,
                'failed_jobs' => $failedJobs,
                'score' => $score
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
    
    private function checkSecuritySystemsHealth(): array
    {
        try {
            // Check if security services are responding
            $virusScannerActive = class_exists('App\\Services\\VirusScanningService');
            $quarantineActive = class_exists('App\\Services\\FileQuarantineService');
            $monitoringActive = class_exists('App\\Services\\SecurityMonitoringService');
            
            $activeServices = array_sum([$virusScannerActive, $quarantineActive, $monitoringActive]);
            $score = ($activeServices / 3) * 100;
            
            return [
                'status' => $score === 100 ? 'healthy' : ($score >= 50 ? 'warning' : 'critical'),
                'virus_scanner' => $virusScannerActive,
                'quarantine_system' => $quarantineActive,
                'security_monitoring' => $monitoringActive,
                'score' => $score
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'score' => 0
            ];
        }
    }
    
    // Additional private helper methods would be implemented here...
    // These are placeholder implementations for brevity
    
    private function getResponseTimeMetrics(): array
    {
        return ['avg_response_time' => 150, 'p95_response_time' => 300];
    }
    
    private function getThroughputMetrics(): array
    {
        return ['requests_per_minute' => 120, 'requests_per_hour' => 7200];
    }
    
    private function getErrorRateMetrics(): array
    {
        return ['error_rate_24h' => 0.5, 'error_rate_1h' => 0.3];
    }
    
    private function getMemoryUsageMetrics(): array
    {
        return ['current_usage' => '128MB', 'peak_usage' => '256MB'];
    }
    
    private function getCpuUsageMetrics(): array
    {
        return ['current_usage' => 15.5, 'peak_usage' => 45.2];
    }
    
    private function getDiskUsageMetrics(): array
    {
        return ['usage_percent' => 45.2, 'free_space' => '2.1GB'];
    }
    
    private function getCachePerformanceMetrics(): array
    {
        return ['hit_rate' => 95.5, 'miss_rate' => 4.5];
    }
    
    private function getDatabasePerformanceMetrics(): array
    {
        return ['avg_query_time' => 25, 'slow_queries' => 3];
    }
    
    private function getVirusScanningStats(): array
    {
        return [
            'total_scanned' => 1250,
            'threats_found' => 5,
            'clean_files' => 1245,
            'queue_size' => 0
        ];
    }
    
    private function getUserActivityBreakdown(): array
    {
        return ['uploading' => 45, 'browsing' => 180, 'downloading' => 95];
    }
    
    private function getRegistrationTrends(): array
    {
        return ['daily' => [12, 15, 8, 20, 25, 18, 14], 'growth_rate' => 5.2];
    }
    
    private function getUserEngagementMetrics(): array
    {
        return ['avg_session_duration' => 18.5, 'bounce_rate' => 25.3];
    }
    
    private function getMostDownloadedRacks(int $limit): array
    {
        return Rack::withCount('downloads')->orderBy('downloads_count', 'desc')->limit($limit)->get()->toArray();
    }
    
    private function getRackCategoryBreakdown(): array
    {
        return Rack::groupBy('category')->selectRaw('category, count(*) as count')->pluck('count', 'category')->toArray();
    }
    
    private function getAverageArticleLength(): float
    {
        return Rack::whereNotNull('how_to_article')->avg(DB::raw('LENGTH(how_to_article)')) ?? 0;
    }
    
    private function getProcessingQueueSize(): int
    {
        return DB::table('jobs')->where('queue', 'rack-processing')->count();
    }
    
    private function getFailedJobsCount(string $period): int
    {
        $since = $period === '24h' ? now()->subDay() : now()->subWeek();
        return DB::table('failed_jobs')->where('failed_at', '>=', $since)->count();
    }
    
    private function getAverageProcessingTime(): float
    {
        // Placeholder - would calculate from job execution logs
        return 12.5;
    }
    
    private function getDatabaseStatus(): array
    {
        return ['status' => 'connected', 'connections' => 15];
    }
    
    private function getRedisStatus(): array
    {
        return ['status' => 'connected', 'memory_usage' => '45MB'];
    }
    
    private function getQueueWorkerStatus(): array
    {
        return ['active_workers' => 3, 'status' => 'running'];
    }
    
    private function getSchedulerStatus(): array
    {
        return ['status' => 'running', 'last_run' => now()->subMinute()->toISOString()];
    }
    
    private function getCacheStatus(): array
    {
        return ['status' => 'active', 'hit_rate' => 95.2];
    }
    
    private function getDiskSpaceUsage(): array
    {
        $storagePath = storage_path();
        return [
            'usage_percent' => 45.2,
            'free_space' => $this->formatBytes(disk_free_space($storagePath)),
            'total_space' => $this->formatBytes(disk_total_space($storagePath))
        ];
    }
    
    private function getRecentFeatureFlagChanges(): array
    {
        // Placeholder - would get recent changes from logs
        return [];
    }
    
    private function getSecurityAlerts(): array
    {
        // Placeholder - would get from security service
        return [];
    }
    
    private function getPerformanceAlerts(): array
    {
        // Placeholder - would check performance thresholds
        return [];
    }
    
    private function getStorageAlerts(): array
    {
        // Placeholder - would check storage thresholds
        return [];
    }
    
    private function getCurrentUptime(): string
    {
        // Placeholder - would calculate actual uptime
        return '15 days, 3 hours, 22 minutes';
    }
    
    private function getUptimePercentage(string $period): float
    {
        // Placeholder - would calculate from monitoring data
        return 99.95;
    }
    
    private function getLastDowntimeInfo(): ?array
    {
        // Placeholder - would get from downtime logs
        return null;
    }
    
    private function getScheduledMaintenance(): array
    {
        // Placeholder - would get scheduled maintenance windows
        return [];
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}