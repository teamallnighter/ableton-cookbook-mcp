/**
 * Enhanced Loading State Management
 * Provides consistent loading indicators, error handling, and retry mechanisms
 */

class LoadingStateManager {
    constructor() {
        this.activeStates = new Map();
        this.retryAttempts = new Map();
        this.maxRetries = 3;
        this.retryDelays = {
            linear: (attempt) => 1000 * attempt,
            exponential: (attempt) => Math.min(1000 * Math.pow(2, attempt), 60000),
            fixed: () => 3000
        };
        
        // Initialize global error handler
        this.setupGlobalErrorHandler();
        
        // Bind methods to preserve context
        this.showLoading = this.showLoading.bind(this);
        this.hideLoading = this.hideLoading.bind(this);
        this.showError = this.showError.bind(this);
        this.retry = this.retry.bind(this);
    }
    
    /**
     * Show loading state for an element or operation
     */
    showLoading(identifier, options = {}) {
        const config = {
            element: null,
            message: 'Loading...',
            showProgress: false,
            progress: 0,
            estimatedTime: null,
            allowCancel: false,
            type: 'spinner', // spinner, skeleton, progress, dots
            size: 'medium', // small, medium, large
            overlay: false,
            ...options
        };
        
        this.activeStates.set(identifier, config);
        
        if (config.element) {
            this.renderLoadingState(config.element, config);
        } else {
            this.showGlobalLoading(identifier, config);
        }
        
        return identifier;
    }
    
    /**
     * Update loading progress
     */
    updateProgress(identifier, progress, message = null) {
        const state = this.activeStates.get(identifier);
        if (!state) return;
        
        state.progress = Math.max(0, Math.min(100, progress));
        if (message) state.message = message;
        
        const element = state.element;
        if (element) {
            const progressBar = element.querySelector('.loading-progress-bar');
            if (progressBar) {
                progressBar.style.width = `${state.progress}%`;
                progressBar.setAttribute('aria-valuenow', state.progress);
            }
            
            const messageEl = element.querySelector('.loading-message');
            if (messageEl && message) {
                messageEl.textContent = message;
            }
        }
        
        // Dispatch progress event
        document.dispatchEvent(new CustomEvent('loadingProgress', {
            detail: { identifier, progress: state.progress, message: state.message }
        }));
    }
    
    /**
     * Hide loading state
     */
    hideLoading(identifier) {
        const state = this.activeStates.get(identifier);
        if (!state) return;
        
        if (state.element) {
            this.clearLoadingState(state.element);
        } else {
            this.hideGlobalLoading(identifier);
        }
        
        this.activeStates.delete(identifier);
        this.retryAttempts.delete(identifier);
    }
    
    /**
     * Show error with retry option
     */
    showError(identifier, error, options = {}) {
        const config = {
            message: error.message || 'An error occurred',
            code: error.code,
            severity: error.severity || 'medium',
            userAction: error.user_action,
            isRetryable: error.is_retryable ?? true,
            retryDelay: error.retry_delay || 3000,
            retryStrategy: error.retry_strategy,
            showDetails: false,
            autoHide: false,
            ...options
        };
        
        // Hide any active loading state
        this.hideLoading(identifier);
        
        // Render error state
        if (config.element) {
            this.renderErrorState(config.element, config, identifier);
        } else {
            this.showGlobalError(identifier, config);
        }
        
        // Auto-retry for certain error types
        if (config.isRetryable && this.shouldAutoRetry(error)) {
            const attempts = this.retryAttempts.get(identifier) || 0;
            if (attempts < this.maxRetries) {
                setTimeout(() => {
                    this.retry(identifier);
                }, config.retryDelay);
            }
        }
    }
    
    /**
     * Retry operation
     */
    async retry(identifier, customCallback = null) {
        const attempts = this.retryAttempts.get(identifier) || 0;
        
        if (attempts >= this.maxRetries) {
            this.showError(identifier, {
                message: 'Maximum retry attempts exceeded',
                isRetryable: false,
                severity: 'high'
            });
            return false;
        }
        
        this.retryAttempts.set(identifier, attempts + 1);
        
        // Show retry loading state
        this.showLoading(identifier, {
            message: `Retrying... (${attempts + 1}/${this.maxRetries})`,
            type: 'spinner',
            allowCancel: true
        });
        
        try {
            // Execute retry callback or original operation
            const result = customCallback ? 
                await customCallback() : 
                await this.executeRetry(identifier);
                
            this.hideLoading(identifier);
            this.retryAttempts.delete(identifier);
            
            return result;
        } catch (error) {
            const retryDelay = this.calculateRetryDelay(attempts + 1, error.retry_strategy);
            
            this.showError(identifier, error, {
                retryDelay,
                showRetryCountdown: true
            });
            
            return false;
        }
    }
    
