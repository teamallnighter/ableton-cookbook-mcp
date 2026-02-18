/**
 * Comprehensive Error Handling Service for Ableton Cookbook
 * Provides application-wide error boundaries, user-friendly messages, and recovery mechanisms
 * Integrates with memory leak prevention and performance monitoring
 */

class GlobalErrorHandler {
    constructor() {
        this.errorQueue = [];
        this.maxQueueSize = 50;
        this.retryAttempts = new Map();
        this.maxRetries = 3;
        this.isOnline = navigator.onLine;
        
        // Track error patterns for intelligent recovery
        this.errorPatterns = {
            network: /network|fetch|cors|timeout/i,
            permission: /permission|unauthorized|403|401/i,
            validation: /validation|invalid|required/i,
            server: /500|502|503|504|server error/i,
            client: /syntax|reference|type.*error/i
        };
        
        // User-friendly error messages
        this.errorMessages = {
            network: {
                title: 'Connection Problem',
                message: 'Please check your internet connection and try again.',
                action: 'Retry'
            },
            permission: {
                title: 'Access Denied',
                message: 'You don\'t have permission to perform this action.',
                action: 'Login Again'
            },
            validation: {
                title: 'Input Error',
                message: 'Please check your input and try again.',
                action: 'Fix Input'
            },
            server: {
                title: 'Server Error',
                message: 'Our servers are experiencing issues. Please try again in a moment.',
                action: 'Retry'
            },
            client: {
                title: 'Page Error',
                message: 'Something went wrong on this page. Refreshing might help.',
                action: 'Refresh Page'
            },
            default: {
                title: 'Something Went Wrong',
                message: 'An unexpected error occurred. Please try again.',
                action: 'Retry'
            }
        };
        
        this.init();
    }
    
    init() {
        this.setupGlobalHandlers();
        this.setupNetworkMonitoring();
        this.setupPerformanceMonitoring();
        this.createNotificationContainer();
    }
    
