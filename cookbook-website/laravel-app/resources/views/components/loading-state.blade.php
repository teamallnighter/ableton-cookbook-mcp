@props([
    'loadingState' => [],
    'component' => null,
    'size' => 'medium',
    'inline' => false,
    'overlay' => false
])

@php
    $isLoading = $loadingState['isLoading'] ?? false;
    $message = $loadingState['message'] ?? 'Loading...';
    $progress = $loadingState['progress'] ?? 0;
    $type = $loadingState['type'] ?? 'spinner';
    $enableRealTimeUpdates = $loadingState['enableRealTimeUpdates'] ?? false;
    $updateInterval = $loadingState['updateInterval'] ?? 5000;
    
    $containerClasses = [
        'loading-container',
        "loading-{$size}",
        $inline ? 'loading-inline' : '',
        $overlay ? 'loading-overlay' : '',
        $attributes->get('class', '')
    ];
@endphp

@if($isLoading)
<div 
    {{ $attributes->merge(['class' => implode(' ', array_filter($containerClasses))]) }}
    wire:ignore.self
    @if($enableRealTimeUpdates && $component)
        x-data="loadingStateHandler('{{ $component::class }}', {{ $updateInterval }})"
        x-init="startPolling()"
    @endif
    role="status" 
    aria-live="polite"
    aria-label="{{ $message }}"
>
    @if($type === 'spinner')
        <div class="loading-spinner">
            <svg class="loading-icon animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.3"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        
    @elseif($type === 'progress')
        <div class="loading-progress-container">
            <div class="loading-progress">
                <div 
                    class="loading-progress-bar" 
                    style="width: {{ $progress }}%"
                    role="progressbar" 
                    aria-valuenow="{{ $progress }}" 
                    aria-valuemin="0" 
                    aria-valuemax="100"
                ></div>
            </div>
            <div class="loading-percentage">{{ $progress }}%</div>
        </div>
        
    @elseif($type === 'skeleton')
        <div class="loading-skeleton">
            <div class="skeleton-line skeleton-line-title"></div>
            <div class="skeleton-line skeleton-line-text"></div>
            <div class="skeleton-line skeleton-line-text skeleton-line-short"></div>
        </div>
        
    @elseif($type === 'dots')
        <div class="loading-dots">
            <span class="loading-dot animate-bounce" style="animation-delay: 0ms"></span>
            <span class="loading-dot animate-bounce" style="animation-delay: 150ms"></span>
            <span class="loading-dot animate-bounce" style="animation-delay: 300ms"></span>
        </div>
        
    @else
        {{-- Default to spinner --}}
        <div class="loading-spinner">
            <svg class="loading-icon animate-spin" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" opacity="0.3"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
            </svg>
        </div>
    @endif
    
    <div class="loading-message">{{ $message }}</div>
    
    @if($loadingState['estimatedTime'] ?? null)
        <div class="loading-eta">
            Est. {{ $loadingState['estimatedTime'] }}
        </div>
    @endif
</div>

@push('scripts')
<script>
function loadingStateHandler(componentClass, updateInterval) {
    return {
        polling: false,
        pollTimer: null,
        
        startPolling() {
            if (this.polling) return;
            
            this.polling = true;
            this.pollTimer = setInterval(() => {
                // Call Livewire component's pollStatus method
                Livewire.find('{{ $component ? $component->getId() : '' }}')?.call('pollStatus');
            }, updateInterval);
        },
        
        stopPolling() {
            this.polling = false;
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        
        destroy() {
            this.stopPolling();
        }
    }
}

// Handle connection recovery
document.addEventListener('connectionRecovered', () => {
    // Resume polling for all active loading states
    document.querySelectorAll('[x-data*="loadingStateHandler"]').forEach(el => {
        if (el._x_dataStack && el._x_dataStack[0] && !el._x_dataStack[0].polling) {
            el._x_dataStack[0].startPolling();
        }
    });
});

// Global loading state events
document.addEventListener('loadingStarted', (event) => {
    console.log('Loading started:', event.detail);
});

document.addEventListener('loadingProgress', (event) => {
    console.log('Loading progress:', event.detail);
});

document.addEventListener('loadingStopped', (event) => {
    console.log('Loading stopped:', event.detail);
});

document.addEventListener('operationCompleted', (event) => {
    console.log('Operation completed:', event.detail);
    
    // Show success notification
    if (window.loadingManager) {
        window.loadingManager.showSuccess(
            event.detail.component,
            'Operation completed successfully'
        );
    }
});
</script>
@endpush

@endif