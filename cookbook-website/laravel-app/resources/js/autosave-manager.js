/**
 * AutoSave Manager - Comprehensive Auto-Save System
 * 
 * Provides robust auto-save functionality with:
 * - Optimistic locking and version control
 * - Conflict detection and resolution
 * - Connection recovery and offline support
 * - Proper debouncing and state management
 * - Comprehensive error handling and logging
 */
class AutoSaveManager {
    constructor(rackId, options = {}) {
        this.rackId = rackId;
        this.options = {
            debounceDelay: 2000,        // 2 seconds debounce
            retryDelay: 1000,           // 1 second base retry delay
            maxRetries: 3,              // Maximum retry attempts
            heartbeatInterval: 30000,   // 30 seconds heartbeat
            offlineThreshold: 5000,     // 5 seconds to consider offline
            conflictCheckInterval: 10000, // 10 seconds conflict check
            ...options
        };

        // State management
        this.state = {
            sessionId: this.generateSessionId(),
            currentVersion: null,
            isOnline: navigator.onLine,
            pendingOperations: new Map(),
            saveQueue: [],
            conflicts: [],
            lastSync: null,
            retryCount: new Map(),
            isProcessingQueue: false,
            fieldStates: new Map() // Per-field state tracking
        };

        // Event tracking
        this.events = new EventTarget();
        
        // Initialize
        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.setupNetworkDetection();
        this.setupHeartbeat();
        this.loadOfflineData();
        
        // Get initial state
        await this.syncWithServer();
        
        // Start conflict monitoring
        this.startConflictMonitoring();
        
        console.log('AutoSaveManager initialized', {
            sessionId: this.state.sessionId,
            rackId: this.rackId
        });
    }

    /**
     * Schedule auto-save for a field
     */
    scheduleAutoSave(field, value, options = {}) {
        const operationId = `${field}_${Date.now()}`;
        
        // Clear any existing debounce for this field
        if (this.state.pendingOperations.has(field)) {
            clearTimeout(this.state.pendingOperations.get(field).timeoutId);
        }

        // Update field state
        this.updateFieldState(field, {
            value: value,
            isDirty: true,
            lastChange: Date.now(),
            status: 'pending'
        });

        // Create debounced save operation
        const timeoutId = setTimeout(() => {
            this.enqueueSave({
                operationId,
                field,
                value,
                timestamp: Date.now(),
                ...options
            });
        }, this.options.debounceDelay);

        // Store pending operation
        this.state.pendingOperations.set(field, {
            operationId,
            timeoutId,
            field,
            value,
            timestamp: Date.now()
        });

        this.emitEvent('saveScheduled', { field, operationId });
    }

    /**
     * Enqueue save operation
     */
    enqueueSave(operation) {
        // Remove any existing operations for the same field
        this.state.saveQueue = this.state.saveQueue.filter(op => op.field !== operation.field);
        
        // Add new operation
        this.state.saveQueue.push(operation);
        
        this.emitEvent('saveEnqueued', operation);
        
        // Process queue if not already processing
        if (!this.state.isProcessingQueue) {
            this.processSaveQueue();
        }
    }

    /**
     * Process save queue
     */
    async processSaveQueue() {
        if (this.state.isProcessingQueue || this.state.saveQueue.length === 0) {
            return;
        }

        this.state.isProcessingQueue = true;

        while (this.state.saveQueue.length > 0) {
            const operation = this.state.saveQueue.shift();
            
            try {
                await this.performSave(operation);
                
                // Clear retry count on success
                this.state.retryCount.delete(operation.field);
                
            } catch (error) {
                console.error('Save operation failed:', error);
                await this.handleSaveError(operation, error);
            }
            
            // Brief pause between operations to prevent overwhelming server
            await this.sleep(100);
        }

        this.state.isProcessingQueue = false;
        this.emitEvent('queueProcessed');
    }