    setupGlobalHandlers() {
        // Handle uncaught JavaScript errors
        window.addEventListener('error', (event) => {
            this.handleError({
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error,
                type: 'javascript'
            });
        });
        
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.handleError({
                message: event.reason?.message || 'Promise rejection',
                error: event.reason,
                type: 'promise'
            });
            
            // Prevent the error from showing in console if we handle it
            event.preventDefault();
        });
        
        // Handle fetch errors specifically
        this.interceptFetch();
    }
    
    setupNetworkMonitoring() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showSuccessNotification('Connection restored', 'You\'re back online!');
            
            // Retry failed network requests
            this.retryFailedRequests();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showErrorNotification('offline', {
                title: 'Connection Lost',
                message: 'You\'re currently offline. Some features may not work.',
                persistent: true
            });
        });
    }
    
    setupPerformanceMonitoring() {
        // Monitor page load performance
        window.addEventListener('load', () => {
            setTimeout(() => {
                const navigation = performance.getEntriesByType('navigation')[0];
                if (navigation && navigation.loadEventEnd > 10000) {
                    this.showWarningNotification(
                        'Slow Loading',
                        'The page took longer than expected to load. Consider refreshing if you experience issues.'
                    );
                }
            }, 1000);
        });
        
        // Monitor memory usage if available
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                const usedPercent = (memory.usedJSHeapSize / memory.jsHeapSizeLimit) * 100;
                
                if (usedPercent > 90) {
                    this.showWarningNotification(
                        'High Memory Usage',
                        'The page is using a lot of memory. Consider refreshing to improve performance.'
                    );
                }
            }, 30000); // Check every 30 seconds
        }
    }
    
    interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            try {
                const response = await originalFetch(...args);
                
                // Handle HTTP error status codes
                if (!response.ok) {
                    const errorType = this.categorizeHttpError(response.status);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response;
            } catch (error) {
                // Only handle network errors here, let application code handle others
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    this.handleError({
                        message: error.message,
                        error: error,
                        type: 'network',
                        url: args[0]
                    });
                }
                throw error; // Re-throw so application code can handle it
            }
        };
    }
    
    handleError(errorInfo) {
        // Prevent duplicate errors
        const errorKey = this.generateErrorKey(errorInfo);
        if (this.hasRecentError(errorKey)) {
            return;
        }
        
        // Add to queue
        this.errorQueue.push({
            ...errorInfo,
            timestamp: Date.now(),
            key: errorKey
        });
        
        // Limit queue size
        if (this.errorQueue.length > this.maxQueueSize) {
            this.errorQueue.shift();
        }
        
        // Categorize and handle error
        const errorType = this.categorizeError(errorInfo);
        const shouldShowNotification = this.shouldShowNotification(errorInfo);
        
        if (shouldShowNotification) {
            this.showErrorNotification(errorType, errorInfo);
        }
        
        // Log for debugging
        console.error('Global Error Handler:', {
            type: errorType,
            info: errorInfo,
            timestamp: new Date().toISOString()
        });
        
        // Send to server for monitoring (if enabled)
        if (window.errorReporting && window.errorReporting.enabled) {
            this.reportToServer(errorInfo, errorType);
        }
    }
    
    categorizeError(errorInfo) {
        const message = (errorInfo.message || '').toLowerCase();
        
        for (const [type, pattern] of Object.entries(this.errorPatterns)) {
            if (pattern.test(message)) {
                return type;
            }
        }
        
        // Check by error type
        if (errorInfo.type === 'network' || errorInfo.type === 'promise') {
            return 'network';
        }
        
        return 'default';
    }
    
    categorizeHttpError(status) {
        if (status >= 400 && status < 500) {
            if (status === 401 || status === 403) return 'permission';
            if (status === 422) return 'validation';
            return 'client';
        }
        if (status >= 500) return 'server';
        return 'default';
    }
    
    generateErrorKey(errorInfo) {
        return `${errorInfo.type || 'unknown'}-${errorInfo.message || 'no-message'}`.substring(0, 100);
    }
    
    hasRecentError(errorKey) {
        const recentErrors = this.errorQueue.filter(
            error => error.key === errorKey && 
            Date.now() - error.timestamp < 5000 // 5 seconds
        );
        return recentErrors.length > 0;
    }
    
    shouldShowNotification(errorInfo) {
        // Don't show notifications for certain error types
        const silentErrors = [
            /script error/i,
            /non-error promise rejection/i
        ];
        
        return !silentErrors.some(pattern => 
            pattern.test(errorInfo.message || '')
        );
    }
    
    createNotificationContainer() {
        this.container = document.createElement('div');
        this.container.id = 'error-notifications';
        this.container.className = 'fixed top-4 right-4 z-50 space-y-2';
        this.container.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            max-width: 400px;
            pointer-events: none;
        `;
        document.body.appendChild(this.container);
    }
    
    showErrorNotification(errorType, errorInfo = {}) {
        const config = this.errorMessages[errorType] || this.errorMessages.default;
        const notification = this.createNotification(config, 'error', errorInfo);
        
        // Add retry functionality
        const retryBtn = notification.querySelector('[data-action="retry"]');
        if (retryBtn && this.canRetry(errorInfo)) {
            retryBtn.onclick = () => {
                this.retryOperation(errorInfo);
                notification.remove();
            };
        }
    }
    
    showWarningNotification(title, message) {
        const notification = this.createNotification({
            title,
            message,
            action: 'Dismiss'
        }, 'warning');
    }
    
    showSuccessNotification(title, message) {
        const notification = this.createNotification({
            title,
            message,
            action: 'Dismiss'
        }, 'success');
        
        // Auto-remove success notifications
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 3000);
    }
    
    createNotification(config, type = 'error', errorInfo = {}) {
        const notification = document.createElement('div');
        notification.style.cssText = 'pointer-events: auto;';
        
        const colors = {
            error: 'bg-red-500 border-red-600',
            warning: 'bg-yellow-500 border-yellow-600',
            success: 'bg-green-500 border-green-600'
        };
        
        const icons = {
            error: '⚠️',
            warning: '⚠️',
            success: '✅'
        };
        
        notification.className = `${colors[type]} text-white p-4 rounded-lg shadow-lg border-l-4 max-w-sm`;
        notification.innerHTML = `
            <div class="flex items-start">
                <span class="text-lg mr-3 mt-0.5">${icons[type]}</span>
                <div class="flex-1">
                    <h4 class="font-semibold mb-1">${config.title}</h4>
                    <p class="text-sm opacity-90 mb-3">${config.message}</p>
                    <div class="flex gap-2">
                        ${config.action && type === 'error' ? `
                            <button data-action="retry" class="text-xs px-3 py-1 bg-white bg-opacity-20 rounded hover:bg-opacity-30 transition-all">
                                ${config.action}
                            </button>
                        ` : ''}
                        <button onclick="this.closest('div').remove()" class="text-xs px-3 py-1 bg-white bg-opacity-20 rounded hover:bg-opacity-30 transition-all">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        this.container.appendChild(notification);
        
        // Animate in
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
            notification.style.transition = 'all 0.3s ease-out';
        });
        
        // Auto-remove after delay (unless persistent)
        if (!errorInfo.persistent) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(100%)';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 300);
                }
            }, type === 'error' ? 8000 : 5000);
        }
        
        return notification;
    }
    
    canRetry(errorInfo) {
        const retryKey = this.generateErrorKey(errorInfo);
        const attempts = this.retryAttempts.get(retryKey) || 0;
        return attempts < this.maxRetries && this.isOnline;
    }
    
    retryOperation(errorInfo) {
        const retryKey = this.generateErrorKey(errorInfo);
        const attempts = this.retryAttempts.get(retryKey) || 0;
        
        if (attempts >= this.maxRetries) {
            this.showErrorNotification('default', {
                title: 'Max Retries Reached',
                message: 'Please refresh the page or try again later.'
            });
            return;
        }
        
        this.retryAttempts.set(retryKey, attempts + 1);
        
        // Show retry notification
        this.showSuccessNotification(
            'Retrying...',
            `Attempt ${attempts + 1} of ${this.maxRetries}`
        );
        
        // Specific retry logic based on error type
        switch (errorInfo.type) {
            case 'network':
                if (errorInfo.url) {
                    // Retry the failed request
                    window.location.reload();
                }
                break;
            default:
                // Generic retry - reload page
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
        }
    }
    
    retryFailedRequests() {
        // Clear retry attempts for network errors when back online
        for (const [key, attempts] of this.retryAttempts.entries()) {
            if (key.startsWith('network-')) {
                this.retryAttempts.delete(key);
            }
        }
    }
    
    async reportToServer(errorInfo, errorType) {
        try {
            await fetch('/api/errors', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    type: errorType,
                    message: errorInfo.message,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                })
            });
        } catch (reportError) {
            console.warn('Failed to report error to server:', reportError);
        }
    }
    
    // Public API
    clearNotifications() {
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
    
    getErrorStats() {
        const stats = {};
        this.errorQueue.forEach(error => {
            const type = this.categorizeError(error);
            stats[type] = (stats[type] || 0) + 1;
        });
        return stats;
    }
}

// Initialize global error handler
document.addEventListener('DOMContentLoaded', () => {
    window.globalErrorHandler = new GlobalErrorHandler();
    
    // Expose API for debugging
    window.debugErrors = {
        getStats: () => window.globalErrorHandler.getErrorStats(),
        clearNotifications: () => window.globalErrorHandler.clearNotifications(),
        simulateError: (type = 'test') => {
            window.globalErrorHandler.handleError({
                message: `Test ${type} error`,
                type: type
            });
        }
    };
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GlobalErrorHandler;
}