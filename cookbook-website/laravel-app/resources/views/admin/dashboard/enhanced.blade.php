<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Enhanced Analytics Dashboard') }}
            </h2>
            <div class="flex space-x-3">
                <button id="refresh-dashboard" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
                <button id="export-data" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export
                </button>
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Classic View
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Real-time Status Bar --}}
            <div id="status-bar" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center">
                                <div id="status-indicator" class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                                <span class="text-sm font-medium">System Healthy</span>
                            </div>
                            <div class="text-sm">
                                <span id="active-users">{{ $stats['total_users'] ?? 0 }}</span> Active Users
                            </div>
                            <div class="text-sm">
                                Queue: <span id="queue-count">{{ $stats['processing_queue'] ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="text-sm">
                            Last updated: <span id="last-updated">{{ now()->format('H:i:s') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Key Metrics Overview --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-500 bg-opacity-75">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Users</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['total_users']) }}</div>
                                    <div class="ml-2 text-sm font-medium {{ ($stats['user_growth_rate'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ ($stats['user_growth_rate'] ?? 0) >= 0 ? '+' : '' }}{{ $stats['user_growth_rate'] ?? 0 }}%
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-500 bg-opacity-75">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 112 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 110 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 110-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Racks</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['total_racks']) }}</div>
                                    <div class="ml-2 text-sm font-medium {{ ($stats['rack_growth_rate'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ ($stats['rack_growth_rate'] ?? 0) >= 0 ? '+' : '' }}{{ $stats['rack_growth_rate'] ?? 0 }}%
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-500 bg-opacity-75">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Downloads</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['total_downloads']) }}</div>
                                    <div class="ml-2 text-sm font-medium text-green-600">
                                        +{{ number_format($stats['downloads_30d']) }} (30d)
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-500 bg-opacity-75">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Newsletter</dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($stats['newsletter_subscribers']) }}</div>
                                    <div class="ml-2 text-sm font-medium text-blue-600">
                                        {{ $emailStats['email_performance']['open_rate'] ?? 0 }}% open rate
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Advanced Analytics Tabs --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button class="analytics-tab active border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="overview">
                            Overview
                        </button>
                        <button class="analytics-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="racks">
                            Racks Analytics
                        </button>
                        <button class="analytics-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="email">
                            Email Analytics
                        </button>
                        <button class="analytics-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="users">
                            User Analytics
                        </button>
                        <button class="analytics-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-tab="system">
                            System Health
                        </button>
                    </nav>
                </div>
                
                <div class="p-6">
                    <div id="tab-content" class="space-y-6">
                        {{-- Content will be loaded dynamically --}}
                        <div class="text-center py-8">
                            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-gray-500">Loading analytics data...</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Performance Metrics Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Real-time Metrics</h3>
                        <canvas id="realtimeChart" class="w-full h-64"></canvas>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">System Performance</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Response Time</span>
                                <span class="text-sm text-gray-500">{{ $healthMetrics['performance']['average_response_time'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Memory Usage</span>
                                <span class="text-sm text-gray-500">{{ round(($healthMetrics['performance']['memory_usage'] ?? 0) / 1024 / 1024, 2) }} MB</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Database Health</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ ($healthMetrics['database']['status'] ?? '') === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($healthMetrics['database']['status'] ?? 'Unknown') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cache Hit Rate</span>
                                <span class="text-sm text-gray-500">{{ $healthMetrics['cache']['status'] ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alerts Section --}}
            <div id="alerts-section" class="hidden bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">System Alerts</h3>
                    <div id="alerts-content">
                        {{-- Alerts will be loaded dynamically --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        class EnhancedDashboard {
            constructor() {
                this.currentTab = 'overview';
                this.refreshInterval = null;
                this.charts = {};
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupRealTimeUpdates();
                this.loadTabContent('overview');
                this.initRealtimeChart();
            }

            bindEvents() {
                // Tab switching
                document.querySelectorAll('.analytics-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        const tabName = e.target.dataset.tab;
                        this.switchTab(tabName);
                    });
                });

                // Refresh button
                document.getElementById('refresh-dashboard').addEventListener('click', () => {
                    this.refreshData();
                });

                // Export button
                document.getElementById('export-data').addEventListener('click', () => {
                    this.exportData();
                });
            }

            switchTab(tabName) {
                // Update tab appearance
                document.querySelectorAll('.analytics-tab').forEach(tab => {
                    tab.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    tab.classList.add('border-transparent', 'text-gray-500');
                });

                const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
                if (activeTab) {
                    activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
                    activeTab.classList.remove('border-transparent', 'text-gray-500');
                }

                this.currentTab = tabName;
                this.loadTabContent(tabName);
            }

            async loadTabContent(tabName) {
                const tabContent = document.getElementById('tab-content');
                tabContent.innerHTML = '<div class="text-center py-8"><svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><p class="text-gray-500">Loading ' + tabName + ' analytics...</p></div>';

                try {
                    const response = await fetch(`/admin/analytics/section/${tabName}?days=30`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.renderTabContent(tabName, result.data);
                    } else {
                        this.showError('Failed to load analytics data');
                    }
                } catch (error) {
                    console.error('Error loading tab content:', error);
                    this.showError('Network error occurred');
                }
            }

            renderTabContent(tabName, data) {
                const tabContent = document.getElementById('tab-content');
                
                switch (tabName) {
                    case 'overview':
                        this.renderOverviewTab(tabContent, data);
                        break;
                    case 'racks':
                        this.renderRacksTab(tabContent, data);
                        break;
                    case 'email':
                        this.renderEmailTab(tabContent, data);
                        break;
                    case 'users':
                        this.renderUsersTab(tabContent, data);
                        break;
                    case 'system':
                        this.renderSystemTab(tabContent, data);
                        break;
                    default:
                        tabContent.innerHTML = '<p class="text-gray-500">Tab content not implemented yet.</p>';
                }
            }

            renderOverviewTab(container, data) {
                container.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Platform Growth</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Users Growth</span>
                                    <span class="text-sm font-medium ${data.users?.growth_rate >= 0 ? 'text-green-600' : 'text-red-600'}">${data.users?.growth_rate || 0}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Content Growth</span>
                                    <span class="text-sm font-medium ${data.racks?.growth_rate >= 0 ? 'text-green-600' : 'text-red-600'}">${data.racks?.growth_rate || 0}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Engagement Score</span>
                                    <span class="text-sm font-medium text-blue-600">${data.engagement?.avg_downloads_per_rack || 0}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">System Status</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Server Uptime</span>
                                    <span class="text-sm font-medium text-green-600">${data.system?.server_uptime || 'N/A'}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Queue Status</span>
                                    <span class="text-sm font-medium text-blue-600">${data.racks?.processing_queue || 0} pending</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Issues</span>
                                    <span class="text-sm font-medium ${data.system?.urgent_issues > 0 ? 'text-red-600' : 'text-green-600'}">${data.system?.pending_issues || 0} pending</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Content Quality</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Avg Rating</span>
                                    <span class="text-sm font-medium text-yellow-600">${data.engagement?.avg_rating || 0} ⭐</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">How-to Articles</span>
                                    <span class="text-sm font-medium text-purple-600">${data.content?.racks_with_how_to || 0}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">Comments</span>
                                    <span class="text-sm font-medium text-blue-600">${data.engagement?.total_comments || 0}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            renderRacksTab(container, data) {
                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Upload Statistics</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Total Racks</span>
                                        <span class="text-sm font-medium">${data.overview?.total_racks || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Public Racks</span>
                                        <span class="text-sm font-medium text-green-600">${data.overview?.public_racks || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Pending Approval</span>
                                        <span class="text-sm font-medium text-yellow-600">${data.overview?.pending_approval || 0}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Processing Status</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Queue Jobs</span>
                                        <span class="text-sm font-medium">${data.processing?.pending_jobs || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Failed Jobs</span>
                                        <span class="text-sm font-medium text-red-600">${data.processing?.failed_jobs || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Success Rate</span>
                                        <span class="text-sm font-medium text-green-600">${data.processing?.success_rate || 0}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 dark:text-white mb-4">Content Engagement</h4>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">${data.engagement?.total_downloads || 0}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">Total Downloads</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">${data.engagement?.total_favorites || 0}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">Favorites</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600">${data.engagement?.total_ratings || 0}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">Ratings</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-yellow-600">${data.engagement?.avg_rating || 0}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">Avg Rating</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            renderEmailTab(container, data) {
                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Subscribers</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Active</span>
                                        <span class="text-sm font-medium text-green-600">${data.subscribers?.total_active || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">New (30d)</span>
                                        <span class="text-sm font-medium">${data.subscribers?.new_30d || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Growth Rate</span>
                                        <span class="text-sm font-medium ${data.subscribers?.growth_rate >= 0 ? 'text-green-600' : 'text-red-600'}">${data.subscribers?.growth_rate || 0}%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Email Performance</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Delivery Rate</span>
                                        <span class="text-sm font-medium text-green-600">${data.email_performance?.delivery_rate || 0}%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Open Rate</span>
                                        <span class="text-sm font-medium text-blue-600">${data.email_performance?.open_rate || 0}%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Click Rate</span>
                                        <span class="text-sm font-medium text-purple-600">${data.email_performance?.click_rate || 0}%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">System Health</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Queue Status</span>
                                        <span class="text-sm font-medium">${data.system_health?.queue_status?.pending || 0} pending</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Failed Emails</span>
                                        <span class="text-sm font-medium text-red-600">${data.system_health?.failed_emails || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">SMTP Status</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${data.system_health?.smtp_status?.status === 'connected' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${data.system_health?.smtp_status?.status || 'Unknown'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            renderUsersTab(container, data) {
                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">User Activity</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Daily Active</span>
                                        <span class="text-sm font-medium text-green-600">${data.engagement_metrics?.daily_active_users || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Monthly Active</span>
                                        <span class="text-sm font-medium text-blue-600">${data.engagement_metrics?.monthly_active_users || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Avg Session</span>
                                        <span class="text-sm font-medium">${data.engagement_metrics?.avg_session_duration || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">User Segments</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Power Users</span>
                                        <span class="text-sm font-medium text-purple-600">${data.user_segments?.power_users || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Active Commenters</span>
                                        <span class="text-sm font-medium text-green-600">${data.user_segments?.active_commenters || 0}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Inactive Users</span>
                                        <span class="text-sm font-medium text-gray-600">${data.user_segments?.inactive_users || 0}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            renderSystemTab(container, data) {
                container.innerHTML = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Database</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Status</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${data.database?.status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${data.database?.status || 'Unknown'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Connection Time</span>
                                        <span class="text-sm font-medium">${data.database?.connection_time || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Cache</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Status</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${data.cache?.status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${data.cache?.status || 'Unknown'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Driver</span>
                                        <span class="text-sm font-medium">${data.cache?.driver || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Performance</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">Memory</span>
                                        <span class="text-sm font-medium">${Math.round((data.performance?.memory_usage || 0) / 1024 / 1024)} MB</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-300">PHP Version</span>
                                        <span class="text-sm font-medium">${data.performance?.php_version || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            setupRealTimeUpdates() {
                // Update every 30 seconds
                this.refreshInterval = setInterval(() => {
                    this.updateRealTimeMetrics();
                }, 30000);
            }

            async updateRealTimeMetrics() {
                try {
                    const response = await fetch('/admin/analytics/real-time');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.updateStatusBar(result.data);
                        this.updateRealtimeChart(result.data);
                    }
                } catch (error) {
                    console.error('Failed to update real-time metrics:', error);
                }
            }

            updateStatusBar(data) {
                document.getElementById('active-users').textContent = data.active_users || 0;
                document.getElementById('queue-count').textContent = data.processing_queue?.pending || 0;
                document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
                
                const indicator = document.getElementById('status-indicator');
                if (data.system_health?.status === 'healthy') {
                    indicator.className = 'w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse';
                } else {
                    indicator.className = 'w-3 h-3 bg-red-400 rounded-full mr-2 animate-pulse';
                }
            }

            initRealtimeChart() {
                const ctx = document.getElementById('realtimeChart').getContext('2d');
                this.charts.realtime = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Active Users',
                            data: [],
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Queue Size',
                            data: [],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            updateRealtimeChart(data) {
                if (!this.charts.realtime) return;

                const now = new Date().toLocaleTimeString();
                const chart = this.charts.realtime;
                
                chart.data.labels.push(now);
                chart.data.datasets[0].data.push(data.active_users || 0);
                chart.data.datasets[1].data.push(data.processing_queue?.pending || 0);
                
                // Keep only last 20 data points
                if (chart.data.labels.length > 20) {
                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();
                    chart.data.datasets[1].data.shift();
                }
                
                chart.update();
            }

            async refreshData() {
                const button = document.getElementById('refresh-dashboard');
                const originalText = button.innerHTML;
                button.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';
                button.disabled = true;

                try {
                    await this.loadTabContent(this.currentTab);
                    await this.updateRealTimeMetrics();
                } finally {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            async exportData() {
                const sections = ['overview', 'racks', 'email', 'users', 'system'];
                
                try {
                    const response = await fetch('/admin/analytics/export', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            sections: sections,
                            format: 'json',
                            days: 30
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `analytics-export-${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    }
                } catch (error) {
                    console.error('Export failed:', error);
                    this.showError('Export failed');
                }
            }

            showError(message) {
                // Simple error display - could be enhanced with proper notifications
                console.error(message);
                alert(message);
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

        // Initialize dashboard when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.enhancedDashboard = new EnhancedDashboard();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (window.enhancedDashboard) {
                window.enhancedDashboard.destroy();
            }
        });
    </script>
    @endpush
</x-app-layout>