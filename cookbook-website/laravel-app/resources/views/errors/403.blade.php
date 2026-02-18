@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message', __('You do not have permission to access this resource.'))

@section('content')
<div class="min-h-screen bg-ableton-black flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <!-- Error Visual -->
        <div class="mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-ableton-warning/20 rounded-full mb-6">
                <svg class="w-10 h-10 text-ableton-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-6xl font-bold text-ableton-light mb-4">403</h1>
            <h2 class="text-2xl font-semibold text-ableton-light mb-4">Access Denied</h2>
            <p class="text-ableton-light/70 mb-8">
                This track is locked. You don't have permission to access this resource. 
                Make sure you're logged in with the right credentials.
            </p>
        </div>

        <!-- Actions -->
        <div class="space-y-4">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Back to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign In
                </a>
            @endauth
            
            <div class="flex gap-3 justify-center">
                <a href="{{ route('home') }}" class="btn-secondary">Browse Public Racks</a>
                @auth
                    <a href="{{ route('profile.show') }}" class="btn-secondary">My Profile</a>
                @endauth
            </div>
        </div>

        <!-- Visual Element -->
        <div class="mt-12 text-ableton-light/20">
            <svg class="w-32 h-32 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
    </div>
</div>
@endsection