    /**
     * Render loading state in element
     */
    renderLoadingState(element, config) {
        const loadingHtml = this.generateLoadingHtml(config);
        
        // Store original content
        if (!element.dataset.originalContent) {
            element.dataset.originalContent = element.innerHTML;
        }
        
        // Add loading class and content
        element.classList.add('loading-active');
        element.innerHTML = loadingHtml;
        element.setAttribute('aria-busy', 'true');
        
        // Handle overlay
        if (config.overlay) {
            element.style.position = 'relative';
            element.classList.add('loading-overlay');
        }
    }
    
    /**
     * Generate loading HTML based on type
     */
    generateLoadingHtml(config) {
        const sizeClass = `loading-${config.size}`;
        
        switch (config.type) {
            case 'spinner':
                return `
                    <div class="loading-container ${sizeClass}">
                        <div class="loading-spinner" role="status" aria-label="Loading">
                            <svg class="loading-icon" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" opacity="0.3"/>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round">
                                    <animateTransform attributeName="transform" type="rotate" dur="1s" repeatCount="indefinite" values="0 12 12;360 12 12"/>
                                </path>
                            </svg>
                        </div>
                        <div class="loading-message">${config.message}</div>
                        ${config.estimatedTime ? `<div class="loading-eta">Est. ${config.estimatedTime}</div>` : ''}
                        ${config.allowCancel ? '<button class="loading-cancel-btn">Cancel</button>' : ''}
                    </div>
                `;
                
            case 'progress':
                return `
                    <div class="loading-container ${sizeClass}">
                        <div class="loading-progress">
                            <div class="loading-progress-bar" style="width: ${config.progress}%" role="progressbar" 
                                 aria-valuenow="${config.progress}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="loading-message">${config.message}</div>
                        <div class="loading-percentage">${config.progress}%</div>
                    </div>
                `;
                
            case 'skeleton':
                return `
                    <div class="loading-container ${sizeClass}">
                        <div class="loading-skeleton">
                            <div class="skeleton-line skeleton-line-title"></div>
                            <div class="skeleton-line skeleton-line-text"></div>
                            <div class="skeleton-line skeleton-line-text skeleton-line-short"></div>
                        </div>
                    </div>
                `;
                
            case 'dots':
                return `
                    <div class="loading-container ${sizeClass}">
                        <div class="loading-dots">
                            <span class="loading-dot"></span>
                            <span class="loading-dot"></span>
                            <span class="loading-dot"></span>
                        </div>
                        <div class="loading-message">${config.message}</div>
                    </div>
                `;
                
            default:
                return this.generateLoadingHtml({ ...config, type: 'spinner' });
        }
    }
    
    /**
     * Render error state
     */
    renderErrorState(element, config, identifier) {
        const errorHtml = `
            <div class="error-container error-${config.severity}">
                <div class="error-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="error-content">
                    <div class="error-message">${config.message}</div>
                    ${config.userAction ? `<div class="error-action">${config.userAction}</div>` : ''}
                    <div class="error-controls">
                        ${config.isRetryable ? `<button class="error-retry-btn" data-identifier="${identifier}">Try Again</button>` : ''}
                        <button class="error-dismiss-btn" data-identifier="${identifier}">Dismiss</button>
                    </div>
                </div>
            </div>
        `;
        
        element.innerHTML = errorHtml;
        element.classList.add('error-active');
        element.setAttribute('aria-live', 'polite');
        
        // Bind event listeners
        this.bindErrorEventListeners(element, identifier);
    }
    
    /**
     * Clear loading state from element
     */
    clearLoadingState(element) {
        element.classList.remove('loading-active', 'loading-overlay', 'error-active');
        element.removeAttribute('aria-busy');
        element.removeAttribute('aria-live');
        
        if (element.dataset.originalContent) {
            element.innerHTML = element.dataset.originalContent;
            delete element.dataset.originalContent;
        }
    }
    
    /**
     * Show global loading notification
     */
    showGlobalLoading(identifier, config) {
        const notification = document.createElement('div');
        notification.id = `loading-${identifier}`;
        notification.className = 'global-loading-notification';
        notification.innerHTML = this.generateLoadingHtml(config);
        
        document.body.appendChild(notification);
        
        // Auto-position
        this.positionGlobalNotification(notification);
    }
    
