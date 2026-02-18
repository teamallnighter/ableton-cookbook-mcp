<x-admin-layout>
    @section('header')
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Issue Management</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Manage and resolve platform issues and user feedback.
                </p>
            </div>
            <div class="flex space-x-3">
                <div class="text-sm text-gray-500 dark:text-gray-400 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                    Total: <span class="font-semibold">{{ $issues->total() }}</span> issues
                </div>
                <button id="bulk-actions-btn" class="admin-btn-secondary" disabled>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Bulk Actions
                </button>
                <button id="refresh-issues" class="admin-btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>
    @endsection

    <!-- Issue Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @php
            $statusCounts = [
                'pending' => $issues->where('status', 'pending')->count(),
                'in_review' => $issues->where('status', 'in_review')->count(),
                'resolved' => $issues->where('status', 'resolved')->count(),
                'urgent' => $issues->where('priority', 'urgent')->count(),
            ];
        @endphp
        
        <!-- Pending Issues -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $statusCounts['pending'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- In Review -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">In Review</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $statusCounts['in_review'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolved -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Resolved</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $statusCounts['resolved'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Urgent Issues -->
        <div class="admin-card hover:shadow-lg transition-shadow duration-200">
            <div class="admin-card-body">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                        <svg class="w-8 h-8 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Urgent</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $statusCounts['urgent'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Filters & Search</h3>
        </div>
        <div class="admin-card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4" id="issue-filters">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Search issues..." 
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="in_review" {{ request('status') === 'in_review' ? 'selected' : '' }}>In Review</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                    <select name="priority" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Priorities</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                    <select name="type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Types</option>
                        @foreach($issueTypes ?? [] as $type)
                            <option value="{{ $type->id }}" {{ request('type') == $type->id ? 'selected' : '' }}>
                                {{ $type->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end space-x-2">
                    <button type="submit" class="admin-btn-primary flex-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('admin.issues.index') }}" class="admin-btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Issues Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Issues List</h3>
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="select-all-issues" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All</span>
                    </label>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ $issues->firstItem() ?? 0 }} to {{ $issues->lastItem() ?? 0 }} of {{ $issues->total() }} results
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-card-body p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <input type="checkbox" class="rounded border-gray-300">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Submitter</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="issues-table-body">
                        @forelse($issues as $issue)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150" data-issue-id="{{ $issue->id }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="issue-checkbox rounded border-gray-300" value="{{ $issue->id }}">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            @if($issue->priority === 'urgent')
                                                <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                            @elseif($issue->priority === 'high')
                                                <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                            @else
                                                <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                #{{ $issue->id }}: {{ $issue->title }}
                                            </p>
                                            @if($issue->rack)
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    Related to: 
                                                    <a href="{{ route('racks.show', $issue->rack) }}" 
                                                       class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                        {{ Str::limit($issue->rack->title, 30) }}
                                                    </a>
                                                </p>
                                            @endif
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ Str::limit($issue->description, 80) }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($issue->user)
                                            <img class="h-8 w-8 rounded-full mr-3" src="{{ $issue->user->profile_photo_url }}" alt="{{ $issue->user->name }}">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $issue->user->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $issue->user->email }}</p>
                                            </div>
                                        @else
                                            <div class="h-8 w-8 bg-gray-300 rounded-full mr-3 flex items-center justify-center">
                                                <svg class="h-4 w-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $issue->submitter_name ?: 'Anonymous' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $issue->submitter_email ?: 'No email' }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($issue->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @elseif($issue->status === 'in_review') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif($issue->status === 'resolved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($issue->status === 'rejected') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $issue->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($issue->priority === 'urgent') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @elseif($issue->priority === 'high') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                        @elseif($issue->priority === 'medium') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                                        {{ ucfirst($issue->priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $issue->created_at->format('M j, Y') }}</div>
                                    <div class="text-xs">{{ $issue->created_at->format('g:i A') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('admin.issues.show', $issue) }}" 
                                           class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            View
                                        </a>
                                        <button onclick="quickStatusUpdate({{ $issue->id }}, 'in_review')" 
                                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                                @if($issue->status === 'in_review') disabled @endif>
                                            Review
                                        </button>
                                        <button onclick="quickStatusUpdate({{ $issue->id }}, 'resolved')" 
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                @if($issue->status === 'resolved') disabled @endif>
                                            Resolve
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium mb-2">No issues found</h3>
                                        <p class="text-sm">No issues match your current filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    @if($issues->hasPages())
        <div class="mt-6">
            <nav class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    @if($issues->onFirstPage())
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-default leading-5 rounded-md">
                            Previous
                        </span>
                    @else
                        <a href="{{ $issues->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-500 dark:hover:text-gray-400 focus:outline-none focus:shadow-outline-blue focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150">
                            Previous
                        </a>
                    @endif

                    @if($issues->hasMorePages())
                        <a href="{{ $issues->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 leading-5 rounded-md hover:text-gray-500 dark:hover:text-gray-400 focus:outline-none focus:shadow-outline-blue focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150">
                            Next
                        </a>
                    @else
                        <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 cursor-default leading-5 rounded-md">
                            Next
                        </span>
                    @endif
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-5">
                            Showing
                            <span class="font-medium">{{ $issues->firstItem() ?? 0 }}</span>
                            to
                            <span class="font-medium">{{ $issues->lastItem() ?? 0 }}</span>
                            of
                            <span class="font-medium">{{ $issues->total() }}</span>
                            results
                        </p>
                    </div>
                    <div>
                        {{ $issues->appends(request()->query())->links() }}
                    </div>
                </div>
            </nav>
        </div>
    @endif

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const issueManager = new IssueManager();
        });

        class IssueManager {
            constructor() {
                this.selectedIssues = new Set();
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupBulkActions();
            }

            bindEvents() {
                // Select all checkbox
                document.getElementById('select-all-issues')?.addEventListener('change', (e) => {
                    this.toggleSelectAll(e.target.checked);
                });

                // Individual checkboxes
                document.querySelectorAll('.issue-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', (e) => {
                        this.toggleIssueSelection(e.target.value, e.target.checked);
                    });
                });

                // Refresh button
                document.getElementById('refresh-issues')?.addEventListener('click', () => {
                    this.refreshIssues();
                });

                // Auto-submit filters on change
                const filterForm = document.getElementById('issue-filters');
                if (filterForm) {
                    const selects = filterForm.querySelectorAll('select');
                    selects.forEach(select => {
                        select.addEventListener('change', () => {
                            filterForm.submit();
                        });
                    });
                }
            }

            toggleSelectAll(checked) {
                document.querySelectorAll('.issue-checkbox').forEach(checkbox => {
                    checkbox.checked = checked;
                    this.toggleIssueSelection(checkbox.value, checked);
                });
            }

            toggleIssueSelection(issueId, selected) {
                if (selected) {
                    this.selectedIssues.add(issueId);
                } else {
                    this.selectedIssues.delete(issueId);
                }

                this.updateBulkActionsButton();
            }

            updateBulkActionsButton() {
                const button = document.getElementById('bulk-actions-btn');
                if (button) {
                    button.disabled = this.selectedIssues.size === 0;
                    button.textContent = `Bulk Actions (${this.selectedIssues.size})`;
                }
            }

            setupBulkActions() {
                // Implementation for bulk actions dropdown/modal
                console.log('Bulk actions setup completed');
            }

            refreshIssues() {
                const button = document.getElementById('refresh-issues');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';
                button.disabled = true;

                // Reload the page to get fresh data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }

        // Quick status update function
        async function quickStatusUpdate(issueId, newStatus) {
            try {
                const response = await window.AdminUtils.apiRequest(`/admin/issues/${issueId}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ 
                        status: newStatus,
                        quick_update: true 
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    // Update the row in the table
                    const row = document.querySelector(`[data-issue-id="${issueId}"]`);
                    if (row) {
                        const statusCell = row.querySelector('td:nth-child(4) span');
                        if (statusCell) {
                            statusCell.textContent = newStatus.replace('_', ' ').toUpperCase();
                            // Update status badge classes
                            statusCell.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(newStatus)}`;
                        }
                    }
                    window.AdminUtils.showFlashMessage(`Issue status updated to ${newStatus}`, 'success');
                } else {
                    window.AdminUtils.showFlashMessage('Failed to update issue status', 'error');
                }
            } catch (error) {
                console.error('Status update failed:', error);
                window.AdminUtils.showFlashMessage('Network error occurred', 'error');
            }
        }

        function getStatusBadgeClass(status) {
            const classes = {
                'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'in_review': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'resolved': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'rejected': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
            };
            return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
    </script>
    @endpush
</x-admin-layout>