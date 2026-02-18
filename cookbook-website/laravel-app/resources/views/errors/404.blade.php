@extends('errors::minimal')

@section('title', __('Not Found'))
@section('code', '404')
@section('message', __('The page you are looking for could not be found.'))

@section('content')
<div class="min-h-screen bg-ableton-black flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <!-- Error Visual -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-ableton-danger/20 rounded-full mb-6">
                <svg class="w-10 h-10 text-ableton-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h1 class="text-6xl font-bold text-ableton-light mb-4">404</h1>
            <h2 class="text-2xl font-semibold text-ableton-light mb-4">Page Not Found</h2>
            <p class="text-ableton-light/70 mb-8">
                The rack you're looking for seems to have been moved or deleted. 
                Let's get you back to the music.
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-4">
            <a href="{{ route('dashboard') }}" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Back to Dashboard
            </a>
            
            <div class="flex gap-3 justify-center">
                <a href="{{ route('home') }}" class="btn-secondary">Browse Racks</a>
                <a href="{{ route('racks.upload') }}" class="btn-secondary">Upload Rack</a>
            </div>
        </div>

        <!-- Visual Element -->
        <div class="mt-12 text-ableton-light/20">
            <svg class="w-32 h-32 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
        </div>
    </div>
</div>
@endsection