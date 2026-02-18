@php
    $seoMetaTags = app('App\Services\SeoService')->getUserMetaTags($user);
    $structuredData = app('App\Services\SeoService')->getStructuredData('user', ['user' => $user]);
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :items="[
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Producers', 'url' => route('home')],
            ['name' => $user->name, 'url' => route('users.show', $user)]
        ]" />

        {{-- Hidden SEO content --}}
        <div class="sr-only">
            <h1>{{ $user->name }} - Music Producer Profile</h1>
            <p>Discover Ableton Live racks created by {{ $user->name }}. Browse their instrument racks, audio effect racks, and MIDI racks for your music production workflow.</p>
        </div>

        {{-- Profile Content --}}
        @livewire('user-profile', ['user' => $user])
    </div>
</x-app-layout>