    /**
     * Perform individual save operation
     */
    async performSave(operation) {
        this.updateFieldState(operation.field, {
            status: 'saving',
            lastAttempt: Date.now()
        });

        this.emitEvent('saveStarted', operation);

        try {
            const response = await this.makeRequest('/auto-save', {
                method: 'POST',
                body: JSON.stringify({
                    field: operation.field,
                    value: operation.value,
                    version: this.state.currentVersion,
                    session_id: this.state.sessionId,
                    timestamp: operation.timestamp
                })
            });

            const result = await response.json();

            if (result.success) {
                await this.handleSaveSuccess(operation, result);
            } else if (result.conflict_detected) {
                await this.handleConflict(operation, result);
            } else {
                throw new Error(result.error || 'Save failed');
            }

        } catch (error) {
            if (error.name === 'NetworkError' || !navigator.onLine) {
                await this.handleOfflineSave(operation);
            } else {
                throw error;
            }
        }
    }

    /**
     * Handle successful save
     */
    async handleSaveSuccess(operation, result) {
        // Update version
        this.state.currentVersion = result.version;
        this.state.lastSync = Date.now();

        // Update field state
        this.updateFieldState(operation.field, {
            status: 'saved',
            lastSaved: Date.now(),
            isDirty: false,
            version: result.version
        });

        // Clear pending operation
        this.state.pendingOperations.delete(operation.field);

        // Store in localStorage for offline access
        this.storeOfflineData();

        this.emitEvent('saveSuccess', {
            operation,
            result,
            saveTime: result.save_time_ms
        });

        // Update analysis status if provided
        if (result.analysis_status) {
            this.emitEvent('analysisUpdate', result.analysis_status);
        }
    }

    /**
     * Handle save conflict
     */
    async handleConflict(operation, conflictData) {
        console.warn('Conflict detected:', conflictData);

        // Store conflict data
        this.state.conflicts.push({
            operation,
            conflictData,
            timestamp: Date.now()
        });

        // Update field state
        this.updateFieldState(operation.field, {
            status: 'conflict',
            conflict: conflictData,
            lastConflict: Date.now()
        });

        this.emitEvent('conflictDetected', {
            operation,
            conflictData,
            totalConflicts: this.state.conflicts.length
        });

        // Show conflict resolution UI
        this.showConflictResolution(conflictData);
    }

    /**
     * Handle save error with retry logic
     */
    async handleSaveError(operation, error) {
        const retryCount = this.state.retryCount.get(operation.field) || 0;
        
        this.updateFieldState(operation.field, {
            status: 'error',
            error: error.message,
            lastError: Date.now(),
            retryCount: retryCount
        });

        if (retryCount < this.options.maxRetries && this.isRetryableError(error)) {
            // Increment retry count
            this.state.retryCount.set(operation.field, retryCount + 1);
            
            // Calculate exponential backoff delay
            const delay = this.options.retryDelay * Math.pow(2, retryCount);
            
            console.log(`Retrying save for ${operation.field} in ${delay}ms (attempt ${retryCount + 1})`);
            
            // Schedule retry
            setTimeout(() => {
                this.state.saveQueue.unshift(operation);
                if (!this.state.isProcessingQueue) {
                    this.processSaveQueue();
                }
            }, delay);

            this.emitEvent('saveRetry', {
                operation,
                retryCount: retryCount + 1,
                delay
            });

        } else {
            // Max retries exceeded or non-retryable error
            console.error(`Save failed permanently for ${operation.field}:`, error);
            
            this.emitEvent('saveFailed', {
                operation,
                error,
                maxRetriesExceeded: retryCount >= this.options.maxRetries
            });

            // Store in offline data for manual recovery
            this.storeFailedOperation(operation, error);
        }
    }

    /**
     * Handle offline save
     */
    async handleOfflineSave(operation) {
        console.log('Saving offline:', operation.field);
        
        this.updateFieldState(operation.field, {
            status: 'offline',
            offlineValue: operation.value,
            lastOffline: Date.now()
        });

        // Store in localStorage
        const offlineData = this.getOfflineData();
        offlineData.pendingSaves[operation.field] = operation;
        this.setOfflineData(offlineData);

        this.emitEvent('offlineSave', operation);
    }

