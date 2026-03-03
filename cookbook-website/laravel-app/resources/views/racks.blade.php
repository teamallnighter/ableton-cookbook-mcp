<x-app-layout>
    {{-- Screen reader page heading (hidden visually, useful for accessibility) --}}
    <div class="sr-only">
        <h1>Ableton Cookbook - Share and Discover Ableton Live Racks</h1>
        <p>Browse and download high-quality Ableton Live racks including instrument racks, audio effect racks, and MIDI racks shared by music producers worldwide.</p>
    </div>

    {{-- Rack Browser (Livewire component) --}}
    @livewire('rack-browser')

    {{-- Recent Blog Posts Section --}}
    @if(isset($recentBlogPosts) && $recentBlogPosts->isNotEmpty())
    <section class="bg-white py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Latest from the Blog</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Stay updated with the latest tips, tricks, and insights for music production with Ableton Live.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                @foreach($recentBlogPosts as $post)
                <article class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-shadow">
                    @if($post->featured_image_path)
                    <div class="h-48 bg-gray-200 overflow-hidden">
                        <img src="{{ asset('storage/' . $post->featured_image_path) }}"
                            alt="{{ $post->title }}"
                            class="w-full h-full object-cover">
                    </div>
                    @endif

                    <div class="p-6">
                        <div class="flex items-center mb-3">
                            <span class="inline-block px-3 py-1 text-xs font-medium text-white rounded-full"
                                style="background-color: {{ $post->category->color }}">
                                {{ $post->category->name }}
                            </span>
                            <span class="text-sm text-gray-500 ml-3">
                                {{ $post->published_at->format('M j, Y') }}
                            </span>
                        </div>

                        <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600 transition-colors">
                                {{ $post->title }}
                            </a>
                        </h3>

                        <p class="text-gray-600 line-clamp-3 mb-4">
                            {{ $post->excerpt }}
                        </p>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">
                                By {{ $post->author->name }}
                            </span>
                            <a href="{{ route('blog.show', $post->slug) }}"
                                class="text-blue-600 hover:text-blue-800 font-medium text-sm transition-colors">
                                Read More →
                            </a>
                        </div>
                    </div>
                </article>
                @endforeach
            </div>

            <div class="text-center mt-8">
                <a href="{{ route('blog.index') }}"
                    class="inline-block bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                    View All Posts
                </a>
            </div>
        </div>
    </section>
    @endif
</x-app-layout>


