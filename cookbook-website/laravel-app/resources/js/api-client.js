/**
 * Enhanced API Client with Error Handling and Loading States
 */

class ApiClient {
    constructor(options = {}) {
        this.baseURL = options.baseURL || '/api/v1';
        this.timeout = options.timeout || 30000;
        this.retryAttempts = options.retryAttempts || 3;
        this.loadingManager = window.loadingManager;
        
        // Request interceptors
        this.requestInterceptors = [];
        this.responseInterceptors = [];
        
        // Default headers
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        };
        
        // CSRF token handling
        this.setupCSRFToken();
        
        // Request tracking
        this.activeRequests = new Map();
        this.requestId = 0;
    }
    
    /**
     * Setup CSRF token from meta tag
     */
    setupCSRFToken() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            this.defaultHeaders['X-CSRF-TOKEN'] = token;
        }
    }
    
    /**
     * Add request interceptor
     */
    addRequestInterceptor(interceptor) {
        this.requestInterceptors.push(interceptor);
    }
    
    /**
     * Add response interceptor
     */
    addResponseInterceptor(interceptor) {
        this.responseInterceptors.push(interceptor);
    }
    
    /**
     * Make HTTP request with enhanced error handling
     */
    async request(url, options = {}) {
        const requestId = ++this.requestId;
        const fullUrl = url.startsWith('http') ? url : `${this.baseURL}${url}`;
        
        const config = {
            method: 'GET',
            headers: { ...this.defaultHeaders },
            timeout: this.timeout,
            showLoading: true,
            loadingMessage: 'Loading...',
            loadingElement: null,
            retryable: true,
            ...options
        };
        
        // Apply request interceptors
        for (const interceptor of this.requestInterceptors) {
            await interceptor(config);
        }
        
        // Show loading state
        if (config.showLoading) {
            const loadingId = `request-${requestId}`;
            this.loadingManager?.showLoading(loadingId, {
                element: config.loadingElement,
                message: config.loadingMessage,
                type: config.loadingType || 'spinner',
                allowCancel: true
            });
            
            config.loadingId = loadingId;
        }
        
        // Track active request
        const controller = new AbortController();
        config.signal = controller.signal;
        
        this.activeRequests.set(requestId, {
            controller,
            url: fullUrl,
            config,
            timestamp: Date.now()
        });
        
        try {
            const response = await this.executeRequest(fullUrl, config, requestId);
            
            // Apply response interceptors
            for (const interceptor of this.responseInterceptors) {
                await interceptor(response);
            }
            
            return response;
            
        } catch (error) {
            throw await this.handleRequestError(error, fullUrl, config, requestId);
            
        } finally {
            // Clean up
            this.activeRequests.delete(requestId);
            if (config.loadingId) {
                this.loadingManager?.hideLoading(config.loadingId);
            }
        }\n    }\n    \n    /**\n     * Execute the actual HTTP request\n     */\n    async executeRequest(url, config, requestId) {\n        const timeoutPromise = new Promise((_, reject) => {\n            setTimeout(() => {\n                reject(new Error('Request timeout'));\n            }, config.timeout);\n        });\n        \n        const fetchPromise = fetch(url, {\n            method: config.method,\n            headers: config.headers,\n            body: config.body,\n            signal: config.signal,\n            credentials: 'same-origin'\n        });\n        \n        const response = await Promise.race([fetchPromise, timeoutPromise]);\n        \n        // Update loading with response info\n        if (config.loadingId && response.headers.get('content-length')) {\n            const contentLength = parseInt(response.headers.get('content-length'));\n            if (contentLength > 100000) { // 100KB\n                this.loadingManager?.updateProgress(\n                    config.loadingId, \n                    50, \n                    'Processing response...'\n                );\n            }\n        }\n        \n        if (!response.ok) {\n            const errorData = await this.parseErrorResponse(response);\n            throw new ApiError(errorData, response.status, response.statusText);\n        }\n        \n        const data = await response.json();\n        \n        // Handle loading states in response\n        if (data.loading) {\n            return this.handleLoadingResponse(data, config);\n        }\n        \n        // Handle success response\n        if (data.success === false) {\n            throw new ApiError(data.error || data, response.status);\n        }\n        \n        return data;\n    }\n    \n    /**\n     * Handle loading response from server\n     */\n    async handleLoadingResponse(data, config) {\n        if (config.loadingId) {\n            this.loadingManager?.showLoading(config.loadingId, {\n                message: data.status || 'Processing...',\n                type: 'progress',\n                progress: data.progress?.percentage || 0,\n                estimatedTime: data.progress?.estimated_time\n            });\n            \n            // Poll for status updates\n            return this.pollForCompletion(data, config);\n        }\n        \n        return data;\n    }\n    \n    /**\n     * Poll for operation completion\n     */\n    async pollForCompletion(initialData, config, pollCount = 0) {\n        const maxPolls = 30; // 5 minutes at 10 second intervals\n        const pollInterval = 10000; // 10 seconds\n        \n        if (pollCount >= maxPolls) {\n            throw new ApiError({\n                code: 'POLLING_TIMEOUT',\n                message: 'Operation timed out'\n            });\n        }\n        \n        await new Promise(resolve => setTimeout(resolve, pollInterval));\n        \n        try {\n            // Make status request\n            const statusResponse = await this.request(\n                initialData.status_url || `${config.url}/status`,\n                {\n                    showLoading: false,\n                    retryable: false\n                }\n            );\n            \n            if (statusResponse.loading) {\n                // Update progress\n                if (config.loadingId) {\n                    this.loadingManager?.updateProgress(\n                        config.loadingId,\n                        statusResponse.progress?.percentage || 0,\n                        statusResponse.status\n                    );\n                }\n                \n                // Continue polling\n                return this.pollForCompletion(statusResponse, config, pollCount + 1);\n            }\n            \n            // Operation completed\n            return statusResponse;\n            \n        } catch (error) {\n            if (pollCount < 3) { // Allow a few polling failures\n                return this.pollForCompletion(initialData, config, pollCount + 1);\n            }\n            throw error;\n        }\n    }\n    \n    /**\n     * Parse error response\n     */\n    async parseErrorResponse(response) {\n        try {\n            const data = await response.json();\n            return data.error || data;\n        } catch {\n            return {\n                code: 'PARSE_ERROR',\n                message: `HTTP ${response.status}: ${response.statusText}`,\n                status: response.status\n            };\n        }\n    }\n    \n    /**\n     * Handle request errors with retry logic\n     */\n    async handleRequestError(error, url, config, requestId, attempt = 1) {\n        const isApiError = error instanceof ApiError;\n        const errorData = isApiError ? error.data : {\n            code: 'NETWORK_ERROR',\n            message: error.message,\n            is_retryable: true\n        };\n        \n        // Show error state\n        if (config.loadingId) {\n            this.loadingManager?.showError(config.loadingId, errorData, {\n                element: config.loadingElement\n            });\n        }\n        \n        // Check if we should retry\n        if (config.retryable && errorData.is_retryable && attempt <= this.retryAttempts) {\n            const retryDelay = this.calculateRetryDelay(attempt, errorData.retry_strategy);\n            \n            // Show retry countdown\n            if (config.loadingId) {\n                this.loadingManager?.showLoading(config.loadingId, {\n                    message: `Retrying in ${Math.ceil(retryDelay / 1000)} seconds... (${attempt}/${this.retryAttempts})`,\n                    type: 'spinner'\n                });\n            }\n            \n            await new Promise(resolve => setTimeout(resolve, retryDelay));\n            \n            try {\n                const response = await this.executeRequest(url, config, requestId);\n                return response;\n            } catch (retryError) {\n                return this.handleRequestError(retryError, url, config, requestId, attempt + 1);\n            }\n        }\n        \n        // No retry - throw the error\n        throw isApiError ? error : new ApiError(errorData);\n    }\n    \n    /**\n     * Calculate retry delay\n     */\n    calculateRetryDelay(attempt, strategy = null) {\n        if (!strategy) {\n            return Math.min(1000 * Math.pow(2, attempt - 1), 10000);\n        }\n        \n        switch (strategy.type) {\n            case 'linear':\n                return Math.min(strategy.base_delay * attempt, strategy.max_delay || 30000);\n            case 'exponential':\n                return Math.min(strategy.base_delay * Math.pow(2, attempt - 1), strategy.max_delay || 60000);\n            case 'fixed':\n                return strategy.delay || 3000;\n            default:\n                return 3000;\n        }\n    }\n    \n    /**\n     * Cancel request\n     */\n    cancelRequest(requestId) {\n        const request = this.activeRequests.get(requestId);\n        if (request) {\n            request.controller.abort();\n            this.activeRequests.delete(requestId);\n        }\n    }\n    \n    /**\n     * Cancel all active requests\n     */\n    cancelAllRequests() {\n        for (const [requestId] of this.activeRequests) {\n            this.cancelRequest(requestId);\n        }\n    }\n    \n    /**\n     * Convenience methods\n     */\n    get(url, options = {}) {\n        return this.request(url, { ...options, method: 'GET' });\n    }\n    \n    post(url, data, options = {}) {\n        return this.request(url, {\n            ...options,\n            method: 'POST',\n            body: JSON.stringify(data)\n        });\n    }\n    \n    put(url, data, options = {}) {\n        return this.request(url, {\n            ...options,\n            method: 'PUT',\n            body: JSON.stringify(data)\n        });\n    }\n    \n    patch(url, data, options = {}) {\n        return this.request(url, {\n            ...options,\n            method: 'PATCH',\n            body: JSON.stringify(data)\n        });\n    }\n    \n    delete(url, options = {}) {\n        return this.request(url, { ...options, method: 'DELETE' });\n    }\n    \n    /**\n     * Upload file with progress\n     */\n    async upload(url, formData, options = {}) {\n        const config = {\n            method: 'POST',\n            body: formData,\n            headers: { ...this.defaultHeaders },\n            showLoading: true,\n            loadingMessage: 'Uploading...',\n            loadingType: 'progress',\n            ...options\n        };\n        \n        // Remove content-type for FormData\n        delete config.headers['Content-Type'];\n        \n        return this.request(url, config);\n    }\n}\n\n/**\n * API Error class\n */\nclass ApiError extends Error {\n    constructor(data, status = null, statusText = null) {\n        const message = data.message || data.error || 'An API error occurred';\n        super(message);\n        \n        this.name = 'ApiError';\n        this.data = data;\n        this.status = status;\n        this.statusText = statusText;\n        this.code = data.code;\n        this.isRetryable = data.is_retryable ?? false;\n        this.severity = data.severity || 'medium';\n        this.userAction = data.user_action;\n    }\n}\n\n// Global instance\nwindow.apiClient = new ApiClient();\n\n// Auto-retry for authentication errors\nwindow.apiClient.addResponseInterceptor(async (response) => {\n    if (response.status === 401) {\n        // Try to refresh the CSRF token\n        const token = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');\n        if (token) {\n            window.apiClient.defaultHeaders['X-CSRF-TOKEN'] = token;\n        }\n    }\n});\n\n// Handle connection recovery\nwindow.addEventListener('online', () => {\n    // Retry failed requests when connection is restored\n    document.dispatchEvent(new CustomEvent('connectionRecovered'));\n});\n\n// Export for modules\nif (typeof module !== 'undefined' && module.exports) {\n    module.exports = { ApiClient, ApiError };\n}"