    /**
     * Sync with server to get current state
     */
    async syncWithServer() {
        try {
            const response = await this.makeRequest('/status', {
                method: 'GET',
                params: { session_id: this.state.sessionId }
            });

            const data = await response.json();
            
            if (data.auto_save_state) {
                this.state.currentVersion = data.auto_save_state.version;
                this.state.lastSync = Date.now();
            }

            this.emitEvent('serverSync', data);

        } catch (error) {
            console.error('Server sync failed:', error);
            this.emitEvent('syncError', error);
        }
    }

    /**
     * Handle connection recovery
     */
    async handleConnectionRecovery() {
        if (!this.state.isOnline) return;

        console.log('Handling connection recovery...');

        try {
            // Get client state for recovery
            const clientState = {
                version: this.state.currentVersion,
                last_sync: this.state.lastSync,
                pending_changes: this.getPendingChanges()
            };

            const response = await this.makeRequest('/connection-recovery', {
                method: 'POST',
                body: JSON.stringify({
                    session_id: this.state.sessionId,
                    client_state: clientState
                })
            });

            const result = await response.json();

            if (result.recovery_needed) {
                await this.handleRecoveryRequired(result);
            } else {
                // Process offline saves
                await this.processOfflineSaves();
            }

            this.emitEvent('connectionRecovered', result);

        } catch (error) {
            console.error('Connection recovery failed:', error);
            this.emitEvent('recoveryError', error);
        }
    }

    /**
     * Process offline saves when coming back online
     */
    async processOfflineSaves() {
        const offlineData = this.getOfflineData();
        const pendingSaves = Object.values(offlineData.pendingSaves || {});

        if (pendingSaves.length === 0) return;

        console.log('Processing offline saves:', pendingSaves.length);

        for (const operation of pendingSaves) {
            this.enqueueSave(operation);
        }

        // Clear offline saves
        offlineData.pendingSaves = {};
        this.setOfflineData(offlineData);

        this.emitEvent('offlineSavesProcessed', { count: pendingSaves.length });
    }

    /**
     * Show conflict resolution UI
     */
    async showConflictResolution(conflictData) {
        // Get detailed conflict information
        try {
            const response = await this.makeRequest('/conflicts', {
                method: 'GET',
                params: { session_id: this.state.sessionId }
            });

            const conflicts = await response.json();
            
            if (conflicts.has_conflicts) {
                this.emitEvent('showConflictUI', conflicts);
            }

        } catch (error) {
            console.error('Failed to get conflict details:', error);
        }
    }

    /**
     * Resolve conflicts with user choice
     */
    async resolveConflicts(resolutions) {
        try {
            const response = await this.makeRequest('/resolve-conflicts', {
                method: 'POST',
                body: JSON.stringify({
                    session_id: this.state.sessionId,
                    resolutions: resolutions
                })
            });

            const result = await response.json();

            if (result.success) {
                // Update version and clear conflicts
                this.state.currentVersion = result.new_version;
                this.state.conflicts = [];
                
                // Update field states
                for (const field of result.resolved_fields) {
                    this.updateFieldState(field, {
                        status: 'resolved',
                        conflict: null,
                        lastResolved: Date.now()
                    });
                }

                this.emitEvent('conflictsResolved', result);
            }

            return result;

        } catch (error) {
            console.error('Failed to resolve conflicts:', error);
            this.emitEvent('conflictResolutionError', error);
            throw error;
        }
    }

