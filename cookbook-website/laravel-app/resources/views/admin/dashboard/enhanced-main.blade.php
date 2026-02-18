<x-admin-layout>
    @section('header')
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Admin Dashboard</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Welcome back! Here's what's happening on your platform.
                </p>
            </div>
            <div class="flex space-x-3">
                <button id="refresh-all-data" class="admin-btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh Data
                </button>
                <a href="{{ route('admin.analytics.dashboard') }}" class="admin-btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 00-2-2m-2 2h2" />
                    </svg>
                    Advanced Analytics
                </a>
            </div>
        </div>
    @endsection

    <!-- Real-time Status Bar -->
    <div id="status-bar" class="admin-card mb-6">
        <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-t-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="flex items-center">
                        <div id="system-status-indicator" class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                        <span class="text-sm font-medium">System Healthy</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Active Users:</span>
                        <span id="active-users-count" class="font-semibold">{{ $stats['total_users'] ?? 0 }}</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Queue:</span>
                        <span id="queue-count" class="font-semibold">{{ $stats['processing_queue'] ?? 0 }}</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-white/80">Issues:</span>
                        <span id="pending-issues-count" class="font-semibold">{{ $stats['pending_issues'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="text-sm text-white/80">
                    Last updated: <span id="last-updated-time">{{ now()->format('H:i:s') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Users -->
        <div class="admin-card transform hover:scale-105 transition-transform duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Users</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($stats['total_users']) }}
                                </div>
                                <div class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">
                                    +{{ $stats['new_users_30d'] ?? 0 }} (30d)
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Racks -->
        <div class="admin-card transform hover:scale-105 transition-transform duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 112 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 110 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 110-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Racks</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($stats['total_racks']) }}
                                </div>
                                <div class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">
                                    +{{ $stats['new_racks_30d'] ?? 0 }} (30d)
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Downloads -->
        <div class="admin-card transform hover:scale-105 transition-transform duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Downloads</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($stats['total_downloads']) }}
                                </div>
                                <div class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">
                                    +{{ number_format($stats['downloads_30d'] ?? 0) }} (30d)
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="admin-card transform hover:scale-105 transition-transform duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">System Status</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    Healthy
                                </div>
                                @if(($stats['urgent_issues'] ?? 0) > 0)
                                    <div class="ml-2 text-sm font-medium text-red-600 dark:text-red-400">
                                        {{ $stats['urgent_issues'] }} urgent
                                    </div>
                                @else
                                    <div class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">
                                        All systems operational
                                    </div>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Activity Trends Chart -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Trends (30 Days)</h3>
                    <div class="flex space-x-2">
                        <button class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="activityTrendsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Issue Status Overview -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Issues Overview</h3>
                    <a href="{{ route('admin.issues.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        View All →
                    </a>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="issuesOverviewChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers and Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Performing Content -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Top Performing Content</h3>
            </div>
            <div class="admin-card-body">
                <div class="space-y-6">
                    <!-- Most Downloaded Racks -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Most Downloaded Racks</h4>
                        <div class="space-y-3">
                            @foreach($topPerformers['top_downloads'] ?? [] as $rack)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <a href="{{ $rack['url'] }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate flex-1">
                                        {{ $rack['title'] }}
                                    </a>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 flex-shrink-0">{{ $rack['metric'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Top Uploaders -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Top Contributors</h4>
                        <div class="space-y-3">
                            @foreach($topPerformers['top_uploaders'] ?? [] as $user)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <a href="{{ $user['url'] }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate flex-1">
                                        {{ $user['title'] }}
                                    </a>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 flex-shrink-0">{{ $user['metric'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Issues -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Issues</h3>
                    <a href="{{ route('admin.issues.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        Manage All →
                    </a>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="space-y-4">
                    @forelse($issues['recent'] ?? [] as $issue)
                        <div class="border-l-4 @if($issue['priority'] === 'urgent') border-red-500 @elseif($issue['priority'] === 'high') border-yellow-500 @else border-gray-300 dark:border-gray-600 @endif pl-4 py-2">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <a href="{{ $issue['url'] }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 block truncate">
                                        {{ $issue['title'] }}
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ ucfirst(str_replace('_', ' ', $issue['type'])) }} • 
                                        {{ ucfirst($issue['priority']) }} • 
                                        by {{ $issue['user'] }} • 
                                        {{ $issue['created_at']->diffForHumans() }}
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ml-2 flex-shrink-0
                                    @if($issue['status'] === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @elseif($issue['status'] === 'in_review') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    @elseif($issue['status'] === 'resolved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $issue['status'])) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm">No recent issues to display</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Platform Activity -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Platform Activity</h3>
        </div>
        <div class="admin-card-body">
            <div class="flow-root">
                <ul class="-mb-8">
                    @forelse($recentActivity ?? [] as $key => $activity)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                    <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-600" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-800
                                            @if($activity['type'] === 'rack_upload') bg-purple-500
                                            @elseif($activity['type'] === 'comment') bg-green-500
                                            @elseif($activity['type'] === 'blog_post') bg-blue-500
                                            @else bg-gray-500 @endif">
                                            @if($activity['type'] === 'rack_upload')
                                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            @elseif($activity['type'] === 'comment')
                                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a2 2 0 00-2 2v6a2 2 0 002 2h8a2 2 0 002-2V6a2 2 0 00-2-2V3a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                                <a href="{{ $activity['url'] }}" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate">
                                                    {{ $activity['title'] }}
                                                </a>
                                                <span class="text-gray-500 dark:text-gray-400">by {{ $activity['user'] }}</span>
                                            </p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400 flex-shrink-0">
                                            {{ $activity['created_at']->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-center py-4 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <p class="text-sm">No recent activity to display</p>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize main dashboard
            const dashboard = new MainAdminDashboard();
        });

        class MainAdminDashboard {
            constructor() {
                this.charts = {};
                this.init();
            }

            init() {
                this.initCharts();
                this.bindEvents();
                this.startRealTimeUpdates();
            }

            initCharts() {
                // Activity Trends Chart
                const activityCtx = document.getElementById('activityTrendsChart').getContext('2d');
                this.charts.activity = new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: {!! json_encode($charts['dates'] ?? []) !!},
                        datasets: [
                            {
                                label: 'Downloads',
                                data: {!! json_encode($charts['downloads'] ?? []) !!},
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'New Users',
                                data: {!! json_encode($charts['users'] ?? []) !!},
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'New Racks',
                                data: {!! json_encode($charts['racks'] ?? []) !!},
                                borderColor: 'rgb(147, 51, 234)',
                                backgroundColor: 'rgba(147, 51, 234, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(156, 163, 175, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(156, 163, 175, 0.1)'
                                }
                            }
                        }
                    }
                });

                // Issues Overview Chart
                const issuesCtx = document.getElementById('issuesOverviewChart').getContext('2d');
                const issuesData = {!! json_encode($issues['by_status'] ?? []) !!};
                this.charts.issues = new Chart(issuesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(issuesData).map(status => status.replace('_', ' ').toUpperCase()),
                        datasets: [{
                            data: Object.values(issuesData),
                            backgroundColor: [
                                '#f59e0b', // pending - yellow
                                '#3b82f6', // in_review - blue
                                '#10b981', // resolved - green
                                '#ef4444', // rejected - red
                                '#6b7280'  // other - gray
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white'
                            }
                        }
                    }
                });
            }

            bindEvents() {
                // Refresh all data
                document.getElementById('refresh-all-data').addEventListener('click', () => {
                    this.refreshAllData();
                });
            }

            async refreshAllData() {
                const button = document.getElementById('refresh-all-data');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';
                button.disabled = true;

                try {
                    // Refresh the page to get latest data
                    window.location.reload();
                } catch (error) {
                    console.error('Failed to refresh data:', error);
                    window.AdminUtils.showFlashMessage('Failed to refresh data', 'error');
                } finally {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            startRealTimeUpdates() {
                // Update real-time metrics every 30 seconds
                window.AdminRealTime.start('dashboard-metrics', () => {
                    this.updateRealTimeMetrics();
                }, 30000);
            }

            async updateRealTimeMetrics() {
                try {
                    const response = await window.AdminUtils.apiRequest('/admin/analytics/real-time');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.updateStatusBar(result.data);
                    }
                } catch (error) {
                    console.error('Failed to update real-time metrics:', error);
                }
            }

            updateStatusBar(data) {
                // Update active users count
                const activeUsersEl = document.getElementById('active-users-count');
                if (activeUsersEl) {
                    activeUsersEl.textContent = window.AdminUtils.formatNumber(data.active_users || 0);
                }

                // Update queue count
                const queueCountEl = document.getElementById('queue-count');
                if (queueCountEl) {
                    queueCountEl.textContent = window.AdminUtils.formatNumber(data.processing_queue?.pending || 0);
                }

                // Update pending issues count
                const issuesCountEl = document.getElementById('pending-issues-count');
                if (issuesCountEl) {
                    issuesCountEl.textContent = window.AdminUtils.formatNumber(data.pending_issues || 0);
                }

                // Update last updated time
                const lastUpdatedEl = document.getElementById('last-updated-time');
                if (lastUpdatedEl) {
                    lastUpdatedEl.textContent = new Date().toLocaleTimeString();
                }

                // Update system status indicator
                const indicatorEl = document.getElementById('system-status-indicator');
                if (indicatorEl) {
                    if (data.system_health?.status === 'healthy') {
                        indicatorEl.className = 'w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse';
                    } else {
                        indicatorEl.className = 'w-3 h-3 bg-red-400 rounded-full mr-2 animate-pulse';
                    }
                }
            }

            destroy() {
                Object.values(this.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
                window.AdminRealTime.stop('dashboard-metrics');
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (window.dashboard) {
                window.dashboard.destroy();
            }
        });
    </script>
    @endpush
</x-admin-layout>