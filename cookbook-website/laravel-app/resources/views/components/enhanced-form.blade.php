@props([
    'action' => '',
    'method' => 'POST',
    'enctype' => null,
    'autoSave' => false,
    'autoSaveInterval' => 5000,
    'versionTracking' => false,
    'conflictResolution' => false,
    'showProgress' => false,
    'loadingMessage' => 'Saving...',
    'successMessage' => 'Saved successfully',
    'errorMessage' => 'An error occurred',
    'formId' => null
])

@php
    $formId = $formId ?: 'form-' . uniqid();
    $hasFile = $enctype === 'multipart/form-data';
@endphp

<div 
    x-data="enhancedForm({
        formId: '{{ $formId }}',
        autoSave: {{ $autoSave ? 'true' : 'false' }},
        autoSaveInterval: {{ $autoSaveInterval }},
        versionTracking: {{ $versionTracking ? 'true' : 'false' }},
        conflictResolution: {{ $conflictResolution ? 'true' : 'false' }},
        showProgress: {{ $showProgress ? 'true' : 'false' }},
        hasFile: {{ $hasFile ? 'true' : 'false' }},
        loadingMessage: '{{ $loadingMessage }}',
        successMessage: '{{ $successMessage }}',
        errorMessage: '{{ $errorMessage }}'
    })"
    x-init="initialize()"
    class="enhanced-form-container"
>
    <!-- Loading Overlay -->
    <div 
        x-show="isLoading" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="form-loading-overlay"
    >
        <x-loading-state 
            :loadingState="[
                'isLoading' => true,
                'message' => 'loadingMessage',
                'type' => 'spinner',
                'progress' => 0
            ]"
            size="medium"
            overlay="true"
        />
    </div>

    <!-- Conflict Resolution Modal -->
    <div 
        x-show="hasConflicts" 
        x-transition
        class="conflict-modal-overlay"
        style="display: none;"
    >
        <div class="conflict-modal">
            <div class="conflict-modal-header">
                <h3>Conflicting Changes Detected</h3>
                <p>Another user has made changes to this content while you were editing.</p>
            </div>
            
            <div class="conflict-modal-body">
                <template x-for="conflict in conflicts" :key="conflict.field">
                    <div class="conflict-item">
                        <h4 x-text="conflict.field_label"></h4>
                        
                        <div class="conflict-options">
                            <div class="conflict-option">
                                <label>
                                    <input 
                                        type="radio" 
                                        :name="`conflict_${conflict.field}`"
                                        value="keep_yours"
                                        x-model="conflict.resolution"
                                    >
                                    Keep your changes
                                </label>
                                <div class="conflict-preview" x-text="conflict.your_value"></div>
                            </div>
                            
                            <div class="conflict-option">
                                <label>
                                    <input 
                                        type="radio" 
                                        :name="`conflict_${conflict.field}`"
                                        value="keep_server"
                                        x-model="conflict.resolution"
                                    >
                                    Keep server changes
                                </label>
                                <div class="conflict-preview" x-text="conflict.server_value"></div>
                            </div>
                            
                            <div class="conflict-option">
                                <label>
                                    <input 
                                        type="radio" 
                                        :name="`conflict_${conflict.field}`"
                                        value="merge"
                                        x-model="conflict.resolution"
                                    >
                                    Merge both (manual edit required)
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <div class="conflict-modal-footer">
                <button type="button" @click="resolveConflicts()" class="btn btn-primary">
                    Resolve Conflicts
                </button>
                <button type="button" @click="cancelConflictResolution()" class="btn btn-secondary">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Auto-save Status -->
    <div 
        x-show="autoSave && (autoSaveStatus || hasError)" 
        x-transition
        class="autosave-status"
        :class="{
            'autosave-saving': autoSaveStatus === 'saving',
            'autosave-saved': autoSaveStatus === 'saved',
            'autosave-error': hasError
        }"
    >
        <span x-show="autoSaveStatus === 'saving'">
            <svg class="animate-spin w-4 h-4 inline mr-1" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.25"/>
                <path fill="currentColor" opacity="0.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            Saving...
        </span>
        <span x-show="autoSaveStatus === 'saved'">
            <svg class="w-4 h-4 inline mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            Saved <span x-text="lastSavedTime"></span>
        </span>
        <span x-show="hasError">
            <svg class="w-4 h-4 inline mr-1 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span x-text="errorMessage"></span>
            <button type="button" @click="retryAutoSave()" class="ml-2 text-blue-600 hover:text-blue-800">
                Retry
            </button>
        </span>
    </div>

    <!-- The actual form -->
    <form
        id="{{ $formId }}"
        action="{{ $action }}"
        method="{{ $method }}"
        @if($enctype) enctype="{{ $enctype }}" @endif
        @submit.prevent="submitForm"
        x-ref="form"
        class="enhanced-form"
        {{ $attributes->except(['action', 'method', 'enctype']) }}
    >
        @if($method !== 'GET')
            @csrf
        @endif

        @if($method !== 'GET' && $method !== 'POST')
            @method($method)
        @endif

        <!-- Version tracking field -->
        <input 
            x-show="versionTracking" 
            type="hidden" 
            name="version" 
            x-model="currentVersion"
            style="display: none;"
        >

        <!-- Session ID for conflict resolution -->
        <input 
            x-show="conflictResolution" 
            type="hidden" 
            name="session_id" 
            x-model="sessionId"
            style="display: none;"
        >

        <!-- Form content slot -->
        {{ $slot }}

        <!-- Progress bar (if enabled) -->
        <div x-show="showProgress && uploadProgress > 0" class="form-progress">
            <div class="progress-bar">
                <div 
                    class="progress-fill" 
                    :style="`width: ${uploadProgress}%`"
                ></div>
            </div>
            <div class="progress-text">
                <span x-text="`${uploadProgress}%`"></span>
                <span x-text="progressMessage"></span>
            </div>
        </div>
    </form>

    <!-- Error Display -->
    <div x-show="hasError && !isLoading" x-transition>
        <x-error-state 
            :loadingState="[
                'error' => 'errorMessage',
                'errorCode' => 'errorCode',
                'canRetry' => true
            ]"
            size="medium"
        />
    </div>
