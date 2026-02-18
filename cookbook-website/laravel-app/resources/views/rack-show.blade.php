<x-app-layout>
    @php
        $seoMetaTags = app('App\Services\SeoService')->getRackMetaTags($rack);
        $structuredData = app('App\Services\SeoService')->getStructuredData('rack', ['rack' => $rack]);
    @endphp

    <x-slot name="breadcrumbs">
        [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Racks', 'url' => route('home')],
            ['name' => $rack->title, 'url' => route('racks.show', $rack)]
        ]
    </x-slot>

    {{-- Hidden structured content for SEO --}}
    <div class="sr-only">
        <h1>{{ $rack->title }} - {{ ucfirst($rack->rack_type) }} Rack for Ableton Live</h1>
        <p>Download {{ $rack->title }}, a high-quality {{ $rack->rack_type }} rack for Ableton Live. Created by {{ $rack->user->name }}.</p>
    </div>

    @livewire('rack-show', ['rack' => $rack])
</x-app-layout>