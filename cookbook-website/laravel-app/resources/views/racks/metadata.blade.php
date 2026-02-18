<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Rack Metadata - {{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK491B502K"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZK491B502K');
    </script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Auto-Save Manager -->
    {{-- Auto-save functionality temporarily disabled --}}
    
    <!-- Markdown Editor -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    {{-- Markdown editor functionality temporarily disabled --}}
</head>
<body class="font-sans antialiased" style="background-color: #C3C3C3;">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Progress Indicator -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <!-- Step 1: Upload (Complete) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        ✓
                    </div>
                    <span class="ml-2 text-black font-semibold">Upload</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-vibrant-green"></div>
                
                <!-- Step 2: Metadata (Active) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-yellow flex items-center justify-center text-black font-bold">
                        2
                    </div>
                    <span class="ml-2 text-black font-semibold">Metadata</span>
                </div>
                
                <!-- Connector -->
                <div id="annotation-connector" class="w-16 h-1 bg-gray-300 transition-colors duration-300"></div>
                
                <!-- Step 3: Annotate -->
                <div class="flex items-center">
                    <div id="annotation-step" class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold transition-colors duration-300">
                        3
                    </div>
                    <span id="annotation-label" class="ml-2 text-gray-600 transition-colors duration-300">Annotate</span>
                </div>
                
                <!-- Connector -->
                <div id="publish-connector" class="w-16 h-1 bg-gray-300 transition-colors duration-300"></div>
                
                <!-- Step 4: Publish -->
                <div class="flex items-center">
                    <div id="publish-step" class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold transition-colors duration-300">
                        4
                    </div>
                    <span id="publish-label" class="ml-2 text-gray-600 transition-colors duration-300">Publish</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis Status Banner -->
    <div id="analysis-banner" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
        <div id="analysis-processing" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4" style="display: {{ $rack->status === 'processing' ? 'block' : 'none' }}">
            <div class="flex items-center">
                <svg class="animate-spin h-5 w-5 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <p class="text-blue-800 font-medium">Analyzing your rack in the background...</p>
                    <p class="text-blue-700 text-sm">You can continue entering metadata while we analyze your file.</p>
                </div>
            </div>
        </div>

        <div id="analysis-complete" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4" style="display: {{ in_array($rack->status, ['pending', 'approved']) ? 'block' : 'none' }}">
            <div class="flex items-center">
                <div class="w-5 h-5 rounded-full bg-green-500 flex items-center justify-center mr-3">
                    <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-green-800 font-medium">Analysis complete!</p>
                    <p class="text-green-700 text-sm" id="analysis-results">Ready to proceed to annotation.</p>
                </div>
            </div>
        </div>

        <div id="analysis-error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4" style="display: {{ $rack->status === 'failed' ? 'block' : 'none' }}">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <p class="text-red-800 font-medium">Analysis failed</p>
                    <p class="text-red-700 text-sm" id="analysis-error-message">{{ $rack->processing_error ?? 'An error occurred during analysis.' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="card card-body">
            <h1 class="text-3xl font-bold mb-2 text-black">Rack Metadata</h1>
            <p class="text-gray-600 mb-8">Tell us about your rack while we analyze it in the background.</p>

            <!-- Success Messages -->
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <ul class="text-red-800 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="metadata-form" method="POST" action="{{ route('racks.metadata.store', $rack) }}">
                @csrf

                <!-- File Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-black mb-2">File Information</h3>
                    <div class="text-sm text-gray-600">
                        <p><strong>Original filename:</strong> {{ $rack->original_filename }}</p>
                        <p><strong>File size:</strong> {{ number_format($rack->file_size / 1024, 1) }} KB</p>
                        <p id="analysis-info" class="mt-2 text-blue-600"></p>
                    </div>
                </div>

                <!-- Title -->
                <div class="mb-6">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title', $rack->title) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-vibrant-cyan focus:border-transparent"
                           required
                           maxlength="255"
                           data-autosave="title">
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-gray-500">Enter a descriptive title for your rack</span>
                        <span class="text-xs text-gray-400" id="title-saved"></span>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <textarea id="description" 
                              name="description" 
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-vibrant-cyan focus:border-transparent"
                              required
                              maxlength="1000"
                              data-autosave="description">{{ old('description', $rack->description) }}</textarea>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-gray-500">Describe what this rack does and how it sounds</span>
                        <span class="text-xs text-gray-400" id="description-saved"></span>
                    </div>
                </div>

                <!-- Category -->
                <div class="mb-6">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                    <select id="category" 
                            name="category" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-vibrant-cyan focus:border-transparent"
                            required
                            data-autosave="category">
                        <option value="">Select a category</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $rack->category) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-gray-500">Choose the most appropriate category</span>
                        <span class="text-xs text-gray-400" id="category-saved"></span>
                    </div>
                </div>

                <!-- Tags -->
                <div class="mb-6">
                    <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                    <input type="text" 
                           id="tags" 
                           name="tags" 
                           value="{{ old('tags', $rack->tags->pluck('name')->join(', ')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-vibrant-cyan focus:border-transparent"
                           maxlength="500"
                           data-autosave="tags">
                    <div class="flex justify-between mt-1">
                        <span class="text-xs text-gray-500">Comma-separated tags (e.g., "bass, deep, wobble")</span>
                        <span class="text-xs text-gray-400" id="tags-saved"></span>
                    </div>
                </div>

                <!-- How-to Article with Enhanced Markdown Editor -->
                <div class="mb-6">
                    <x-markdown-editor 
                        name="how_to_article"
                        :value="old('how_to_article', $rack->how_to_article)"
                        label="How-to Article"
                        rows="12"
                        maxlength="10000"
                        auto-save="how_to_article"
                        :upload-route="route('racks.how-to-images.upload', $rack)"
                        :preview-route="route('racks.how-to-images.preview', $rack)"
                        placeholder="Write a comprehensive tutorial explaining how to use this rack. Use Markdown formatting for rich text, add images, embed YouTube videos or SoundCloud tracks."
                        help-text="Optional tutorial/guide for using this rack. Supports <strong>Markdown</strong>, images, YouTube/SoundCloud embeds, and code syntax highlighting."
                        :show-toolbar="true"
                        :show-preview="true"
                        :show-image-upload="true"
                        data-markdown-editor
                        data-upload-route="{{ route('racks.how-to-images.upload', $rack) }}"
                        data-preview-route="{{ route('racks.how-to-images.preview', $rack) }}"
                        data-auto-save-route="{{ route('racks.auto-save', $rack) }}"
                    />
                </div>


                <!-- Visibility -->
                <div class="mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="is_public" 
                               name="is_public" 
                               value="1"
                               {{ old('is_public', $rack->is_public) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-vibrant-cyan focus:ring-vibrant-cyan">
                        <label for="is_public" class="ml-2 text-sm text-gray-700">
                            Make this rack publicly visible
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- Save Draft -->
                    <button type="submit" 
                            class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        Save Metadata
                    </button>

                    <!-- Proceed to Annotation (when analysis complete) -->
                    <button type="button" 
                            id="proceed-to-annotation" 
                            class="px-6 py-2 bg-vibrant-cyan text-black rounded-md hover:bg-cyan-500 focus:outline-none focus:ring-2 focus:ring-vibrant-cyan focus:ring-offset-2 transition-colors opacity-50 cursor-not-allowed"
                            disabled>
                        <span class="flex items-center">
                            <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Waiting for analysis...
                        </span>
                    </button>

                    <!-- Quick Publish (when analysis complete) -->
                    <button type="button" 
                            id="quick-publish" 
                            class="px-6 py-2 bg-vibrant-yellow text-black rounded-md hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-vibrant-yellow focus:ring-offset-2 transition-colors opacity-50 cursor-not-allowed"
                            disabled>
                        Skip Annotation & Publish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced JavaScript with AutoSave Manager -->
    <script>
        let analysisComplete = {{ in_array($rack->status, ['pending', 'approved']) ? 'true' : 'false' }};
        let analysisStatus = '{{ $rack->status }}';
        
        // Initialize AutoSave Manager
        let autoSaveManager;
        let conflictModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Create AutoSave Manager instance
            autoSaveManager = new AutoSaveManager({{ $rack->id }}, {
                debounceDelay: 2000,
                retryDelay: 1000,
                maxRetries: 3,
                heartbeatInterval: 30000,
                conflictCheckInterval: 10000
            });
            
            // Make it globally available for markdown editor
            window.autoSaveManager = autoSaveManager;
            
            // Setup field listeners
            setupFieldListeners();
            
            // Setup event listeners for AutoSave Manager
            setupAutoSaveEventListeners();
            
            // Create conflict resolution modal
            createConflictModal();
            
            console.log('Auto-save system initialized');
        });
        
        function setupFieldListeners() {
            const autoSaveElements = document.querySelectorAll('[data-autosave]');
            
            autoSaveElements.forEach(element => {
                const field = element.dataset.autosave;
                
                element.addEventListener('input', function() {
                    autoSaveManager.scheduleAutoSave(field, element.value);
                });
                
                // Handle paste events
                element.addEventListener('paste', function() {
                    setTimeout(() => {
                        autoSaveManager.scheduleAutoSave(field, element.value);
                    }, 10);
                });
            });
        }
        
        function setupAutoSaveEventListeners() {
            // Save status updates
            autoSaveManager.on('saveSuccess', (event) => {
                const { operation, result, saveTime } = event.detail;
                updateSaveIndicator(operation.field, 'success', `✓ Saved (${saveTime}ms)`);
                
                // Update analysis status if provided
                if (result.analysis_status) {
                    updateAnalysisStatus(result.analysis_status.status, result.analysis_status.is_complete);
                }
            });
            
            autoSaveManager.on('saveStarted', (event) => {
                const { field } = event.detail;
                updateSaveIndicator(field, 'saving', '⏳ Saving...');
            });
            
            autoSaveManager.on('saveFailed', (event) => {
                const { operation, error } = event.detail;
                updateSaveIndicator(operation.field, 'error', '✗ Error');
                console.error('Save failed:', error);
            });
            
            autoSaveManager.on('saveRetry', (event) => {
                const { operation, retryCount, delay } = event.detail;
                updateSaveIndicator(operation.field, 'retry', `🔄 Retry ${retryCount}`);
            });
            
            // Conflict handling
            autoSaveManager.on('conflictDetected', (event) => {
                const { conflictData, totalConflicts } = event.detail;
                console.warn('Conflict detected:', conflictData);
                showConflictNotification(totalConflicts);
            });
            
            autoSaveManager.on('showConflictUI', (event) => {
                const conflicts = event.detail;
                showConflictModal(conflicts);
            });
            
            // Connection status
            autoSaveManager.on('connectionLost', () => {
                showNetworkStatus('offline', 'Connection lost - working offline');
            });
            
            autoSaveManager.on('connectionRestored', () => {
                showNetworkStatus('online', 'Connection restored');
            });
            
            autoSaveManager.on('offlineSave', (event) => {
                const { field } = event.detail;
                updateSaveIndicator(field, 'offline', '📱 Saved offline');
            });
        }
        
        function updateSaveIndicator(field, status, message) {
            const indicator = document.getElementById(field + '-saved');
            if (!indicator) return;
            
            // Clear existing classes
            indicator.classList.remove('text-green-500', 'text-red-500', 'text-blue-500', 'text-yellow-500');
            
            // Set message and style based on status
            indicator.textContent = message;
            
            switch (status) {
                case 'success':
                    indicator.classList.add('text-green-500');
                    setTimeout(() => {
                        indicator.textContent = '';
                        indicator.classList.remove('text-green-500');
                    }, 3000);
                    break;
                case 'error':
                    indicator.classList.add('text-red-500');
                    setTimeout(() => {
                        indicator.textContent = '';
                        indicator.classList.remove('text-red-500');
                    }, 5000);
                    break;
                case 'saving':
                    indicator.classList.add('text-blue-500');
                    break;
                case 'retry':
                    indicator.classList.add('text-yellow-500');
                    break;
                case 'offline':
                    indicator.classList.add('text-blue-500');
                    setTimeout(() => {
                        indicator.textContent = '';
                        indicator.classList.remove('text-blue-500');
                    }, 3000);
                    break;
            }
        }
        
        function showNetworkStatus(status, message) {
            // Create or update network status indicator
            let statusEl = document.getElementById('network-status');
            if (!statusEl) {
                statusEl = document.createElement('div');
                statusEl.id = 'network-status';
                statusEl.className = 'fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 transition-all duration-300';
                document.body.appendChild(statusEl);
            }
            
            statusEl.textContent = message;
            statusEl.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 transition-all duration-300 ${
                status === 'online' ? 'bg-green-500' : 'bg-red-500'
            }`;
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                statusEl.style.opacity = '0';
                setTimeout(() => {
                    if (statusEl.parentNode) {
                        statusEl.parentNode.removeChild(statusEl);
                    }
                }, 300);
            }, 3000);
        }
        
        function showConflictNotification(conflictCount) {
            // Show a notification about conflicts
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 bg-orange-500 text-white px-6 py-3 rounded-lg z-50';
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <span>⚠️</span>
                    <span>${conflictCount} editing conflict${conflictCount > 1 ? 's' : ''} detected</span>
                    <button onclick="resolveConflicts()" class="ml-4 bg-white text-orange-500 px-3 py-1 rounded text-sm font-semibold">
                        Resolve
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 10000);
        }

        // Poll for analysis status updates
        function pollAnalysisStatus() {
            if (analysisComplete) return;

            const sessionId = autoSaveManager ? autoSaveManager.state.sessionId : null;
            const url = `/racks/{{ $rack->id }}/status${sessionId ? '?session_id=' + sessionId : ''}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    updateAnalysisStatus(data.status, data.is_complete, data.analysis_data, data.error_message);
                    
                    // Update auto-save state if provided
                    if (data.auto_save_state) {
                        console.log('Auto-save state:', data.auto_save_state);
                    }
                    
                    if (!data.is_complete && !data.has_error) {
                        setTimeout(pollAnalysisStatus, 3000); // Poll every 3 seconds
                    }
                })
                .catch(error => {
                    console.error('Status polling error:', error);
                    setTimeout(pollAnalysisStatus, 5000); // Retry in 5 seconds
                });
        }

        function updateAnalysisStatus(status, isComplete, analysisData = null, errorMessage = null) {
            analysisStatus = status;
            analysisComplete = isComplete;

            const processingBanner = document.getElementById('analysis-processing');
            const completeBanner = document.getElementById('analysis-complete');
            const errorBanner = document.getElementById('analysis-error');
            const proceedButton = document.getElementById('proceed-to-annotation');
            const publishButton = document.getElementById('quick-publish');
            const analysisResults = document.getElementById('analysis-results');
            const analysisInfo = document.getElementById('analysis-info');

            // Hide all banners first
            processingBanner.style.display = 'none';
            completeBanner.style.display = 'none';
            errorBanner.style.display = 'none';

            if (status === 'failed') {
                errorBanner.style.display = 'block';
                if (errorMessage) {
                    document.getElementById('analysis-error-message').textContent = errorMessage;
                }
            } else if (isComplete) {
                completeBanner.style.display = 'block';
                proceedButton.disabled = false;
                proceedButton.classList.remove('opacity-50', 'cursor-not-allowed');
                proceedButton.innerHTML = 'Proceed to Annotation';
                
                publishButton.disabled = false;
                publishButton.classList.remove('opacity-50', 'cursor-not-allowed');

                // Update progress indicators
                const annotationStep = document.getElementById('annotation-step');
                const annotationConnector = document.getElementById('annotation-connector');
                const annotationLabel = document.getElementById('annotation-label');
                
                annotationStep.classList.remove('bg-gray-300', 'text-gray-600');
                annotationStep.classList.add('bg-vibrant-green', 'text-black');
                annotationStep.textContent = '✓';
                
                annotationConnector.classList.remove('bg-gray-300');
                annotationConnector.classList.add('bg-vibrant-green');
                
                annotationLabel.classList.remove('text-gray-600');
                annotationLabel.classList.add('text-black');

                // Show analysis data
                if (analysisData) {
                    const results = [];
                    if (analysisData.rack_type) results.push(`Type: ${analysisData.rack_type}`);
                    if (analysisData.device_count) results.push(`${analysisData.device_count} devices`);
                    if (analysisData.chain_count) results.push(`${analysisData.chain_count} chains`);
                    if (analysisData.category) results.push(`Category: ${analysisData.category}`);
                    
                    if (results.length > 0) {
                        analysisResults.textContent = results.join(' • ');
                        analysisInfo.textContent = results.join(' • ');
                    }
                }
            } else {
                processingBanner.style.display = 'block';
            }
        }

        // Set up proceed to annotation button
        document.getElementById('proceed-to-annotation').addEventListener('click', function() {
            if (analysisComplete) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/racks/{{ $rack->id }}/proceed-to-annotation';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Set up quick publish button
        document.getElementById('quick-publish').addEventListener('click', function() {
            if (analysisComplete && confirm('Are you sure you want to publish without adding chain annotations?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/racks/{{ $rack->id }}/quick-publish';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Enhanced markdown editor and auto-save integration handled above

        // Conflict resolution functions
        async function resolveConflicts() {
            if (!autoSaveManager) return;
            
            try {
                const conflicts = await autoSaveManager.autoResolveConflicts('smart_merge');
                
                if (conflicts.manual_resolution_required > 0) {
                    // Show manual resolution UI
                    const conflictData = await fetch(`/racks/{{ $rack->id }}/conflicts?session_id=${autoSaveManager.state.sessionId}`)
                        .then(r => r.json());
                    showConflictModal(conflictData);
                } else {
                    showNetworkStatus('online', 'Conflicts resolved automatically');
                }
            } catch (error) {
                console.error('Auto-resolve failed:', error);
                showNetworkStatus('error', 'Failed to resolve conflicts');
            }
        }
        
        function createConflictModal() {
            conflictModal = document.createElement('div');
            conflictModal.id = 'conflict-modal';
            conflictModal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 hidden';
            conflictModal.innerHTML = `
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg max-w-4xl w-full max-h-90vh overflow-y-auto">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-2xl font-bold text-gray-900">Resolve Editing Conflicts</h2>
                                <button onclick="closeConflictModal()" class="text-gray-500 hover:text-gray-700">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <div id="conflict-content">
                                <!-- Conflict resolution UI will be inserted here -->
                            </div>
                            <div class="flex justify-end space-x-4 mt-6">
                                <button onclick="closeConflictModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                                    Cancel
                                </button>
                                <button onclick="applyConflictResolution()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    Apply Resolution
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(conflictModal);
        }
        
        function showConflictModal(conflicts) {
            const content = document.getElementById('conflict-content');
            if (!content) return;
            
            let html = '<div class="space-y-6">';
            
            conflicts.conflicts.forEach((conflict, index) => {
                html += `
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-lg mb-3">${conflict.field_label}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="border rounded p-3">
                                <h4 class="font-medium text-green-700 mb-2">Your Version</h4>
                                <div class="text-sm text-gray-600 mb-2">${conflict.your_version.timestamp}</div>
                                <div class="bg-green-50 p-2 rounded text-sm max-h-32 overflow-y-auto">
                                    ${conflict.your_version.preview}
                                </div>
                            </div>
                            <div class="border rounded p-3">
                                <h4 class="font-medium text-blue-700 mb-2">Server Version</h4>
                                <div class="text-sm text-gray-600 mb-2">${conflict.server_version.timestamp}</div>
                                <div class="bg-blue-50 p-2 rounded text-sm max-h-32 overflow-y-auto">
                                    ${conflict.server_version.preview}
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="font-medium">Choose resolution:</label>`;
                            
                conflict.suggestions.forEach(suggestion => {
                    html += `
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="resolution_${conflict.field}" value="${suggestion.id}" class="text-blue-600">
                            <span class="text-sm">
                                <strong>${suggestion.label}</strong> - ${suggestion.description}
                            </span>
                        </label>`;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            content.innerHTML = html;
            
            // Show modal
            conflictModal.classList.remove('hidden');
        }
        
        function closeConflictModal() {
            if (conflictModal) {
                conflictModal.classList.add('hidden');
            }
        }
        
        async function applyConflictResolution() {
            const resolutions = {};
            
            // Collect user choices
            const radioInputs = conflictModal.querySelectorAll('input[type="radio"]:checked');
            radioInputs.forEach(input => {
                const field = input.name.replace('resolution_', '');
                resolutions[field] = input.value;
            });
            
            if (Object.keys(resolutions).length === 0) {
                alert('Please select a resolution for each conflict.');
                return;
            }
            
            try {
                const result = await autoSaveManager.resolveConflicts(resolutions);
                
                if (result.success) {
                    closeConflictModal();
                    showNetworkStatus('online', 'Conflicts resolved successfully');
                } else {
                    alert('Failed to resolve conflicts: ' + result.error);
                }
            } catch (error) {
                console.error('Conflict resolution failed:', error);
                alert('Failed to resolve conflicts. Please try again.');
            }
        }
        
        // Force save all changes function
        window.saveAllChanges = async function() {
            if (autoSaveManager) {
                const result = await autoSaveManager.saveAll();
                console.log('Save all result:', result);
                return result;
            }
        };
        
        // Initialize analysis status polling if needed
        if (!analysisComplete && analysisStatus === 'processing') {
            setTimeout(pollAnalysisStatus, 3000);
        }

        // Initialize with current status
        if (analysisComplete) {
            updateAnalysisStatus(analysisStatus, true);
        }
    </script>
</body>
</html>