    /**
     * Auto-resolve conflicts where possible
     */
    async autoResolveConflicts(strategy = 'smart_merge') {
        try {
            const response = await this.makeRequest('/auto-resolve-conflicts', {
                method: 'POST',
                body: JSON.stringify({
                    session_id: this.state.sessionId,
                    strategy: strategy
                })
            });

            const result = await response.json();
            
            this.emitEvent('autoResolutionAttempted', result);
            
            return result;

        } catch (error) {
            console.error('Auto-resolve failed:', error);
            throw error;
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Form field listeners are set up externally
        // This method can be used for internal event setup
        
        // Listen for beforeunload to handle unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges()) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Listen for visibility change to handle tab switching
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.handleTabHidden();
            } else {
                this.handleTabVisible();
            }
        });
    }

    /**
     * Setup network detection
     */
    setupNetworkDetection() {
        window.addEventListener('online', () => {
            console.log('Connection restored');
            this.state.isOnline = true;
            this.emitEvent('connectionRestored');
            this.handleConnectionRecovery();
        });

        window.addEventListener('offline', () => {
            console.log('Connection lost');
            this.state.isOnline = false;
            this.emitEvent('connectionLost');
        });
    }

    /**
     * Setup heartbeat for connection monitoring
     */
    setupHeartbeat() {
        setInterval(async () => {
            if (this.state.isOnline) {
                try {
                    await this.syncWithServer();
                } catch (error) {
                    // Connection might be lost
                    if (!navigator.onLine) {
                        this.state.isOnline = false;
                        this.emitEvent('connectionLost');
                    }
                }
            }
        }, this.options.heartbeatInterval);
    }

    /**
     * Start conflict monitoring
     */
    startConflictMonitoring() {
        setInterval(async () => {
            if (this.state.isOnline && this.hasActiveEditing()) {
                await this.checkForConflicts();
            }
        }, this.options.conflictCheckInterval);
    }

    /**
     * Check for conflicts
     */
    async checkForConflicts() {
        try {
            const response = await this.makeRequest('/conflicts', {
                method: 'GET',
                params: { session_id: this.state.sessionId }
            });

            const conflicts = await response.json();
            
            if (conflicts.has_conflicts && conflicts.conflict_count > this.state.conflicts.length) {
                this.emitEvent('newConflictsDetected', conflicts);
            }

        } catch (error) {
            // Silently handle conflict check errors
            console.debug('Conflict check failed:', error);
        }
    }

    /**
     * Utility methods
     */
    
    updateFieldState(field, updates) {
        const currentState = this.state.fieldStates.get(field) || {};
        const newState = { ...currentState, ...updates };
        this.state.fieldStates.set(field, newState);
        
        this.emitEvent('fieldStateChanged', { field, state: newState });
    }

    getFieldState(field) {
        return this.state.fieldStates.get(field) || {};
    }

    hasUnsavedChanges() {
        for (const [field, state] of this.state.fieldStates) {
            if (state.isDirty || state.status === 'pending' || state.status === 'saving') {
                return true;
            }
        }
        return this.state.saveQueue.length > 0;
    }

    hasActiveEditing() {
        return this.state.fieldStates.size > 0 || this.state.saveQueue.length > 0;
    }

    getPendingChanges() {
        const pending = {};
        for (const [field, state] of this.state.fieldStates) {
            if (state.isDirty) {
                pending[field] = state.value;
            }
        }
        return pending;
    }

    isRetryableError(error) {
        return error.name === 'NetworkError' || 
               error.message.includes('timeout') ||
               error.message.includes('connection');
    }

    generateSessionId() {
        return 'autosave_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    async makeRequest(endpoint, options = {}) {
        const url = `/racks/${this.rackId}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            },
            ...options
        };

        if (options.params) {
            const searchParams = new URLSearchParams(options.params);
            const separator = url.includes('?') ? '&' : '?';
            return fetch(url + separator + searchParams.toString(), config);
        }

        return fetch(url, config);
    }

    emitEvent(type, data = null) {
        this.events.dispatchEvent(new CustomEvent(type, { detail: data }));
    }

    on(eventType, handler) {
        this.events.addEventListener(eventType, handler);
    }

    off(eventType, handler) {
        this.events.removeEventListener(eventType, handler);
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Offline data management
    getOfflineData() {
        try {
            return JSON.parse(localStorage.getItem(`autosave_${this.rackId}`)) || {
                pendingSaves: {},
                failedOperations: [],
                lastSync: null
            };
        } catch (e) {
            return { pendingSaves: {}, failedOperations: [], lastSync: null };
        }
    }

    setOfflineData(data) {
        try {
            localStorage.setItem(`autosave_${this.rackId}`, JSON.stringify(data));
        } catch (e) {
            console.error('Failed to store offline data:', e);
        }
    }

    storeOfflineData() {
        const offlineData = this.getOfflineData();
        offlineData.lastSync = this.state.lastSync;
        this.setOfflineData(offlineData);
    }

    loadOfflineData() {
        const offlineData = this.getOfflineData();
        this.state.lastSync = offlineData.lastSync;
    }

    storeFailedOperation(operation, error) {
        const offlineData = this.getOfflineData();
        offlineData.failedOperations.push({
            operation,
            error: error.message,
            timestamp: Date.now()
        });
        this.setOfflineData(offlineData);
    }

    // Tab visibility handling
    handleTabHidden() {
        // Process any pending saves immediately when tab becomes hidden
        if (this.state.saveQueue.length > 0) {
            this.processSaveQueue();
        }
    }

    handleTabVisible() {
        // Sync with server when tab becomes visible again
        this.syncWithServer();
    }

    handleRecoveryRequired(recoveryData) {
        // Implementation for handling recovery scenarios
        console.log('Recovery required:', recoveryData);
        this.emitEvent('recoveryRequired', recoveryData);
    }

    // Public API methods
    
    /**
     * Get current save status for UI updates
     */
    getSaveStatus() {
        const fieldStatuses = {};
        for (const [field, state] of this.state.fieldStates) {
            fieldStatuses[field] = {
                status: state.status || 'idle',
                isDirty: state.isDirty || false,
                lastSaved: state.lastSaved || null,
                error: state.error || null
            };
        }

        return {
            isOnline: this.state.isOnline,
            hasUnsavedChanges: this.hasUnsavedChanges(),
            queueSize: this.state.saveQueue.length,
            conflictCount: this.state.conflicts.length,
            version: this.state.currentVersion,
            sessionId: this.state.sessionId,
            fieldStatuses
        };
    }

    /**
     * Force save all dirty fields
     */
    async saveAll() {
        const dirtyFields = [];
        
        for (const [field, state] of this.state.fieldStates) {
            if (state.isDirty) {
                dirtyFields.push({
                    field,
                    value: state.value
                });
            }
        }

        if (dirtyFields.length === 0) return { success: true, message: 'No changes to save' };

        // Clear debounce timers and enqueue all saves
        for (const fieldData of dirtyFields) {
            if (this.state.pendingOperations.has(fieldData.field)) {
                clearTimeout(this.state.pendingOperations.get(fieldData.field).timeoutId);
                this.state.pendingOperations.delete(fieldData.field);
            }

            this.enqueueSave({
                operationId: `${fieldData.field}_force_${Date.now()}`,
                field: fieldData.field,
                value: fieldData.value,
                timestamp: Date.now(),
                priority: 'high'
            });
        }

        return { success: true, message: `Saving ${dirtyFields.length} fields` };
    }

    /**
     * Clear all pending operations and reset state
     */
    reset() {
        // Clear timeouts
        for (const [field, operation] of this.state.pendingOperations) {
            clearTimeout(operation.timeoutId);
        }

        // Reset state
        this.state.pendingOperations.clear();
        this.state.saveQueue = [];
        this.state.conflicts = [];
        this.state.retryCount.clear();
        this.state.fieldStates.clear();
        this.state.isProcessingQueue = false;

        this.emitEvent('reset');
    }

    /**
     * Destroy manager and cleanup
     */
    destroy() {
        this.reset();
        
        // Remove event listeners would go here in a full implementation
        
        this.emitEvent('destroyed');
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AutoSaveManager;
}

// Global export
window.AutoSaveManager = AutoSaveManager;