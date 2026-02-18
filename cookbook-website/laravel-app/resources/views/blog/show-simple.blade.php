<x-app-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <article class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-8">
                    <h1 class="text-4xl font-bold text-gray-900 leading-tight mb-6">
                        {{ $post->title }}
                    </h1>

                    <div class="text-xl text-gray-600 leading-relaxed mb-8">
                        {{ $post->excerpt }}
                    </div>

                    <div class="prose prose-lg max-w-none mb-12">
                        {!! $post->html_content !!}
                    </div>

                    <div class="flex items-center mb-8">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                {{ $post->author->name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $post->published_at->format('M j, Y') }}
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>