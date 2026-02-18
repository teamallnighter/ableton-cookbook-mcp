@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('Whoops, something went wrong on our servers.'))

@section('content')
<div class="min-h-screen bg-ableton-black flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <!-- Error Visual -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-ableton-danger/20 rounded-full mb-6">
                <svg class="w-10 h-10 text-ableton-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h1 class="text-6xl font-bold text-ableton-light mb-4">500</h1>
            <h2 class="text-2xl font-semibold text-ableton-light mb-4">Server Error</h2>
            <p class="text-ableton-light/70 mb-8">
                Our servers hit a wrong note. We're working to get things back in tune. 
                Please try again in a moment.
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-4">
            <button onclick="window.location.reload()" class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Try Again
            </button>
            
            <div class="flex gap-3 justify-center">
                <a href="{{ route('dashboard') }}" class="btn-secondary">Dashboard</a>
                <a href="{{ route('home') }}" class="btn-secondary">Browse Racks</a>
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