@props(['disabled' => false, 'error' => null])

@php
    $hasError = !is_null($error);
    
    $baseClasses = 'w-full px-4 py-2 text-ableton-light bg-ableton-gray border rounded transition-colors focus:outline-none placeholder-ableton-light/50';
    
    $stateClasses = $hasError 
        ? 'border-ableton-danger focus:border-ableton-danger focus:ring-2 focus:ring-ableton-danger/50' 
        : 'border-ableton-light/20 focus:border-ableton-accent focus:ring-2 focus:ring-ableton-accent/50';
    
    $disabledClasses = $disabled ? 'opacity-50 cursor-not-allowed' : '';
    
    $classes = $baseClasses . ' ' . $stateClasses . ' ' . $disabledClasses;
@endphp

<div class="w-full">
    <input {{ $disabled ? 'disabled' : '' }} 
           {!! $attributes->merge(['class' => $classes])->except(['error']) !!}>
    
    @if($hasError && $error)
        <p class="mt-1 text-sm text-ableton-danger">{{ $error }}</p>
    @endif
</div>
