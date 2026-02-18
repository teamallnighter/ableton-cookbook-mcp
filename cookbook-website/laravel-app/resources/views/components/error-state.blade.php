@props([
    'loadingState' => [],
    'component' => null,
    'size' => 'medium',
    'inline' => false,
    'showDetails' => false
])

@php
    $error = $loadingState['error'] ?? null;
    $errorCode = $loadingState['errorCode'] ?? null;
    $canRetry = $loadingState['canRetry'] ?? false;
    $retryAttempts = $loadingState['retryAttempts'] ?? 0;
    $maxRetries = $loadingState['maxRetries'] ?? 3;
    
    // Determine severity from error code
    $severity = 'medium';
    if ($errorCode) {
        try {
            $enumValue = \App\Enums\ErrorCode::from($errorCode);
            $severity = $enumValue->severity();
        } catch (\Exception $e) {
            // Keep default severity
        }
    }
    
    $containerClasses = [
        'error-container',
        "error-{$severity}",
        "error-{$size}",
        $inline ? 'error-inline' : '',
        $attributes->get('class', '')
    ];
@endphp

@if($error)
<div 
    {{ $attributes->merge(['class' => implode(' ', array_filter($containerClasses))]) }}
    wire:ignore.self
    role="alert"
    aria-live="assertive"
>
    <div class="error-icon">
        @if($severity === 'critical')
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        @elseif($severity === 'high')
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        @elseif($severity === 'medium')
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        @else
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
            </svg>
        @endif
    </div>
    
    <div class="error-content">
        <div class="error-message">{{ $error }}</div>
        
        @if($errorCode && $showDetails)
            <div class="error-details">
                <button 
                    type="button"
                    class="error-details-toggle"
                    x-data="{ showDetails: false }"
                    @click="showDetails = !showDetails"
                >
                    <span x-show="!showDetails">Show details</span>
                    <span x-show="showDetails">Hide details</span>
                </button>
                
                <div x-show="showDetails" x-collapse class="error-details-content">
                    <div class="error-code">Error Code: {{ $errorCode }}</div>
                    @if($retryAttempts > 0)
                        <div class="error-attempts">Retry Attempts: {{ $retryAttempts }}/{{ $maxRetries }}</div>
                    @endif
                </div>
            </div>
        @endif
        
        @php
            $userAction = null;
            if ($errorCode) {
                try {
                    $enumValue = \App\Enums\ErrorCode::from($errorCode);
                    $userAction = $enumValue->userAction();
                } catch (\Exception $e) {
                    // No user action available
                }
            }
        @endphp
        
        @if($userAction)
            <div class="error-action">{{ $userAction }}</div>
        @endif
        
        <div class="error-controls">
            @if($canRetry && $component)
                <button 
                    type="button"
                    class="error-retry-btn"
                    wire:click="retryOperation"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="retryOperation">
                        @if($retryAttempts > 0)
                            Try Again ({{ $maxRetries - $retryAttempts }} left)
                        @else
                            Try Again
                        @endif
                    </span>
                    <span wire:loading wire:target="retryOperation" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.25"/>
                            <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Retrying...
                    </span>
                </button>
            @endif
            
            <button 
                type="button"
                class="error-dismiss-btn"
                @if($component)
                    wire:click="$emit('errorDismissed')"
                @else
                    onclick="this.closest('.error-container').style.display = 'none'"
                @endif
            >
                Dismiss
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Handle error events
document.addEventListener('errorOccurred', (event) => {
    console.error('Component error:', event.detail);
    
    // Show browser notification for critical errors if permitted
    if (event.detail.severity === 'critical' && 'Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('Application Error', {
                body: event.detail.error,
                icon: '/favicon.ico',
                tag: 'app-error'
            });
        } else if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('Application Error', {
                        body: event.detail.error,
                        icon: '/favicon.ico',
                        tag: 'app-error'
                    });
                }
            });
        }
    }
    
    // Log to external service if configured
    if (window.errorLogger) {
        window.errorLogger.log(event.detail);
    }
});

document.addEventListener('errorDismissed', (event) => {
    console.log('Error dismissed:', event.detail);
});

// Auto-dismiss non-critical errors after delay
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.error-container:not(.error-critical):not(.error-high)').forEach(errorEl => {
        setTimeout(() => {
            if (errorEl.parentElement) {
                errorEl.style.transition = 'opacity 0.5s ease';
                errorEl.style.opacity = '0';
                setTimeout(() => {
                    if (errorEl.parentElement) {
                        errorEl.remove();
                    }
                }, 500);
            }
        }, 10000); // 10 second auto-dismiss for medium/low severity errors
    });
});
</script>
@endpush

@endif