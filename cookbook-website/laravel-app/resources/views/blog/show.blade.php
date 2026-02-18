<x-app-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <article class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-8">
                    <!-- Category -->
                    <div class="mb-6">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white"
                              style="background-color: {{ $post->category->color }}">
                            {{ $post->category->name }}
                        </span>
                        
                        <span class="text-sm text-gray-500 ml-4">
                            {{ $post->published_at->format('F j, Y') }}
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-4xl font-bold text-gray-900 leading-tight mb-6">
                        {{ $post->title }}
                    </h1>

                    <!-- Excerpt -->
                    <div class="text-xl text-gray-600 leading-relaxed mb-8">
                        {{ $post->excerpt }}
                    </div>

                    <!-- Author -->
                    <div class="flex items-center mb-8 pb-8 border-b border-gray-200">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                {{ $post->author->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $post->published_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="prose prose-lg max-w-none mb-12">
                        {!! $post->html_content !!}
                    </div>

                    <!-- Back to Blog -->
                    <div class="mt-8">
                        <a href="{{ route('blog.index') }}" class="text-blue-600 hover:underline">
                            ← Back to Blog
                        </a>
                    </div>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>