    /**
     * Show global error notification
     */
    showGlobalError(identifier, config) {
        const notification = document.createElement('div');
        notification.id = `error-${identifier}`;
        notification.className = `global-error-notification error-${config.severity}`;
        notification.innerHTML = `
            <div class="error-content">
                <div class="error-message">${config.message}</div>
                ${config.userAction ? `<div class="error-action">${config.userAction}</div>` : ''}
                <div class="error-controls">
                    ${config.isRetryable ? `<button class="error-retry-btn" data-identifier="${identifier}">Retry</button>` : ''}
                    <button class="error-close-btn" data-identifier="${identifier}">×</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Bind event listeners
        this.bindErrorEventListeners(notification, identifier);
        
        // Auto-hide after delay
        if (config.autoHide) {
            setTimeout(() => {
                this.hideGlobalError(identifier);
            }, 5000);
        }
        
        // Position notification
        this.positionGlobalNotification(notification);
    }
    
    /**
     * Hide global notifications
     */
    hideGlobalLoading(identifier) {
        const element = document.getElementById(`loading-${identifier}`);
        if (element) element.remove();
    }
    
    hideGlobalError(identifier) {
        const element = document.getElementById(`error-${identifier}`);
        if (element) element.remove();
    }
    
    /**
     * Position global notifications
     */
    positionGlobalNotification(element) {
        const notifications = document.querySelectorAll('.global-loading-notification, .global-error-notification');
        const index = Array.from(notifications).indexOf(element);
        
        element.style.top = `${20 + (index * 80)}px`;
        element.style.right = '20px';
        element.style.zIndex = 9999 + index;
    }
    
    /**
     * Bind error event listeners
     */
    bindErrorEventListeners(container, identifier) {
        const retryBtn = container.querySelector('.error-retry-btn');
        const dismissBtn = container.querySelector('.error-dismiss-btn');
        const closeBtn = container.querySelector('.error-close-btn');
        
        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.retry(identifier));
        }
        
        if (dismissBtn || closeBtn) {
            const btn = dismissBtn || closeBtn;
            btn.addEventListener('click', () => {
                this.hideLoading(identifier);
                if (container.parentElement === document.body) {
                    container.remove();
                }
            });
        }
    }
    
    /**
     * Setup global error handler
     */
    setupGlobalErrorHandler() {
        window.addEventListener('unhandledrejection', (event) => {
            const error = event.reason;
            
            if (error && typeof error === 'object' && error.code) {
                this.showError('global-unhandled', error, {
                    autoHide: true,
                    severity: 'medium'
                });
            }
        });
        
        // Handle network errors
        window.addEventListener('offline', () => {
            this.showError('network-offline', {
                message: 'You are offline. Some features may not work.',
                isRetryable: false,
                severity: 'medium',
                autoHide: false
            });
        });
        
        window.addEventListener('online', () => {
            this.hideGlobalError('network-offline');
        });
    }
    
    /**
     * Calculate retry delay based on strategy
     */
    calculateRetryDelay(attempt, strategy = null) {
        if (!strategy) return 3000;
        
        const delayFn = this.retryDelays[strategy.type] || this.retryDelays.fixed;
        return Math.min(delayFn(attempt), strategy.max_delay || 60000);
    }
    
    /**
     * Check if error should auto-retry
     */
    shouldAutoRetry(error) {
        if (!error.is_retryable) return false;
        
        const autoRetryErrors = ['network_error', 'temporary_failure', 'service_unavailable'];
        return autoRetryErrors.includes(error.code);
    }
    
    /**
     * Execute retry for identifier
     */
    async executeRetry(identifier) {
        // This should be overridden by specific implementations
        // or use a registry of retry callbacks
        const event = new CustomEvent('retryOperation', {
            detail: { identifier },
            bubbles: true
        });
        
        document.dispatchEvent(event);
        
        return new Promise((resolve) => {
            setTimeout(resolve, 1000); // Placeholder
        });
    }
}

// Global instance
window.loadingManager = new LoadingStateManager();

// Convenience functions
window.showLoading = window.loadingManager.showLoading;
window.hideLoading = window.loadingManager.hideLoading;
window.showError = window.loadingManager.showError;
window.updateProgress = window.loadingManager.updateProgress;

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LoadingStateManager;
}