<x-admin-layout>
    @section('header')
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Management</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Manage newsletters, campaigns, and email analytics for your platform.
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.newsletter.create') }}" class="admin-btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Campaign
                </a>
                <button id="sync-email-data" class="admin-btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sync Data
                </button>
            </div>
        </div>
    @endsection

    <!-- Email Performance Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Subscribers -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <div class="flex items-baseline">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="total-subscribers">2,547</p>
                            <p class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">+12.5%</p>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Subscribers</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Rate -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M12 12v7"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <div class="flex items-baseline">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="open-rate">24.8%</p>
                            <p class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">+2.1%</p>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Average Open Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Click Rate -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <div class="flex items-baseline">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="click-rate">3.2%</p>
                            <p class="ml-2 text-sm font-medium text-red-600 dark:text-red-400">-0.3%</p>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Click-through Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unsubscribe Rate -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                        <svg class="w-8 h-8 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <div class="flex items-baseline">
                            <p class="text-2xl font-semibold text-gray-900 dark:text-white" id="unsubscribe-rate">0.8%</p>
                            <p class="ml-2 text-sm font-medium text-green-600 dark:text-green-400">-0.1%</p>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Unsubscribe Rate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Email Performance Over Time -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Performance Trends</h3>
                    <select id="performance-period" class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="performanceTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Campaign Types Performance -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Campaign Performance by Type</h3>
            </div>
            <div class="admin-card-body">
                <div class="chart-container">
                    <canvas id="campaignTypesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriber Growth and Campaigns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Subscriber Growth -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Subscriber Growth</h3>
            </div>
            <div class="admin-card-body">
                <div class="chart-container h-48">
                    <canvas id="subscriberGrowthChart"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">New subscribers (30d)</span>
                        <span class="font-medium text-gray-900 dark:text-white">+324</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Unsubscribed (30d)</span>
                        <span class="font-medium text-red-600 dark:text-red-400">-23</span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold">
                        <span class="text-gray-900 dark:text-white">Net growth</span>
                        <span class="text-green-600 dark:text-green-400">+301</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Campaigns -->
        <div class="admin-card lg:col-span-2">
            <div class="admin-card-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Campaigns</h3>
                    <a href="{{ route('admin.newsletter.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        View All →
                    </a>
                </div>
            </div>
            <div class="admin-card-body p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Opens</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Clicks</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @php
                                $sampleCampaigns = [
                                    [
                                        'id' => 1,
                                        'title' => 'Weekly Rack Roundup #45',
                                        'sent_at' => now()->subDays(2),
                                        'recipients' => 2547,
                                        'opens' => 634,
                                        'clicks' => 89,
                                        'status' => 'sent'
                                    ],
                                    [
                                        'id' => 2,
                                        'title' => 'New Features Update',
                                        'sent_at' => now()->subDays(5),
                                        'recipients' => 2521,
                                        'opens' => 723,
                                        'clicks' => 156,
                                        'status' => 'sent'
                                    ],
                                    [
                                        'id' => 3,
                                        'title' => 'Producer Spotlight: Deep House',
                                        'sent_at' => null,
                                        'recipients' => 0,
                                        'opens' => 0,
                                        'clicks' => 0,
                                        'status' => 'draft'
                                    ]
                                ];
                            @endphp

                            @foreach($sampleCampaigns as $campaign)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $campaign['title'] }}
                                            </div>
                                            @if($campaign['sent_at'])
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Sent {{ $campaign['sent_at']->format('M j, Y g:i A') }}
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Draft created
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ number_format($campaign['recipients']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        @if($campaign['recipients'] > 0)
                                            {{ number_format($campaign['opens']) }}
                                            <span class="text-xs text-gray-500">({{ number_format(($campaign['opens'] / $campaign['recipients']) * 100, 1) }}%)</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        @if($campaign['recipients'] > 0)
                                            {{ number_format($campaign['clicks']) }}
                                            <span class="text-xs text-gray-500">({{ number_format(($campaign['clicks'] / $campaign['recipients']) * 100, 1) }}%)</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($campaign['status'] === 'sent') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @elseif($campaign['status'] === 'draft') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                                            {{ ucfirst($campaign['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            @if($campaign['status'] === 'sent')
                                                <button class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    View Report
                                                </button>
                                            @else
                                                <button class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                    Edit
                                                </button>
                                                <button class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                    Send Test
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Health & Deliverability -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Email Health Score -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Email Health Score</h3>
            </div>
            <div class="admin-card-body">
                <div class="flex items-center justify-center mb-6">
                    <div class="relative w-32 h-32">
                        <canvas id="healthScoreChart" width="128" height="128"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">87</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">out of 100</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Deliverability</span>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: 95%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">95%</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Engagement</span>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: 78%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">78%</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">List Quality</span>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2 mr-2">
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: 82%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">82%</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="text-sm">
                            <p class="font-medium text-blue-800 dark:text-blue-200">Good performance!</p>
                            <p class="text-blue-700 dark:text-blue-300">Your email health is above average. Consider improving engagement rates.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deliverability Issues -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Deliverability Status</h3>
            </div>
            <div class="admin-card-body">
                <div class="space-y-4">
                    <!-- SMTP Status -->
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">SMTP Server</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Connected</span>
                    </div>

                    <!-- SPF Status -->
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">SPF Record</span>
                        </div>
                        <span class="text-sm text-green-600 dark:text-green-400">Valid</span>
                    </div>

                    <!-- DKIM Status -->
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">DKIM Signature</span>
                        </div>
                        <span class="text-sm text-yellow-600 dark:text-yellow-400">Needs Setup</span>
                    </div>

                    <!-- DMARC Status -->
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">DMARC Policy</span>
                        </div>
                        <span class="text-sm text-red-600 dark:text-red-400">Not Configured</span>
                    </div>
                </div>

                <!-- Recent Issues -->
                <div class="mt-6">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Recent Issues</h4>
                    <div class="space-y-2">
                        <div class="flex items-start p-2 bg-red-50 dark:bg-red-900/30 rounded-lg">
                            <svg class="w-4 h-4 text-red-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="font-medium text-red-800 dark:text-red-200">45 bounced emails</p>
                                <p class="text-red-700 dark:text-red-300">Campaign: Weekly Roundup #44</p>
                            </div>
                        </div>

                        <div class="flex items-start p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                            <svg class="w-4 h-4 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="text-sm">
                                <p class="font-medium text-yellow-800 dark:text-yellow-200">High spam score detected</p>
                                <p class="text-yellow-700 dark:text-yellow-300">Template: Newsletter Base</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailDashboard = new EmailDashboard();
        });

        class EmailDashboard {
            constructor() {
                this.charts = {};
                this.init();
            }

            init() {
                this.initCharts();
                this.bindEvents();
            }

            initCharts() {
                // Performance Trend Chart
                const performanceTrendCtx = document.getElementById('performanceTrendChart').getContext('2d');
                this.charts.performanceTrend = new Chart(performanceTrendCtx, {
                    type: 'line',
                    data: {
                        labels: this.generateDateLabels(30),
                        datasets: [
                            {
                                label: 'Open Rate %',
                                data: this.generateRandomData(30, 20, 30),
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Click Rate %',
                                data: this.generateRandomData(30, 2, 5),
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
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Campaign Types Chart
                const campaignTypesCtx = document.getElementById('campaignTypesChart').getContext('2d');
                this.charts.campaignTypes = new Chart(campaignTypesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Newsletter', 'Product Updates', 'Promotional', 'Welcome Series', 'Re-engagement'],
                        datasets: [{
                            data: [45, 25, 15, 10, 5],
                            backgroundColor: [
                                'rgb(59, 130, 246)',
                                'rgb(34, 197, 94)',
                                'rgb(251, 191, 36)',
                                'rgb(147, 51, 234)',
                                'rgb(239, 68, 68)'
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
                                position: 'bottom'
                            }
                        }
                    }
                });

                // Subscriber Growth Chart
                const subscriberGrowthCtx = document.getElementById('subscriberGrowthChart').getContext('2d');
                this.charts.subscriberGrowth = new Chart(subscriberGrowthCtx, {
                    type: 'line',
                    data: {
                        labels: this.generateDateLabels(30),
                        datasets: [{
                            label: 'Subscribers',
                            data: this.generateGrowthData(30, 2200, 2547),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
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
                                beginAtZero: false
                            }
                        }
                    }
                });

                // Health Score Chart
                const healthScoreCtx = document.getElementById('healthScoreChart').getContext('2d');
                this.charts.healthScore = new Chart(healthScoreCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [87, 13],
                            backgroundColor: ['rgb(34, 197, 94)', 'rgba(156, 163, 175, 0.2)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: false,
                        plugins: { legend: { display: false } },
                        cutout: '80%'
                    }
                });
            }

            bindEvents() {
                // Sync email data
                document.getElementById('sync-email-data').addEventListener('click', () => {
                    this.syncEmailData();
                });

                // Performance period change
                document.getElementById('performance-period').addEventListener('change', (e) => {
                    this.updatePerformanceChart(parseInt(e.target.value));
                });
            }

            async syncEmailData() {
                const button = document.getElementById('sync-email-data');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Syncing...';
                button.disabled = true;

                try {
                    // Simulate API call to sync email data
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                    // Update metrics on the page
                    this.updateMetrics();
                    
                    window.AdminUtils.showFlashMessage('Email data synchronized successfully', 'success');
                } catch (error) {
                    console.error('Sync failed:', error);
                    window.AdminUtils.showFlashMessage('Failed to sync email data', 'error');
                } finally {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            }

            updatePerformanceChart(days) {
                const chart = this.charts.performanceTrend;
                chart.data.labels = this.generateDateLabels(days);
                chart.data.datasets[0].data = this.generateRandomData(days, 20, 30);
                chart.data.datasets[1].data = this.generateRandomData(days, 2, 5);
                chart.update();
            }

            updateMetrics() {
                // Simulate updating metrics with slight variations
                const openRate = (Math.random() * 5 + 22).toFixed(1);
                const clickRate = (Math.random() * 1.5 + 2.5).toFixed(1);
                
                document.getElementById('open-rate').textContent = `${openRate}%`;
                document.getElementById('click-rate').textContent = `${clickRate}%`;
            }

            generateDateLabels(days) {
                const labels = [];
                const now = new Date();
                for (let i = days - 1; i >= 0; i--) {
                    const date = new Date(now.getTime() - (i * 24 * 60 * 60 * 1000));
                    labels.push(date.getMonth() + 1 + '/' + date.getDate());
                }
                return labels;
            }

            generateRandomData(count, min, max) {
                const data = [];
                for (let i = 0; i < count; i++) {
                    data.push((Math.random() * (max - min) + min).toFixed(1));
                }
                return data;
            }

            generateGrowthData(count, start, end) {
                const data = [];
                const increment = (end - start) / count;
                for (let i = 0; i < count; i++) {
                    data.push(Math.floor(start + (increment * i) + (Math.random() * 50 - 25)));
                }
                return data;
            }

            destroy() {
                Object.values(this.charts).forEach(chart => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (window.emailDashboard) {
                window.emailDashboard.destroy();
            }
        });
    </script>
    @endpush
</x-admin-layout>