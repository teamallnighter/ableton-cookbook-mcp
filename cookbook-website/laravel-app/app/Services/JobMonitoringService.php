<?php

namespace App\Services;

use App\Models\JobExecution;
use App\Models\JobProgress;
use App\Models\JobNotification;
use App\Models\JobSystemMetric;
use App\Enums\FailureCategory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Comprehensive monitoring and metrics service for job processing system
 * 
 * This service provides real-time monitoring, alerting, health checks,
 * and performance metrics for the background job processing infrastructure.
 */
class JobMonitoringService
{
    /**
     * Thresholds for alerting and health checks
     */
    private const HEALTH_THRESHOLDS = [
        'error_rate_warning' => 0.1,    // 10% error rate
        'error_rate_critical' => 0.25,  // 25% error rate
        'queue_depth_warning' => 100,   // 100+ jobs queued
        'queue_depth_critical' => 500,  // 500+ jobs queued
        'avg_processing_time_warning' => 300,  // 5+ minutes average
        'avg_processing_time_critical' => 600, // 10+ minutes average
        'stalled_jobs_warning' => 5,    // 5+ stalled jobs
        'stalled_jobs_critical' => 20,  // 20+ stalled jobs
    ];
    
    /**
     * Track job metrics for monitoring and alerting
     */
    public function trackJobMetrics(JobExecution $job): void
    {
        try {
            // Update system metrics cache
            $this->updateSystemMetricsCache($job);
            
            // Check for alerting thresholds
            $this->checkAlertingThresholds();
            
            // Log performance metrics
            $this->logPerformanceMetrics($job);
            
        } catch (\Exception $e) {
            Log::error('Failed to track job metrics', [
                'job_id' => $job->job_id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Generate comprehensive system health report
     */
    public function generateHealthReport(): array
    {
        try {
            $now = now();
            $hourAgo = $now->copy()->subHour();
            $dayAgo = $now->copy()->subDay();
            
            // Queue statistics
            $queueStats = $this->getQueueStatistics();
            
            // Performance metrics
            $performanceStats = $this->getPerformanceStatistics($hourAgo);
            
            // Error analysis
            $errorStats = $this->getErrorStatistics($dayAgo);
            
            // System resources
            $systemStats = $this->getSystemResourceStatistics();
            
            // Health score calculation
            $healthScore = $this->calculateHealthScore($queueStats, $performanceStats, $errorStats);
            
            return [
                'timestamp' => $now->toISOString(),
                'health_score' => $healthScore,
                'status' => $this->getHealthStatus($healthScore),
                'queue_statistics' => $queueStats,
                'performance_statistics' => $performanceStats,
                'error_statistics' => $errorStats,
                'system_statistics' => $systemStats,
                'alerts' => $this->getActiveAlerts(),
                'recommendations' => $this->generateRecommendations($queueStats, $performanceStats, $errorStats),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate health report', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'timestamp' => now()->toISOString(),
                'health_score' => 0,
                'status' => 'error',
                'error' => 'Failed to generate health report'
            ];
        }
    }
    
    /**
     * Check for failure threshold alerts
     */
    public function alertOnFailureThreshold(string $jobType, int $failures): void
    {
        $cacheKey = "failure_alert_sent:{$jobType}";
        
        // Avoid spam by limiting alerts to once per hour
        if (Cache::has($cacheKey)) {
            return;
        }
        
        if ($failures > 10) { // Alert threshold
            $this->sendAlert('high_failure_rate', [
                'job_type' => $jobType,
                'failure_count' => $failures,
                'time_period' => '1 hour'
            ]);
            
            Cache::put($cacheKey, true, 3600); // Cache for 1 hour
        }
    }
    
    /**
     * Identify system bottlenecks and performance issues
     */
    public function identifyBottlenecks(): array
    {
        $bottlenecks = [];
        
        try {
            // Queue depth analysis
            $queueDepth = $this->getCurrentQueueDepth();
            if ($queueDepth > self::HEALTH_THRESHOLDS['queue_depth_warning']) {
                $bottlenecks[] = [
                    'type' => 'queue_congestion',
                    'severity' => $queueDepth > self::HEALTH_THRESHOLDS['queue_depth_critical'] ? 'critical' : 'warning',
                    'description' => "Job queue has {$queueDepth} pending jobs",
                    'recommendation' => 'Consider scaling worker capacity or investigating slow jobs'
                ];
            }
            
            // Processing time analysis
            $avgProcessingTime = $this->getAverageProcessingTime();
            if ($avgProcessingTime > self::HEALTH_THRESHOLDS['avg_processing_time_warning']) {
                $bottlenecks[] = [
                    'type' => 'slow_processing',
                    'severity' => $avgProcessingTime > self::HEALTH_THRESHOLDS['avg_processing_time_critical'] ? 'critical' : 'warning',
                    'description' => "Average processing time is {$avgProcessingTime} seconds",
                    'recommendation' => 'Investigate slow job types and optimize processing logic'
                ];
            }
            
            // Error rate analysis
            $errorRate = $this->getCurrentErrorRate();
            if ($errorRate > self::HEALTH_THRESHOLDS['error_rate_warning']) {
                $bottlenecks[] = [
                    'type' => 'high_error_rate',
                    'severity' => $errorRate > self::HEALTH_THRESHOLDS['error_rate_critical'] ? 'critical' : 'warning',
                    'description' => "Error rate is " . round($errorRate * 100, 2) . "%",
                    'recommendation' => 'Review error logs and address common failure patterns'
                ];
            }
            
            // Stalled jobs analysis
            $stalledJobs = $this->getStalledJobsCount();
            if ($stalledJobs > self::HEALTH_THRESHOLDS['stalled_jobs_warning']) {
                $bottlenecks[] = [
                    'type' => 'stalled_jobs',
                    'severity' => $stalledJobs > self::HEALTH_THRESHOLDS['stalled_jobs_critical'] ? 'critical' : 'warning',
                    'description' => "{$stalledJobs} jobs appear to be stalled",
                    'recommendation' => 'Investigate stalled jobs and consider restarting or terminating them'
                ];
            }
            
            // Memory usage analysis
            $memoryUsage = $this->getAverageMemoryUsage();
            if ($memoryUsage > 1024 * 1024 * 1024) { // 1GB
                $bottlenecks[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'warning',
                    'description' => "Average memory usage is " . $this->formatBytes($memoryUsage),
                    'recommendation' => 'Monitor for memory leaks and optimize memory-intensive operations'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to identify bottlenecks', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $bottlenecks;
    }
    
    /**
     * Get real-time system metrics
     */
    public function getRealTimeMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'jobs_processing' => $this->getJobsProcessingCount(),
            'jobs_queued' => $this->getCurrentQueueDepth(),
            'jobs_failed_last_hour' => $this->getFailedJobsCount(60),
            'jobs_completed_last_hour' => $this->getCompletedJobsCount(60),
            'average_processing_time' => $this->getAverageProcessingTime(),
            'error_rate' => $this->getCurrentErrorRate(),
            'queue_wait_time' => $this->getAverageQueueWaitTime(),
            'system_load' => $this->getSystemLoad(),
            'memory_usage' => $this->getAverageMemoryUsage(),
        ];
    }
    
    /**
     * Store system metrics for historical analysis
     */
    public function recordSystemMetrics(): void
    {
        try {
            $metrics = $this->getRealTimeMetrics();
            
            JobSystemMetric::create([
                'recorded_at' => now(),
                'queue' => 'rack-processing',
                'jobs_pending' => $metrics['jobs_queued'],
                'jobs_processing' => $metrics['jobs_processing'],
                'jobs_failed' => $this->getFailedJobsCount(60),
                'jobs_completed_last_hour' => $metrics['jobs_completed_last_hour'],
                'avg_execution_time' => $metrics['average_processing_time'] * 1000, // Convert to milliseconds
                'avg_wait_time' => $metrics['queue_wait_time'] * 1000,
                'peak_memory_usage' => $metrics['memory_usage'],
                'cpu_usage' => $this->getCpuUsage(),
                'memory_usage' => $metrics['memory_usage'],
                'disk_usage' => $this->getDiskUsage(),
                'disk_usage_percentage' => $this->getDiskUsagePercentage(),
                'error_rate' => $metrics['error_rate'],
                'retry_rate' => $this->getRetryRate(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to record system metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Private helper methods
    
    private function updateSystemMetricsCache(JobExecution $job): void
    {
        $cacheKey = 'job_system_metrics';
        $metrics = Cache::get($cacheKey, [
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'total_execution_time' => 0,
            'total_memory_usage' => 0,
            'last_updated' => now()
        ]);
        
        $metrics['total_jobs']++;
        
        if ($job->status === 'completed') {
            $metrics['completed_jobs']++;
            if ($job->execution_time) {
                $metrics['total_execution_time'] += $job->execution_time;
            }
            if ($job->memory_peak) {
                $metrics['total_memory_usage'] += $job->memory_peak;
            }
        } elseif ($job->isFailed()) {
            $metrics['failed_jobs']++;
        }
        
        $metrics['last_updated'] = now();
        
        Cache::put($cacheKey, $metrics, 3600); // Cache for 1 hour
    }
    
    private function checkAlertingThresholds(): void
    {
        $errorRate = $this->getCurrentErrorRate();
        $queueDepth = $this->getCurrentQueueDepth();
        $stalledJobs = $this->getStalledJobsCount();
        
        // Check error rate threshold
        if ($errorRate > self::HEALTH_THRESHOLDS['error_rate_critical']) {
            $this->sendAlert('critical_error_rate', [
                'error_rate' => round($errorRate * 100, 2) . '%',
                'threshold' => round(self::HEALTH_THRESHOLDS['error_rate_critical'] * 100, 2) . '%'
            ]);
        }
        
        // Check queue depth threshold
        if ($queueDepth > self::HEALTH_THRESHOLDS['queue_depth_critical']) {
            $this->sendAlert('critical_queue_depth', [
                'queue_depth' => $queueDepth,
                'threshold' => self::HEALTH_THRESHOLDS['queue_depth_critical']
            ]);
        }
        
        // Check stalled jobs threshold
        if ($stalledJobs > self::HEALTH_THRESHOLDS['stalled_jobs_critical']) {
            $this->sendAlert('critical_stalled_jobs', [
                'stalled_count' => $stalledJobs,
                'threshold' => self::HEALTH_THRESHOLDS['stalled_jobs_critical']
            ]);
        }
    }
    
    private function logPerformanceMetrics(JobExecution $job): void
    {
        if ($job->status === 'completed' && $job->execution_time) {
            Log::info('Job performance metrics', [
                'job_id' => $job->job_id,
                'job_class' => $job->job_class,
                'execution_time_ms' => $job->execution_time,
                'memory_peak_bytes' => $job->memory_peak,
                'attempts' => $job->attempts,
                'queue_wait_time_ms' => $job->queue_wait_time
            ]);
        }
    }
    
    private function getQueueStatistics(): array
    {
        return [
            'total_queued' => $this->getCurrentQueueDepth(),
            'processing' => $this->getJobsProcessingCount(),
            'retry_scheduled' => JobExecution::where('status', 'retry_scheduled')->count(),
            'average_wait_time' => $this->getAverageQueueWaitTime(),
        ];
    }
    
    private function getPerformanceStatistics(Carbon $since): array
    {
        return [
            'average_execution_time' => $this->getAverageProcessingTime($since),
            'completed_jobs' => $this->getCompletedJobsCount(60),
            'jobs_per_hour' => $this->getJobsPerHour($since),
            'average_memory_usage' => $this->getAverageMemoryUsage($since),
        ];
    }
    
    private function getErrorStatistics(Carbon $since): array
    {
        $totalJobs = JobExecution::where('created_at', '>=', $since)->count();
        $failedJobs = JobExecution::where('created_at', '>=', $since)
            ->where('status', 'failed')->count();
        $permanentlyFailedJobs = JobExecution::where('created_at', '>=', $since)
            ->where('status', 'permanently_failed')->count();
            
        return [
            'total_jobs' => $totalJobs,
            'failed_jobs' => $failedJobs,
            'permanently_failed_jobs' => $permanentlyFailedJobs,
            'error_rate' => $totalJobs > 0 ? ($failedJobs + $permanentlyFailedJobs) / $totalJobs : 0,
            'failure_categories' => $this->getFailureCategoryBreakdown($since),
        ];
    }
    
    private function getSystemResourceStatistics(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getSystemMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'disk_free_space' => disk_free_space(storage_path()),
            'load_average' => $this->getSystemLoadAverage(),
        ];
    }
    
    private function calculateHealthScore(array $queueStats, array $performanceStats, array $errorStats): int
    {
        $score = 100;
        
        // Deduct points for high error rate
        if ($errorStats['error_rate'] > 0.1) {
            $score -= min(30, $errorStats['error_rate'] * 100);
        }
        
        // Deduct points for queue congestion
        if ($queueStats['total_queued'] > 100) {
            $score -= min(20, ($queueStats['total_queued'] - 100) / 20);
        }
        
        // Deduct points for slow processing
        if ($performanceStats['average_execution_time'] > 300) {
            $score -= min(15, ($performanceStats['average_execution_time'] - 300) / 60);
        }
        
        // Deduct points for stalled jobs
        $stalledJobs = $this->getStalledJobsCount();
        if ($stalledJobs > 0) {
            $score -= min(10, $stalledJobs * 2);
        }
        
        return max(0, (int) $score);
    }
    
    private function getHealthStatus(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 50) return 'fair';
        if ($score >= 25) return 'poor';
        return 'critical';
    }
    
    private function getCurrentQueueDepth(): int
    {
        return JobExecution::whereIn('status', ['queued', 'processing'])->count();
    }
    
    private function getJobsProcessingCount(): int
    {
        return JobExecution::where('status', 'processing')->count();
    }
    
    private function getFailedJobsCount(int $minutes): int
    {
        return JobExecution::where('failed_at', '>=', now()->subMinutes($minutes))
            ->whereIn('status', ['failed', 'permanently_failed'])
            ->count();
    }
    
    private function getCompletedJobsCount(int $minutes): int
    {
        return JobExecution::where('completed_at', '>=', now()->subMinutes($minutes))
            ->where('status', 'completed')
            ->count();
    }
    
    private function getAverageProcessingTime(?Carbon $since = null): float
    {
        $query = JobExecution::where('status', 'completed')
            ->whereNotNull('execution_time');
            
        if ($since) {
            $query->where('completed_at', '>=', $since);
        } else {
            $query->where('completed_at', '>=', now()->subHour());
        }
        
        return $query->avg('execution_time') / 1000 ?? 0; // Convert to seconds
    }
    
    private function getCurrentErrorRate(): float
    {
        $total = JobExecution::where('created_at', '>=', now()->subHour())->count();
        if ($total === 0) return 0;
        
        $failed = JobExecution::where('created_at', '>=', now()->subHour())
            ->whereIn('status', ['failed', 'permanently_failed'])
            ->count();
            
        return $failed / $total;
    }
    
    private function getStalledJobsCount(): int
    {
        return JobExecution::where('status', 'processing')
            ->where('started_at', '<', now()->subMinutes(15))
            ->count();
    }
    
    private function getAverageMemoryUsage(?Carbon $since = null): int
    {
        $query = JobExecution::where('status', 'completed')
            ->whereNotNull('memory_peak');
            
        if ($since) {
            $query->where('completed_at', '>=', $since);
        } else {
            $query->where('completed_at', '>=', now()->subHour());
        }
        
        return (int) $query->avg('memory_peak') ?? 0;
    }
    
    private function getAverageQueueWaitTime(): float
    {
        return JobExecution::where('started_at', '>=', now()->subHour())
            ->whereNotNull('queue_wait_time')
            ->avg('queue_wait_time') / 1000 ?? 0; // Convert to seconds
    }
    
    private function getJobsPerHour(Carbon $since): float
    {
        $hours = $since->diffInHours(now());
        if ($hours === 0) $hours = 1;
        
        $completedJobs = JobExecution::where('completed_at', '>=', $since)
            ->where('status', 'completed')
            ->count();
            
        return $completedJobs / $hours;
    }
    
    private function getFailureCategoryBreakdown(Carbon $since): array
    {
        return JobExecution::where('created_at', '>=', $since)
            ->whereNotNull('failure_category')
            ->groupBy('failure_category')
            ->selectRaw('failure_category, COUNT(*) as count')
            ->pluck('count', 'failure_category')
            ->toArray();
    }
    
    private function getRetryRate(): float
    {
        $totalJobs = JobExecution::where('created_at', '>=', now()->subHour())->count();
        if ($totalJobs === 0) return 0;
        
        $retriedJobs = JobExecution::where('created_at', '>=', now()->subHour())
            ->where('attempts', '>', 1)
            ->count();
            
        return $retriedJobs / $totalJobs;
    }
    
    private function getCpuUsage(): ?float
    {
        // This would integrate with system monitoring tools
        return null;
    }
    
    private function getSystemMemoryUsage(): ?int
    {
        // This would integrate with system monitoring tools
        return null;
    }
    
    private function getDiskUsage(): ?int
    {
        try {
            return disk_total_space(storage_path()) - disk_free_space(storage_path());
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getDiskUsagePercentage(): ?float
    {
        try {
            $total = disk_total_space(storage_path());
            $free = disk_free_space(storage_path());
            return (($total - $free) / $total) * 100;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getSystemLoad(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg();
        }
        return null;
    }
    
    private function getSystemLoadAverage(): ?array
    {
        return $this->getSystemLoad();
    }
    
    private function sendAlert(string $type, array $data): void
    {
        Log::alert("Job monitoring alert: {$type}", $data);
        
        // This would integrate with your alerting system (email, Slack, PagerDuty, etc.)
    }
    
    private function getActiveAlerts(): array
    {
        // This would return currently active alerts
        return [];
    }
    
    private function generateRecommendations(array $queueStats, array $performanceStats, array $errorStats): array
    {
        $recommendations = [];
        
        if ($queueStats['total_queued'] > 100) {
            $recommendations[] = 'Consider increasing worker capacity to handle queue backlog';
        }
        
        if ($performanceStats['average_execution_time'] > 300) {
            $recommendations[] = 'Investigate slow-running jobs and optimize processing logic';
        }
        
        if ($errorStats['error_rate'] > 0.1) {
            $recommendations[] = 'Review error logs and address common failure patterns';
        }
        
        if ($this->getStalledJobsCount() > 5) {
            $recommendations[] = 'Check for stalled jobs and consider restarting workers';
        }
        
        return $recommendations;
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