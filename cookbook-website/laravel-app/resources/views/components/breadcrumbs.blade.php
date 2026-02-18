@props(['items' => []])

@if(is_countable($items) && count($items) > 1)
<nav aria-label="Breadcrumb" class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
    @foreach($items as $index => $item)
        @if($index > 0)
            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
            </svg>
        @endif
        
        @if($index === count($items) - 1)
            <span class="font-medium text-gray-900" aria-current="page">{{ $item['name'] }}</span>
        @else
            <a href="{{ $item['url'] }}" class="hover:text-gray-900 transition-colors">{{ $item['name'] }}</a>
        @endif
    @endforeach
</nav>

{{-- Structured Data for Breadcrumbs --}}
<x-structured-data :data="app('App\Services\SeoService')->getStructuredData('breadcrumb', ['items' => $items])" />
@endif