<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :items="[
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Issues', 'url' => route('issues.index')]
        ]" />

        <div class="card card-body">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-black">Community Issues</h1>
                <a href="{{ route('issues.create') }}" 
                   class="px-4 py-2 bg-vibrant-green text-black font-semibold rounded-md hover:bg-vibrant-green/90 transition-colors">
                    Submit Issue
                </a>
            </div>

            {{-- Filters --}}
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_review" {{ request('status') === 'in_review' ? 'selected' : '' }}>In Review</option>
                            <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All Types</option>
                            @foreach($issueTypes as $type)
                                <option value="{{ $type->id }}" {{ request('type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-vibrant-green text-black font-semibold rounded-md hover:bg-vibrant-green/90 transition-colors">
                            Filter
                        </button>
                    </div>
                </form>
            </div>

            {{-- Issues Grid --}}
            <div class="grid gap-6">
                @forelse($issues as $issue)
                    <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-semibold text-black">
                                        <a href="{{ route('issues.show', $issue) }}" class="hover:text-vibrant-green">
                                            #{{ $issue->id }}: {{ $issue->title }}
                                        </a>
                                    </h3>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $issue->getStatusBadgeClass() }}">
                                        {{ ucfirst(str_replace('_', ' ', $issue->status)) }}
                                    </span>
                                </div>

                                <p class="text-gray-600 mb-3">{{ Str::limit($issue->description, 150) }}</p>

                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span class="inline-flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a1.994 1.994 0 01-1.414.586H7a4 4 0 01-4-4V7a4 4 0 014-4z"/>
                                        </svg>
                                        {{ $issue->issueType->display_name }}
                                    </span>

                                    <span class="inline-flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        @if($issue->user)
                                            {{ $issue->user->name }}
                                        @else
                                            {{ $issue->submitter_name ?: 'Anonymous' }}
                                        @endif
                                    </span>

                                    <span class="inline-flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ $issue->created_at->diffForHumans() }}
                                    </span>

                                    @if($issue->fileUploads->count() > 0)
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            {{ $issue->fileUploads->count() }} file(s)
                                        </span>
                                    @endif
                                </div>

                                @if($issue->rack)
                                    <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                                        <span class="text-sm text-blue-700">
                                            Related to rack: 
                                            <a href="{{ route('racks.show', $issue->rack) }}" class="font-medium hover:underline">
                                                {{ $issue->rack->title }}
                                            </a>
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="ml-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $issue->getPriorityBadgeClass() }}">
                                    {{ ucfirst($issue->priority) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No issues found</h3>
                        <p class="mt-1 text-sm text-gray-500">No issues match your current filter criteria.</p>
                        <div class="mt-6">
                            <a href="{{ route('issues.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-vibrant-green text-black font-semibold rounded-md hover:bg-vibrant-green/90 transition-colors">
                                Submit New Issue
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($issues->hasPages())
                <div class="mt-8">
                    {{ $issues->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
