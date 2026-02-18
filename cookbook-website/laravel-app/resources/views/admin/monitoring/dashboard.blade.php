<x-admin-layout>
    @section('header')
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Monitoring</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Real-time system health, performance metrics, and infrastructure status.
                </p>
            </div>
            <div class="flex space-x-3">
                <div class="text-sm text-gray-500 dark:text-gray-400 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                    Last updated: <span id="last-update-time">{{ now()->format('H:i:s') }}</span>
                </div>
                <button id="toggle-auto-refresh" class="admin-btn-secondary" data-auto-refresh="true">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Auto-refresh: ON
                </button>
                <button id="refresh-monitoring" class="admin-btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh Now
                </button>
            </div>
        </div>
    @endsection

    <!-- System Health Status Bar -->
    <div class="admin-card mb-6" id="system-status-card">
        <div class="p-4 bg-gradient-to-r from-green-500 to-blue-600 text-white rounded-t-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="flex items-center">
                        <div id="system-health-indicator" class="w-4 h-4 bg-green-300 rounded-full mr-3 animate-pulse"></div>
                        <span class="text-lg font-semibold">System Status: <span id="system-health-text">Healthy</span></span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Uptime:</span>
                        <span id="system-uptime" class="font-semibold">99.9%</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Response Time:</span>
                        <span id="response-time" class="font-semibold">120ms</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Active Alerts:</span>
                        <span id="active-alerts-count" class="font-semibold">0</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-white/80">Server Load</div>
                    <div id="server-load" class="text-2xl font-bold">0.45</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- CPU Usage -->
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">CPU Usage</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="cpu-usage">45%</p>
                            </div>
                            <div class="w-16 h-16 relative">
                                <canvas id="cpu-chart" width="64" height="64"></canvas>
                            </div>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div id="cpu-progress" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 45%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Memory Usage -->
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                        </svg>
                    </div>
                    <div class="ml-5 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Memory Usage</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="memory-usage">2.1GB</p>
                            </div>
                            <div class="w-16 h-16 relative">
                                <canvas id="memory-chart" width="64" height="64"></canvas>
                            </div>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div id="memory-progress" class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: 60%"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">of <span id="memory-total">4GB</span> total</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disk Usage -->
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Disk Usage</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="disk-usage">45GB</p>
                            </div>
                            <div class="w-16 h-16 relative">
                                <canvas id="disk-chart" width="64" height="64"></canvas>
                            </div>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div id="disk-progress" class="bg-yellow-600 h-2 rounded-full transition-all duration-300" style="width: 75%"></div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">of <span id="disk-total">60GB</span> total</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network I/O -->
        <div class="admin-card">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                    </div>
                    <div class="ml-5 flex-1">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Network I/O</p>
                            <div class="flex items-center space-x-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">↓ <span id="network-in">12.5 MB/s</span></p>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">↑ <span id="network-out">8.2 MB/s</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Download</span>
                                <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                    <div id="network-in-progress" class="bg-purple-600 h-1 rounded-full transition-all duration-300" style="width: 70%"></div>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Upload</span>
                                <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                    <div id="network-out-progress" class="bg-purple-400 h-1 rounded-full transition-all duration-300" style="width: 45%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- System Performance Chart -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">System Performance (Last 24h)</h3>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Response Time Chart -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Response Times</h3>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Database Status -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Database Status</h3>
            </div>
            <div class="admin-card-body">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="font-medium text-gray-900 dark:text-white">MySQL Connection</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Connected</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="font-medium text-gray-900 dark:text-white">Redis Cache</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Online</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active Connections</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="db-connections">45</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Query Time (avg)</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="db-query-time">12ms</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cache Hit Rate</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="cache-hit-rate">95.2%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Slow Queries</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="slow-queries">2</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Status -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Application Services</h3>
            </div>
            <div class="admin-card-body">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="font-medium text-gray-900 dark:text-white">Web Server</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Running</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="font-medium text-gray-900 dark:text-white">Queue Worker</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Active</span>
                    </div>
                    
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                            <span class="font-medium text-gray-900 dark:text-white">Email Service</span>
                        </div>
                        <span class="text-sm text-yellow-600 dark:text-yellow-400">Delayed</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active Sessions</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="active-sessions">324</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Queue Size</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="queue-size">12</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Failed Jobs</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="failed-jobs">3</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Processed/min</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white" id="processed-per-min">45</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Alerts -->
    <div class="admin-card" id="alerts-section">
        <div class="admin-card-header">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Active Alerts</h3>
                <span id="alerts-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    All Clear
                </span>
            </div>
        </div>
        <div class="admin-card-body">
            <div id="alerts-list">
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm">No active alerts. System is running smoothly.</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monitoringDashboard = new SystemMonitoringDashboard();
        });

        class SystemMonitoringDashboard {
            constructor() {
                this.charts = {};
                this.autoRefresh = true;
                this.refreshInterval = null;
                this.init();
            }

            init() {
                this.initCharts();
                this.bindEvents();
                this.startRealTimeUpdates();
                this.initMiniCharts();
            }

            bindEvents() {
                // Toggle auto-refresh
                document.getElementById('toggle-auto-refresh').addEventListener('click', (e) => {
                    this.toggleAutoRefresh();
                });

                // Manual refresh
                document.getElementById('refresh-monitoring').addEventListener('click', () => {
                    this.refreshData();
                });
            }

            initCharts() {
                // Performance Chart
                const performanceCtx = document.getElementById('performanceChart').getContext('2d');
                this.charts.performance = new Chart(performanceCtx, {
                    type: 'line',
                    data: {
                        labels: this.generateTimeLabels(24),
                        datasets: [
                            {
                                label: 'CPU %',
                                data: this.generateRandomData(24, 30, 70),
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Memory %',
                                data: this.generateRandomData(24, 50, 80),
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });

                // Response Time Chart
                const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
                this.charts.responseTime = new Chart(responseTimeCtx, {
                    type: 'bar',
                    data: {
                        labels: ['API', 'Web', 'Database', 'Cache', 'Queue', 'Storage'],
                        datasets: [{
                            label: 'Response Time (ms)',
                            data: [120, 85, 12, 3, 45, 30],
                            backgroundColor: [
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(251, 191, 36, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(75, 85, 99, 0.8)'
                            ],
                            borderColor: [
                                'rgb(59, 130, 246)',
                                'rgb(34, 197, 94)',
                                'rgb(251, 191, 36)',
                                'rgb(147, 51, 234)',
                                'rgb(239, 68, 68)',
                                'rgb(75, 85, 99)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            initMiniCharts() {
                // CPU Mini Chart
                const cpuCtx = document.getElementById('cpu-chart').getContext('2d');
                new Chart(cpuCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [45, 55],
                            backgroundColor: ['rgb(59, 130, 246)', 'rgba(156, 163, 175, 0.2)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: { legend: { display: false } },
                        cutout: '70%'
                    }
                });

                // Memory Mini Chart
                const memoryCtx = document.getElementById('memory-chart').getContext('2d');
                new Chart(memoryCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [60, 40],
                            backgroundColor: ['rgb(34, 197, 94)', 'rgba(156, 163, 175, 0.2)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: { legend: { display: false } },
                        cutout: '70%'
                    }
                });

                // Disk Mini Chart
                const diskCtx = document.getElementById('disk-chart').getContext('2d');
                new Chart(diskCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [75, 25],
                            backgroundColor: ['rgb(251, 191, 36)', 'rgba(156, 163, 175, 0.2)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: { legend: { display: false } },
                        cutout: '70%'
                    }
                });
            }

            startRealTimeUpdates() {
                if (this.autoRefresh) {
                    this.refreshInterval = setInterval(() => {
                        this.updateRealTimeMetrics();
                    }, 5000); // Update every 5 seconds
                }
            }

            async updateRealTimeMetrics() {
                try {
                    // Simulate real-time data updates
                    this.updateMetricValues();
                    this.updateLastUpdateTime();
                } catch (error) {
                    console.error('Failed to update real-time metrics:', error);
                }
            }

            updateMetricValues() {
                // Simulate metric updates with slight variations
                const cpu = Math.floor(Math.random() * 20) + 35;
                const memory = Math.floor(Math.random() * 15) + 55;
                const responseTime = Math.floor(Math.random() * 40) + 100;
                
                document.getElementById('cpu-usage').textContent = `${cpu}%`;
                document.getElementById('cpu-progress').style.width = `${cpu}%`;
                
                document.getElementById('response-time').textContent = `${responseTime}ms`;
            }

            updateLastUpdateTime() {
                document.getElementById('last-update-time').textContent = new Date().toLocaleTimeString();
            }

            toggleAutoRefresh() {
                const button = document.getElementById('toggle-auto-refresh');
                this.autoRefresh = !this.autoRefresh;
                
                if (this.autoRefresh) {
                    button.textContent = 'Auto-refresh: ON';
                    button.setAttribute('data-auto-refresh', 'true');
                    this.startRealTimeUpdates();
                } else {
                    button.textContent = 'Auto-refresh: OFF';
                    button.setAttribute('data-auto-refresh', 'false');
                    if (this.refreshInterval) {
                        clearInterval(this.refreshInterval);
                        this.refreshInterval = null;
                    }
                }
            }

            async refreshData() {
                const button = document.getElementById('refresh-monitoring');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';
                button.disabled = true;

                try {
                    await this.updateRealTimeMetrics();
                    // Update charts with new data
                    this.updateCharts();
                } finally {
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 1000);
                }
            }

            updateCharts() {
                // Add new data points and remove old ones
                Object.values(this.charts).forEach(chart => {
                    if (chart.data && chart.data.datasets) {
                        chart.data.datasets.forEach(dataset => {
                            if (Array.isArray(dataset.data)) {
                                // Add new data point
                                dataset.data.push(Math.floor(Math.random() * 50) + 30);
                                // Remove old data point if we have too many
                                if (dataset.data.length > 24) {
                                    dataset.data.shift();
                                }
                            }
                        });
                        chart.update();
                    }
                });
            }

            generateTimeLabels(hours) {
                const labels = [];
                const now = new Date();
                for (let i = hours - 1; i >= 0; i--) {
                    const time = new Date(now.getTime() - (i * 60 * 60 * 1000));
                    labels.push(time.getHours().toString().padStart(2, '0') + ':00');
                }
                return labels;
            }

            generateRandomData(count, min, max) {
                const data = [];
                for (let i = 0; i < count; i++) {
                    data.push(Math.floor(Math.random() * (max - min + 1)) + min);
                }
                return data;
            }

            destroy() {
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                Object.values(this.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (window.monitoringDashboard) {
                window.monitoringDashboard.destroy();
            }
        });
    </script>
    @endpush
</x-admin-layout>