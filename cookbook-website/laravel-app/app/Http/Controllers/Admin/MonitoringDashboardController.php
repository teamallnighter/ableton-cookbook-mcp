<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonitoringDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Monitoring Dashboard Controller
 * 
 * Provides admin interface for system monitoring including:
 * - Real-time system health
 * - Performance metrics
 * - Security monitoring
 * - User analytics
 * - Infrastructure status
 */
class MonitoringDashboardController extends Controller
{
    private MonitoringDashboardService $monitoringService;
    
    public function __construct(MonitoringDashboardService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
        $this->middleware(['auth', 'admin']);
    }
    
    /**
     * Display the monitoring dashboard
     */
    public function index(): View
    {
        $metrics = $this->monitoringService->getDashboardMetrics();
        
        return view('admin.monitoring.dashboard', compact('metrics'));
    }
    
    /**
     * Get dashboard metrics as JSON for real-time updates
     */
    public function api(): JsonResponse
    {
        $metrics = $this->monitoringService->getDashboardMetrics();
        
        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }
    
    /**
     * Get system health status
     */
    public function systemHealth(): JsonResponse
    {
        $health = $this->monitoringService->getSystemHealth();
        
        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }
    
    /**
     * Get performance metrics
     */
    public function performanceMetrics(): JsonResponse
    {
        $performance = $this->monitoringService->getPerformanceMetrics();
        
        return response()->json([
            'success' => true,
            'data' => $performance
        ]);
    }
    
    /**
     * Get security metrics
     */
    public function securityMetrics(): JsonResponse
    {
        $security = $this->monitoringService->getSecurityMetrics();
        
        return response()->json([
            'success' => true,
            'data' => $security
        ]);
    }
    
    /**
     * Get user analytics
     */
    public function userAnalytics(): JsonResponse
    {
        $analytics = $this->monitoringService->getUserAnalytics();
        
        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }
    
    /**
     * Get content metrics
     */
    public function contentMetrics(): JsonResponse
    {
        $content = $this->monitoringService->getContentMetrics();
        
        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }
    
    /**
     * Get infrastructure status
     */
    public function infrastructureStatus(): JsonResponse
    {
        $infrastructure = $this->monitoringService->getInfrastructureStatus();
        
        return response()->json([
            'success' => true,
            'data' => $infrastructure
        ]);
    }
    
    /**
     * Get active alerts
     */
    public function activeAlerts(): JsonResponse
    {
        $alerts = $this->monitoringService->getActiveAlerts();
        
        return response()->json([
            'success' => true,
            'data' => $alerts
        ]);
    }
    
    /**
     * Get uptime statistics
     */
    public function uptimeStats(): JsonResponse
    {
        $uptime = $this->monitoringService->getUptimeStats();
        
        return response()->json([
            'success' => true,
            'data' => $uptime
        ]);
    }
}