</div>

@push('scripts')
<script>
function enhancedForm(config) {
    return {
        // Configuration
        formId: config.formId,
        autoSave: config.autoSave,
        autoSaveInterval: config.autoSaveInterval,
        versionTracking: config.versionTracking,
        conflictResolution: config.conflictResolution,
        showProgress: config.showProgress,
        hasFile: config.hasFile,
        
        // State
        isLoading: false,
        hasError: false,
        hasConflicts: false,
        autoSaveStatus: null, // 'saving', 'saved', null
        currentVersion: 1,
        sessionId: this.generateSessionId(),
        lastSavedTime: null,
        conflicts: [],
        originalFormData: {},
        
        // Messages
        loadingMessage: config.loadingMessage,
        successMessage: config.successMessage,
        errorMessage: config.errorMessage,
        errorCode: null,
        
        // Upload progress
        uploadProgress: 0,
        progressMessage: '',
        
        // Timers
        autoSaveTimer: null,
        retryTimer: null,
        
        initialize() {
            this.captureOriginalFormData();
            
            if (this.autoSave) {
                this.setupAutoSave();
            }
            
            if (this.versionTracking) {
                this.setupVersionTracking();
            }
            
            this.setupFormChangeDetection();
            this.setupConnectionRecovery();
        },
        
        generateSessionId() {
            return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        },
        
        captureOriginalFormData() {
            const formData = new FormData(this.$refs.form);
            this.originalFormData = Object.fromEntries(formData.entries());
        },
        
        setupAutoSave() {
            this.autoSaveTimer = setInterval(() => {
                if (this.hasFormChanged() && !this.isLoading) {
                    this.performAutoSave();
                }
            }, this.autoSaveInterval);
        },
        
        setupVersionTracking() {
            // Fetch current version from server
            this.fetchCurrentVersion();
        },
        
        setupFormChangeDetection() {
            // Watch for form changes
            this.$refs.form.addEventListener('input', (e) => {
                if (this.autoSave && e.target.type !== 'file') {
                    this.debounceAutoSave();
                }
            });
            
            this.$refs.form.addEventListener('change', (e) => {
                if (this.autoSave) {
                    this.debounceAutoSave();
                }
            });
        },
        
        setupConnectionRecovery() {
            window.addEventListener('online', () => {
                if (this.hasError && this.autoSave) {
                    this.retryAutoSave();
                }
            });
            
            document.addEventListener('connectionRecovered', () => {
                this.handleConnectionRecovery();
            });
        },
        
        hasFormChanged() {
            const currentData = new FormData(this.$refs.form);
            const current = Object.fromEntries(currentData.entries());
            
            return JSON.stringify(current) !== JSON.stringify(this.originalFormData);
        },
        
        debounceAutoSave() {
            if (this.autoSaveTimer) {
                clearTimeout(this.autoSaveTimer);
            }
            
            this.autoSaveTimer = setTimeout(() => {
                if (!this.isLoading) {
                    this.performAutoSave();
                }
            }, 2000); // 2 second debounce
        },
        
        async performAutoSave() {
            if (!this.autoSave || this.isLoading) return;
            
            this.autoSaveStatus = 'saving';
            this.hasError = false;
            
            try {
                const formData = new FormData(this.$refs.form);
                
                const response = await fetch(this.$refs.form.action + '/auto-save', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content')
                    }
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error?.message || 'Auto-save failed');
                }
                
                // Handle conflicts
                if (result.data?.has_conflicts) {
                    this.handleConflicts(result.data.conflicts);
                    return;
                }
                
                // Success
                this.autoSaveStatus = 'saved';
                this.lastSavedTime = 'just now';
                this.currentVersion = result.data?.version || this.currentVersion;
                this.captureOriginalFormData(); // Update baseline
                
                // Clear saved status after 3 seconds
                setTimeout(() => {
                    if (this.autoSaveStatus === 'saved') {
                        this.autoSaveStatus = null;
                    }
                }, 3000);
                
            } catch (error) {
                this.handleAutoSaveError(error);
            }
        },
        
        handleAutoSaveError(error) {
            this.hasError = true;
            this.autoSaveStatus = null;
            this.errorMessage = error.message || 'Auto-save failed';
            
            // Extract error code if available
            if (error.response?.data?.error?.code) {
                this.errorCode = error.response.data.error.code;
            }
            
            console.error('Auto-save error:', error);
        },
        
        async retryAutoSave() {
            await this.performAutoSave();
        },
        
        handleConflicts(conflicts) {
            this.hasConflicts = true;
            this.conflicts = conflicts.map(conflict => ({
                ...conflict,
                resolution: 'keep_yours' // default resolution
            }));\n        },\n        \n        async resolveConflicts() {\n            try {\n                const resolutions = {};\n                this.conflicts.forEach(conflict => {\n                    resolutions[conflict.field] = conflict.resolution;\n                });\n                \n                const response = await fetch(this.$refs.form.action + '/resolve-conflicts', {\n                    method: 'POST',\n                    headers: {\n                        'Content-Type': 'application/json',\n                        'X-Requested-With': 'XMLHttpRequest',\n                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content')\n                    },\n                    body: JSON.stringify({\n                        session_id: this.sessionId,\n                        resolutions: resolutions\n                    })\n                });\n                \n                const result = await response.json();\n                \n                if (result.success) {\n                    this.hasConflicts = false;\n                    this.conflicts = [];\n                    this.currentVersion = result.data?.version || this.currentVersion;\n                    \n                    // Refresh form with resolved data\n                    if (result.data?.resolved_data) {\n                        this.applyResolvedData(result.data.resolved_data);\n                    }\n                } else {\n                    throw new Error(result.error?.message || 'Conflict resolution failed');\n                }\n                \n            } catch (error) {\n                this.errorMessage = error.message;\n                this.hasError = true;\n            }\n        },\n        \n        cancelConflictResolution() {\n            this.hasConflicts = false;\n            this.conflicts = [];\n            // Optionally reload the form or revert changes\n        },\n        \n        applyResolvedData(resolvedData) {\n            // Apply resolved data to form fields\n            Object.entries(resolvedData).forEach(([field, value]) => {\n                const input = this.$refs.form.querySelector(`[name=\"${field}\"]`);\n                if (input) {\n                    input.value = value;\n                }\n            });\n            \n            this.captureOriginalFormData();\n        },\n        \n        async fetchCurrentVersion() {\n            try {\n                const response = await fetch(this.$refs.form.action + '/version', {\n                    headers: {\n                        'X-Requested-With': 'XMLHttpRequest'\n                    }\n                });\n                \n                if (response.ok) {\n                    const data = await response.json();\n                    this.currentVersion = data.version || 1;\n                }\n            } catch (error) {\n                console.warn('Failed to fetch version:', error);\n            }\n        },\n        \n        async submitForm(event) {\n            if (this.isLoading) return;\n            \n            this.isLoading = true;\n            this.hasError = false;\n            this.uploadProgress = 0;\n            \n            try {\n                const formData = new FormData(this.$refs.form);\n                \n                // Setup XMLHttpRequest for progress tracking\n                const xhr = new XMLHttpRequest();\n                \n                // Track upload progress\n                if (this.hasFile && this.showProgress) {\n                    xhr.upload.onprogress = (e) => {\n                        if (e.lengthComputable) {\n                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);\n                            this.progressMessage = `Uploading... (${this.uploadProgress}%)`;\n                        }\n                    };\n                }\n                \n                // Handle response\n                const response = await new Promise((resolve, reject) => {\n                    xhr.onload = () => {\n                        if (xhr.status >= 200 && xhr.status < 300) {\n                            resolve({\n                                ok: true,\n                                status: xhr.status,\n                                json: () => Promise.resolve(JSON.parse(xhr.responseText))\n                            });\n                        } else {\n                            reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));\n                        }\n                    };\n                    \n                    xhr.onerror = () => reject(new Error('Network error'));\n                    \n                    xhr.open(this.$refs.form.method, this.$refs.form.action);\n                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');\n                    xhr.send(formData);\n                });\n                \n                const result = await response.json();\n                \n                if (result.success) {\n                    // Handle success\n                    this.handleSubmitSuccess(result);\n                } else {\n                    throw new Error(result.error?.message || result.message || 'Submission failed');\n                }\n                \n            } catch (error) {\n                this.handleSubmitError(error);\n            } finally {\n                this.isLoading = false;\n                this.uploadProgress = 0;\n            }\n        },\n        \n        handleSubmitSuccess(result) {\n            // Show success message\n            if (window.loadingManager) {\n                window.loadingManager.showSuccess(\n                    this.formId,\n                    result.message || this.successMessage\n                );\n            }\n            \n            // Update version if provided\n            if (result.data?.version) {\n                this.currentVersion = result.data.version;\n            }\n            \n            // Redirect if specified\n            if (result.redirect) {\n                window.location.href = result.redirect;\n                return;\n            }\n            \n            // Update form data baseline\n            this.captureOriginalFormData();\n            \n            // Emit success event\n            this.$dispatch('formSubmitSuccess', {\n                formId: this.formId,\n                result: result\n            });\n        },\n        \n        handleSubmitError(error) {\n            this.hasError = true;\n            this.errorMessage = error.message || 'An error occurred while submitting the form';\n            \n            // Extract structured error data\n            if (error.response?.data?.error) {\n                this.errorCode = error.response.data.error.code;\n                this.errorMessage = error.response.data.error.message;\n            }\n            \n            // Show error notification\n            if (window.loadingManager) {\n                window.loadingManager.showError(this.formId, {\n                    message: this.errorMessage,\n                    code: this.errorCode,\n                    is_retryable: true\n                });\n            }\n            \n            // Emit error event\n            this.$dispatch('formSubmitError', {\n                formId: this.formId,\n                error: error\n            });\n        },\n        \n        handleConnectionRecovery() {\n            if (this.hasError) {\n                // Clear error and retry if appropriate\n                this.hasError = false;\n                this.errorMessage = '';\n                \n                if (this.autoSave && this.hasFormChanged()) {\n                    this.performAutoSave();\n                }\n            }\n        },\n        \n        destroy() {\n            if (this.autoSaveTimer) {\n                clearTimeout(this.autoSaveTimer);\n            }\n            if (this.retryTimer) {\n                clearTimeout(this.retryTimer);\n            }\n        }\n    }\n}\n</script>\n@endpush\n\n@push('styles')\n<style>\n.enhanced-form-container {\n    position: relative;\n}\n\n.form-loading-overlay {\n    position: absolute;\n    top: 0;\n    left: 0;\n    right: 0;\n    bottom: 0;\n    background-color: rgba(255, 255, 255, 0.9);\n    display: flex;\n    align-items: center;\n    justify-content: center;\n    z-index: 100;\n    backdrop-filter: blur(2px);\n}\n\n.conflict-modal-overlay {\n    position: fixed;\n    top: 0;\n    left: 0;\n    right: 0;\n    bottom: 0;\n    background-color: rgba(0, 0, 0, 0.5);\n    display: flex;\n    align-items: center;\n    justify-content: center;\n    z-index: 1000;\n}\n\n.conflict-modal {\n    background: white;\n    border-radius: 8px;\n    max-width: 600px;\n    max-height: 80vh;\n    overflow-y: auto;\n    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);\n}\n\n.conflict-modal-header {\n    padding: 1.5rem;\n    border-bottom: 1px solid #e5e7eb;\n}\n\n.conflict-modal-header h3 {\n    margin: 0 0 0.5rem 0;\n    font-size: 1.25rem;\n    font-weight: 600;\n}\n\n.conflict-modal-body {\n    padding: 1.5rem;\n    max-height: 400px;\n    overflow-y: auto;\n}\n\n.conflict-item {\n    margin-bottom: 2rem;\n}\n\n.conflict-item h4 {\n    margin: 0 0 1rem 0;\n    font-size: 1rem;\n    font-weight: 600;\n    text-transform: capitalize;\n}\n\n.conflict-options {\n    display: flex;\n    flex-direction: column;\n    gap: 1rem;\n}\n\n.conflict-option {\n    border: 1px solid #e5e7eb;\n    border-radius: 6px;\n    padding: 1rem;\n}\n\n.conflict-option label {\n    display: flex;\n    align-items: center;\n    font-weight: 500;\n    margin-bottom: 0.5rem;\n}\n\n.conflict-option input[type=\"radio\"] {\n    margin-right: 0.5rem;\n}\n\n.conflict-preview {\n    background-color: #f9fafb;\n    border: 1px solid #e5e7eb;\n    border-radius: 4px;\n    padding: 0.75rem;\n    font-family: monospace;\n    font-size: 0.875rem;\n    white-space: pre-wrap;\n    word-break: break-word;\n    max-height: 100px;\n    overflow-y: auto;\n}\n\n.conflict-modal-footer {\n    padding: 1.5rem;\n    border-top: 1px solid #e5e7eb;\n    display: flex;\n    gap: 1rem;\n    justify-content: flex-end;\n}\n\n.autosave-status {\n    position: fixed;\n    bottom: 20px;\n    right: 20px;\n    background: white;\n    border: 1px solid #e5e7eb;\n    border-radius: 6px;\n    padding: 0.75rem 1rem;\n    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);\n    z-index: 50;\n    font-size: 0.875rem;\n}\n\n.autosave-saving {\n    border-color: #3b82f6;\n    color: #3b82f6;\n}\n\n.autosave-saved {\n    border-color: #10b981;\n    color: #10b981;\n}\n\n.autosave-error {\n    border-color: #ef4444;\n    color: #ef4444;\n}\n\n.form-progress {\n    margin-top: 1rem;\n}\n\n.progress-bar {\n    width: 100%;\n    height: 8px;\n    background-color: #e5e7eb;\n    border-radius: 4px;\n    overflow: hidden;\n}\n\n.progress-fill {\n    height: 100%;\n    background: linear-gradient(90deg, #3b82f6, #1d4ed8);\n    transition: width 0.3s ease;\n}\n\n.progress-text {\n    display: flex;\n    justify-content: space-between;\n    margin-top: 0.5rem;\n    font-size: 0.875rem;\n    color: #6b7280;\n}\n</style>\n@endpush"