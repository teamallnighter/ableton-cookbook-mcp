<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :items="[
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Issues', 'url' => route('issues.index')],
            ['name' => 'Issue #' . $issue->id, 'url' => route('issues.show', $issue)]
        ]" />

        <div class="card card-body">
            {{-- Issue Header --}}
            <div class="border-b border-gray-200 pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-black mb-2">
                            Issue #{{ $issue->id }}: {{ $issue->title }}
                        </h1>
                        <div class="flex items-center space-x-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $issue->getStatusBadgeClass() }}">
                                {{ ucfirst(str_replace('_', ' ', $issue->status)) }}
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $issue->getPriorityBadgeClass() }}">
                                {{ ucfirst($issue->priority) }} Priority
                            </span>
                            <span class="text-sm text-gray-500">{{ $issue->issueType->display_name }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                    <div>
                        <strong>Submitted:</strong> {{ $issue->created_at->format('M j, Y \a\t g:i A') }}
                    </div>
                    <div>
                        <strong>Submitter:</strong> 
                        @if($issue->user)
                            <a href="{{ route('users.show', $issue->user) }}" class="text-vibrant-green hover:underline">
                                {{ $issue->user->name }}
                            </a>
                        @else
                            {{ $issue->submitter_name ?: 'Anonymous' }}
                        @endif
                    </div>
                    @if($issue->resolved_at)
                        <div>
                            <strong>Resolved:</strong> {{ $issue->resolved_at->format('M j, Y \a\t g:i A') }}
                        </div>
                    @endif
                </div>

                @if($issue->rack)
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <strong>Related Rack:</strong> 
                        <a href="{{ route('racks.show', $issue->rack) }}" class="text-vibrant-green hover:underline">
                            {{ $issue->rack->name }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- Description --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">Description</h3>
                <div class="prose max-w-none bg-gray-50 p-4 rounded-lg">
                    {!! nl2br(e($issue->description)) !!}
                </div>
            </div>

            {{-- File Uploads --}}
            @if($issue->fileUploads->count() > 0)
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Uploaded Files</h3>
                    <div class="space-y-3">
                        @foreach($issue->fileUploads as $upload)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium">{{ $upload->original_filename }}</div>
                                    <div class="text-sm text-gray-600">
                                        {{ strtoupper($upload->file_type) }} • {{ $upload->formatted_file_size }}
                                        @if($upload->rack_name)
                                            • Rack: {{ $upload->rack_name }}
                                        @endif
                                        @if($upload->ableton_version)
                                            • Live {{ $upload->ableton_version }}
                                        @endif
                                    </div>
                                    @if($upload->rack_description)
                                        <div class="text-sm text-gray-700 mt-1">{{ $upload->rack_description }}</div>
                                    @endif
                                    @if($upload->tags)
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @foreach($upload->tags as $tag)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-200 text-gray-700">
                                                    {{ $tag }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $upload->created_at->format('M j, Y') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Comments --}}
            @if($issue->comments->where('is_public', true)->count() > 0)
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Updates & Comments</h3>
                    <div class="space-y-4">
                        @foreach($issue->comments->where('is_public', true) as $comment)
                            <div class="p-4 rounded-lg {{ $comment->is_admin_comment ? 'bg-blue-50 border-l-4 border-blue-400' : 'bg-gray-50' }}">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-medium text-sm">
                                        {{ $comment->author_name }}
                                        @if($comment->is_admin_comment)
                                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                                Admin
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $comment->created_at->format('M j, Y \a\t g:i A') }}
                                    </div>
                                </div>
                                <div class="prose max-w-none">
                                    {!! nl2br(e($comment->comment_text)) !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Add Comment Form (for authenticated users) --}}
            @auth
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold mb-3">Add Comment</h3>
                    <form method="POST" action="{{ route('issues.comments.store', $issue) }}">
                        @csrf
                        <div class="mb-4">
                            <textarea name="comment_text" rows="3" required
                                      placeholder="Add a comment to this issue..."
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="px-4 py-2 bg-vibrant-green text-black font-semibold rounded-md hover:bg-vibrant-green/90 transition-colors">
                                Add Comment
                            </button>
                        </div>
                    </form>
                </div>
            @endauth
        </div>
    </div>

    <script>
        function toggleFileUpload() {
            const issueTypeSelect = document.getElementById('issue_type_id');
            const fileUploadSection = document.getElementById('fileUploadSection');
            const rackFileInput = document.getElementById('rack_file');
            
            const selectedOption = issueTypeSelect.options[issueTypeSelect.selectedIndex];
            const allowsUpload = selectedOption?.dataset?.allowsUpload === 'true';
            
            if (allowsUpload) {
                fileUploadSection.classList.remove('hidden');
                rackFileInput.required = true;
            } else {
                fileUploadSection.classList.add('hidden');
                rackFileInput.required = false;
            }
        }
    </script>
</x-app-layout>
