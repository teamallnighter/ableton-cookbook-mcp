<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Blog') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Latest updates from the Ableton Cookbook team
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('blog.rss') }}" 
                   class="text-orange-600 hover:text-orange-700 flex items-center text-sm">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3.429 2.571v2.857c7.714 0 14.571 6.857 14.571 14.571h2.857C20.857 12.571 15.429 2.571 3.429 2.571zM3.429 8.286V11.143c4.714 0 8.571 3.857 8.571 8.571h2.857c0-6.286-5.143-11.429-11.428-11.429zM6.286 15.714c0 1.571-1.286 2.857-2.857 2.857s-2.857-1.286-2.857-2.857 1.286-2.857 2.857-2.857 2.857 1.286 2.857 2.857z"/>
                    </svg>
                    RSS Feed
                </a>
                
                <!-- Search -->
                <form method="GET" action="{{ route('blog.search') }}" class="flex">
                    <input type="text" 
                           name="q" 
                           value="{{ request('q') }}"
                           placeholder="Search posts..." 
                           class="border-gray-300 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-r-md text-sm font-medium">
                        Search
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-3">
                    <!-- Featured Posts -->
                    @if($featuredPosts->count() > 0 && !request()->filled('category'))
                        <div class="mb-12">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6">Featured Posts</h3>
                            <div class="grid grid-cols-1 md:grid-cols-{{ min(3, $featuredPosts->count()) }} gap-6">
                                @foreach($featuredPosts as $featuredPost)
                                    <article class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                                        @if($featuredPost->featured_image_path)
                                            <div class="aspect-video overflow-hidden">
                                                <img src="{{ Storage::url($featuredPost->featured_image_path) }}" 
                                                     alt="{{ $featuredPost->title }}"
                                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                            </div>
                                        @endif
                                        <div class="p-6">
                                            <div class="flex items-center space-x-2 mb-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white"
                                                      style="background-color: {{ $featuredPost->category->color }}">
                                                    {{ $featuredPost->category->name }}
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Featured
                                                </span>
                                            </div>
                                            <h3 class="text-lg font-semibold text-gray-900 mb-2 hover:text-blue-600">
                                                <a href="{{ route('blog.show', $featuredPost->slug) }}">
                                                    {{ $featuredPost->title }}
                                                </a>
                                            </h3>
                                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                                {{ $featuredPost->excerpt }}
                                            </p>
                                            <div class="flex items-center justify-between text-sm text-gray-500">
                                                <span>{{ $featuredPost->published_at->format('M j, Y') }}</span>
                                                <span>{{ $featuredPost->reading_time }} min read</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- All Posts -->
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">
                            @if(request()->filled('category'))
                                Posts in "{{ $posts->first()->category->name ?? 'Category' }}"
                            @else
                                Latest Posts
                            @endif
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @forelse($posts as $post)
                                <article class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                                    @if($post->featured_image_path)
                                        <div class="aspect-video overflow-hidden">
                                            <img src="{{ Storage::url($post->featured_image_path) }}" 
                                                 alt="{{ $post->title }}"
                                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                                        </div>
                                    @endif
                                    <div class="p-6">
                                        <div class="flex items-center space-x-2 mb-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white"
                                                  style="background-color: {{ $post->category->color }}">
                                                <a href="{{ route('blog.category', $post->category->slug) }}">
                                                    {{ $post->category->name }}
                                                </a>
                                            </span>
                                            @if($post->featured)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Featured
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <h3 class="text-lg font-semibold text-gray-900 mb-2 hover:text-blue-600">
                                            <a href="{{ route('blog.show', $post->slug) }}">
                                                {{ $post->title }}
                                            </a>
                                        </h3>
                                        
                                        <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                                            {{ $post->excerpt }}
                                        </p>
                                        
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3 text-sm text-gray-500">
                                                <div class="flex items-center">
                                                    <img src="{{ $post->author->profile_photo_url }}" 
                                                         alt="{{ $post->author->name }}" 
                                                         class="h-6 w-6 rounded-full mr-2">
                                                    {{ $post->author->name }}
                                                </div>
                                                <span>{{ $post->published_at->format('M j, Y') }}</span>
                                            </div>
                                            
                                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    {{ number_format($post->views_count) }}
                                                </span>
                                                <span>{{ $post->reading_time }} min read</span>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="col-span-2 text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No posts found</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ request()->filled('category') ? 'No posts in this category yet.' : 'No posts have been published yet.' }}
                                    </p>
                                </div>
                            @endforelse
                        </div>

                        <!-- Pagination -->
                        <div class="mt-8">
                            {{ $posts->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-8 space-y-8">
                        <!-- Categories -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Categories</h4>
                            <div class="space-y-3">
                                <a href="{{ route('blog.index') }}" 
                                   class="flex items-center justify-between text-sm {{ !request()->filled('category') ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                                    <span>All Posts</span>
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs">
                                        {{ $posts->total() }}
                                    </span>
                                </a>
                                @foreach($categories as $category)
                                    <a href="{{ route('blog.category', $category->slug) }}" 
                                       class="flex items-center justify-between text-sm {{ request('category') == $category->slug ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900' }}">
                                        <span class="flex items-center">
                                            <span class="w-3 h-3 rounded-full mr-2" 
                                                  style="background-color: {{ $category->color }}"></span>
                                            {{ $category->name }}
                                        </span>
                                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs">
                                            {{ $category->published_posts_count }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Newsletter Signup (Optional) -->
                        <div class="bg-blue-50 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">Stay Updated</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Get the latest updates about new racks, platform features, and community highlights.
                            </p>
                            <a href="{{ route('blog.rss') }}" 
                               class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3.429 2.571v2.857c7.714 0 14.571 6.857 14.571 14.571h2.857C20.857 12.571 15.429 2.571 3.429 2.571zM3.429 8.286V11.143c4.714 0 8.571 3.857 8.571 8.571h2.857c0-6.286-5.143-11.429-11.428-11.429zM6.286 15.714c0 1.571-1.286 2.857-2.857 2.857s-2.857-1.286-2.857-2.857 1.286-2.857 2.857-2.857 2.857 1.286 2.857 2.857z"/>
                                </svg>
                                Subscribe to RSS Feed
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
    @endpush
</x-app-layout>