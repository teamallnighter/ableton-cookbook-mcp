/**
 * Enhanced Markdown Editor with Rich Media Support, XSS Prevention, and Memory Management
 * Integrates with Phase 2 auto-save functionality and provides rich editing experience
 * Implements defense-in-depth security architecture to prevent XSS attacks
 * Features comprehensive error boundaries and memory leak prevention
 */

/**
 * Error Boundary for Markdown Editor Components
 */
class MarkdownEditorErrorBoundary {
    constructor(container) {
        this.container = container;
        this.retryCount = 0;
        this.maxRetries = 3;
    }
    
    handleError(error, context = 'markdown-editor', operation = null) {
        console.error(`Markdown Editor Error in ${context}:`, error);
        
        // Show user-friendly error message
        this.showErrorMessage(error, context, operation);
        
        // Attempt recovery if possible
        if (this.retryCount < this.maxRetries) {
            setTimeout(() => {
                this.attemptRecovery(context, operation);
            }, 1000);
        }
        
        return null;
    }
    
    showErrorMessage(error, context, operation) {
        const errorContainer = this.container.querySelector('[data-error-container]') || this.createErrorContainer();
        
        const errorType = this.categorizeError(error);
        const message = this.getErrorMessage(errorType, operation);
        
        errorContainer.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded p-3 mb-4">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1">
                        <h4 class="text-sm font-medium text-red-800 mb-1">Editor Error</h4>
                        <p class="text-sm text-red-700 mb-2">${message}</p>
                        <div class="flex gap-2">
                            <button onclick="this.closest('[data-error-container]').style.display='none'" 
                                    class="text-xs px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                                Dismiss
                            </button>
                            ${this.retryCount < this.maxRetries ? `
                                <button onclick="window.markdownEditorErrorBoundary?.attemptRecovery('${context}', '${operation}')" 
                                        class="text-xs px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600">
                                    Retry (${this.maxRetries - this.retryCount} left)
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        errorContainer.style.display = 'block';
        
        // Auto-hide after 10 seconds unless it's a critical error
        if (!this.isCriticalError(error)) {
            setTimeout(() => {
                errorContainer.style.display = 'none';
            }, 10000);
        }
    }
    
    createErrorContainer() {
        const container = document.createElement('div');
        container.setAttribute('data-error-container', '');
        container.style.display = 'none';
        this.container.insertBefore(container, this.container.firstChild);
        return container;
    }
    
    categorizeError(error) {
        if (error.name === 'NetworkError' || error.message?.includes('fetch')) {
            return 'network';
        }
        if (error.name === 'SyntaxError') {
            return 'syntax';
        }
        if (error.message?.includes('permissions') || error.message?.includes('access')) {
            return 'permissions';
        }
        return 'general';
    }
    
    getErrorMessage(errorType, operation) {
        const messages = {
            network: {
                preview: 'Unable to generate preview. Please check your internet connection.',
                upload: 'Image upload failed. Please check your connection and try again.',
                save: 'Auto-save failed. Your changes might not be saved.',
                default: 'Network error occurred. Please check your connection.'
            },
            syntax: {
                default: 'There was an issue processing your content. Please check for unusual formatting.'
            },
            permissions: {
                upload: 'You don\'t have permission to upload images.',
                save: 'You don\'t have permission to save changes.',
                default: 'Permission denied. Please check your access rights.'
            },
            general: {
                default: 'An unexpected error occurred. The editor may need to be reloaded.'
            }
        };
        
        return messages[errorType]?.[operation] || messages[errorType]?.default || messages.general.default;
    }
    
    isCriticalError(error) {
        const criticalPatterns = [
            /cannot read property/i,
            /is not defined/i,
            /permission denied/i
        ];
        
        return criticalPatterns.some(pattern => pattern.test(error.message));
    }
    
    attemptRecovery(context, operation) {
        this.retryCount++;
        
        try {
            console.log(`Attempting recovery for ${context}.${operation}, attempt ${this.retryCount}`);
            
            switch (context) {
                case 'preview':
                    this.recoverPreview();
                    break;
                case 'upload':
                    this.recoverUpload();
                    break;
                case 'auto-save':
                    this.recoverAutoSave();
                    break;
                default:
                    this.generalRecovery();
            }
            
        } catch (recoveryError) {
            console.error('Recovery attempt failed:', recoveryError);
        }
    }
    
    recoverPreview() {
        // Clear preview container and show fallback
        const previewContainer = this.container.querySelector('[data-preview-container]');
        if (previewContainer) {
            previewContainer.innerHTML = '<p class="text-gray-500 text-sm italic">Preview temporarily unavailable</p>';
        }
    }
    
    recoverUpload() {
        // Reset file input and show manual URL option
        const fileInput = this.container.querySelector('[data-image-input]');
        if (fileInput) {
            fileInput.value = '';
        }
    }
    
    recoverAutoSave() {
        // Show manual save reminder
        const saveIndicator = this.container.querySelector('[data-save-indicator]');
        if (saveIndicator) {
            saveIndicator.textContent = '⚠ Please save manually';
            saveIndicator.className = 'text-yellow-600';
        }
    }
    
    generalRecovery() {
        // Suggest page reload for general errors
        if (this.retryCount >= this.maxRetries) {
            this.showErrorMessage(
                { message: 'Multiple errors occurred. Consider reloading the page.' },
                'general',
                'recovery'
            );
        }
    }
}

/**
 * Secure HTML Sanitizer - Prevents XSS attacks through DOM-based sanitization
 */
class SecureHtmlSanitizer {
    constructor() {
        this.allowedTags = new Set([
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'blockquote', 'pre', 'code',
            'a', 'img',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
            'div', 'span', 'iframe'
        ]);
        
        this.allowedAttributes = new Map([
            ['a', new Set(['href', 'title'])],
            ['img', new Set(['src', 'alt', 'title', 'width', 'height'])],
            ['iframe', new Set(['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'allow'])],
            ['div', new Set(['class'])],
            ['span', new Set(['class'])],
            ['table', new Set(['class'])],
            ['th', new Set(['scope'])],
            ['td', new Set(['colspan', 'rowspan'])],
            ['blockquote', new Set(['cite'])]
        ]);
        
        this.allowedProtocols = new Set([
            'http:', 'https:', 'mailto:', 'tel:'
        ]);
        
        this.allowedDomains = new Set([
            'youtube.com', 'www.youtube.com',
            'soundcloud.com', 'w.soundcloud.com'
        ]);
    }
    
    /**
     * Safely render HTML content using DOM manipulation instead of innerHTML
     */
    sanitizeAndRender(htmlString, targetElement) {
        // Clear existing content safely
        this.clearElement(targetElement);
        
        if (!htmlString || !htmlString.trim()) {
            this.createTextNode('Nothing to preview', targetElement, 'text-gray-500');
            return;
        }
        
        try {
            // Parse HTML using DOMParser (safer than innerHTML)
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlString, 'text/html');
            
            // Check for parsing errors
            const parseError = doc.querySelector('parsererror');
            if (parseError) {
                throw new Error('HTML parsing failed');
            }
            
            // Sanitize and append each child node
            this.processNodes(doc.body, targetElement);
            
        } catch (error) {
            console.error('HTML sanitization failed:', error);
            this.createTextNode('Preview error - content may contain unsafe elements', targetElement, 'text-red-500');
        }
    }
    
    /**
     * Recursively process and sanitize DOM nodes
     */
    processNodes(sourceNode, targetNode) {
        for (const child of Array.from(sourceNode.childNodes)) {
            const sanitizedNode = this.sanitizeNode(child);
            if (sanitizedNode) {
                targetNode.appendChild(sanitizedNode);
            }
        }
    }
    
    /**
     * Sanitize individual DOM node
     */
    sanitizeNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return document.createTextNode(node.textContent);
        }
        
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return null; // Skip comments, processing instructions, etc.
        }
        
        const tagName = node.tagName.toLowerCase();
        
        // Check if tag is allowed
        if (!this.allowedTags.has(tagName)) {
            // Extract text content from disallowed tags
            return document.createTextNode(node.textContent);
        }
        
        // Create new clean element
        const cleanElement = document.createElement(tagName);
        
        // Sanitize and copy allowed attributes
        this.sanitizeAttributes(node, cleanElement, tagName);
        
        // Recursively process child nodes
        this.processNodes(node, cleanElement);
        
        return cleanElement;
    }
    
    /**
     * Sanitize element attributes
     */
    sanitizeAttributes(sourceElement, targetElement, tagName) {
        const allowedAttrs = this.allowedAttributes.get(tagName) || new Set();
        
        for (const attr of Array.from(sourceElement.attributes)) {
            const attrName = attr.name.toLowerCase();
            const attrValue = attr.value;
            
            if (allowedAttrs.has(attrName)) {
                const sanitizedValue = this.sanitizeAttributeValue(attrName, attrValue, tagName);
                if (sanitizedValue !== null) {
                    targetElement.setAttribute(attrName, sanitizedValue);
                }
            }
        }
    }
    
    /**
     * Sanitize attribute values with specific validation rules
     */
    sanitizeAttributeValue(attrName, attrValue, tagName) {
        // Basic XSS pattern detection
        if (this.detectXssPatterns(attrValue)) {
            return null;
        }
        
        switch (attrName) {
            case 'href':
            case 'src':
                return this.sanitizeUrl(attrValue);
            case 'width':
            case 'height':
            case 'colspan':
            case 'rowspan':
                return this.sanitizeNumericValue(attrValue);
            case 'class':
                return this.sanitizeClassName(attrValue);
            case 'title':
            case 'alt':
                return this.sanitizeTextValue(attrValue);
            default:
                return this.sanitizeTextValue(attrValue);
        }
    }
    
    /**
     * Detect common XSS patterns
     */
    detectXssPatterns(value) {
        const xssPatterns = [
            /javascript:/i,
            /data:/i,
            /vbscript:/i,
            /on\w+\s*=/i,
            /<script/i,
            /<iframe/i,
            /eval\s*\(/i,
            /expression\s*\(/i
        ];
        
        return xssPatterns.some(pattern => pattern.test(value));
    }
    
    /**
     * Sanitize URL values
     */
    sanitizeUrl(url) {
        try {
            // Handle relative URLs
            if (url.startsWith('/') || url.startsWith('?') || url.startsWith('#')) {
                return url;
            }
            
            const urlObj = new URL(url);
            
            // Check protocol
            if (!this.allowedProtocols.has(urlObj.protocol)) {
                return null;
            }
            
            // Special validation for embed URLs
            if (urlObj.protocol === 'https:' && !this.isAllowedDomain(urlObj.hostname)) {
                // For non-embed domains, allow HTTPS URLs
                if (!url.includes('embed')) {
                    return url;
                }
                return null;
            }
            
            return url;
        } catch (e) {
            return null;
        }
    }
    
    /**
     * Check if domain is allowed for embeds
     */
    isAllowedDomain(hostname) {
        return this.allowedDomains.has(hostname.toLowerCase());
    }
    
    /**
     * Sanitize numeric values
     */
    sanitizeNumericValue(value) {
        const num = parseInt(value, 10);
        return (isNaN(num) || num < 0 || num > 9999) ? null : num.toString();
    }
    
    /**
     * Sanitize CSS class names
     */
    sanitizeClassName(value) {
        // Allow only alphanumeric, hyphens, underscores, and spaces
        return value.replace(/[^a-zA-Z0-9\s_-]/g, '').trim();
    }
    
    /**
     * Sanitize text values
     */
    sanitizeTextValue(value) {
        // Remove potential XSS vectors while preserving text
        return value.replace(/[<>"']/g, '');
    }
    
    /**
     * Safely clear element content
     */
    clearElement(element) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }
    
    /**
     * Create text node with optional CSS class
     */
    createTextNode(text, targetElement, className = null) {
        const p = document.createElement('p');
        if (className) {
            p.className = className;
        }
        p.textContent = text;
        targetElement.appendChild(p);
    }
}

/**
 * XSS Detection and Monitoring Service
 */
class XssMonitor {
    constructor() {
        this.suspiciousPatterns = [
            /<script[^>]*>/i,
            /javascript:/i,
            /on\w+\s*=/i,
            /<iframe[^>]*src\s*=\s*["'](?!https?:\/\/(www\.)?(youtube\.com|soundcloud\.com))/i,
            /eval\s*\(/i,
            /document\.cookie/i,
            /document\.write/i
        ];
    }
    
    /**
     * Monitor content for XSS attempts
     */
    detectXssAttempt(content, context = 'unknown') {
        const detectedPatterns = [];
        
        for (const pattern of this.suspiciousPatterns) {
            if (pattern.test(content)) {
                detectedPatterns.push(pattern.source);
            }
        }
        
        if (detectedPatterns.length > 0) {
            this.logXssAttempt(content, detectedPatterns, context);
            return true;
        }
        
        return false;
    }
    
    /**
     * Log XSS attempts for security monitoring
     */
    logXssAttempt(content, patterns, context) {
        const xssEvent = {
            timestamp: new Date().toISOString(),
            context: context,
            patterns: patterns,
            content: content.substring(0, 500), // Limit logged content
            userAgent: navigator.userAgent,
            url: window.location.href
        };
        
        // Log to console for development
        console.warn('XSS attempt detected:', xssEvent);
        
        // Send to server for production monitoring
        if (typeof fetch !== 'undefined') {
            fetch('/api/security/xss-attempt', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify(xssEvent)
            }).catch(err => {
                console.error('Failed to report XSS attempt:', err);
            });
        }
    }
}

class MarkdownEditor {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            autoSaveDelay: 2000,
            previewDebounce: 500,
            uploadRoute: null,
            previewRoute: null,
            autoSaveRoute: null,
            ...options
        };

        this.elements = this.initializeElements();
        this.state = {
            isPreviewMode: false,
            hasUnsavedChanges: false,
            autoSaveTimeout: null,
            previewTimeout: null
        };
        
        // Initialize security components
        this.sanitizer = new SecureHtmlSanitizer();
        this.xssMonitor = new XssMonitor();
        
        // Track bound event handlers for cleanup
        this.boundHandlers = {
            handleContentChange: null,
            handlePreviewClick: null,
            handleClosePreview: null,
            handleModalClick: null,
            handleKeydown: null,
            handleToolbarClick: null,
            handleImageInput: null,
            handleDragEvents: null
        };
        
        // Track active fetch operations for cancellation
        this.abortController = null;
        
        // Initialize error boundary
        this.errorBoundary = new MarkdownEditorErrorBoundary(container);
        
        // Make error boundary available globally for recovery operations
        if (!window.markdownEditorErrorBoundary) {
            window.markdownEditorErrorBoundary = this.errorBoundary;
        }

        this.init();
    }

    initializeElements() {
        return {
            originalTextarea: this.container.querySelector('textarea[name]'),
            markdownTextarea: this.container.querySelector('[data-markdown-textarea]'),
            previewContainer: this.container.querySelector('[data-preview-container]'),
            previewModal: this.container.querySelector('[data-preview-modal]') || document.querySelector('[data-preview-modal]'),
            modalPreviewContent: this.container.querySelector('[data-modal-preview-content]') || document.querySelector('[data-modal-preview-content]'),
            toolbar: this.container.querySelector('[data-toolbar]'),
            dropZone: this.container.querySelector('[data-drop-zone]'),
            imageInput: this.container.querySelector('[data-image-input]'),
            saveIndicator: this.container.querySelector('[data-save-indicator]'),
            previewBtn: this.container.querySelector('[data-preview-btn]'),
            closePreviewBtn: document.querySelector('[data-close-preview]')
        };
    }

    init() {
        this.setupEventListeners();
        this.setupToolbar();
        this.setupAutoSave();
        this.setupKeyboardShortcuts();
        this.setupImageUpload();
        this.initializeContent();
    }

    initializeContent() {
        // Sync initial content
        if (this.elements.originalTextarea.value) {
            this.elements.markdownTextarea.value = this.elements.originalTextarea.value;
        }
    }

    setupEventListeners() {
        // Create bound handlers for proper cleanup
        this.boundHandlers.handleContentChange = (e) => {
            this.handleContentChange(e.target.value);
        };
        
        this.boundHandlers.handlePreviewClick = () => {
            this.showPreviewModal();
        };
        
        this.boundHandlers.handleClosePreview = () => {
            this.hidePreviewModal();
        };
        
        this.boundHandlers.handleModalClick = (e) => {
            if (e.target === this.elements.previewModal) {
                this.hidePreviewModal();
            }
        };
        
        this.boundHandlers.handleKeydown = (e) => {
            if (e.key === 'Escape' && !this.elements.previewModal?.classList.contains('hidden')) {
                this.hidePreviewModal();
            }
        };

        // Attach event listeners with bound handlers
        this.elements.markdownTextarea?.addEventListener('input', this.boundHandlers.handleContentChange);
        this.elements.previewBtn?.addEventListener('click', this.boundHandlers.handlePreviewClick);
        this.elements.closePreviewBtn?.addEventListener('click', this.boundHandlers.handleClosePreview);
        this.elements.previewModal?.addEventListener('click', this.boundHandlers.handleModalClick);
        document.addEventListener('keydown', this.boundHandlers.handleKeydown);
    }

    setupToolbar() {
        if (!this.elements.toolbar) return;

        this.boundHandlers.handleToolbarClick = (e) => {
            const button = e.target.closest('[data-action]');
            if (!button) return;

            e.preventDefault();
            const action = button.dataset.action;
            const level = button.dataset.level;

            this.executeAction(action, level);
        };

        this.elements.toolbar.addEventListener('click', this.boundHandlers.handleToolbarClick);
    }

    setupAutoSave() {
        if (!this.elements.originalTextarea?.hasAttribute('data-autosave-field')) return;

        const field = this.elements.originalTextarea.dataset.autosaveField;
        
        // Check if AutoSaveManager is available
        if (window.autoSaveManager) {
            this.elements.markdownTextarea.addEventListener('input', () => {
                const content = this.elements.markdownTextarea.value;
                window.autoSaveManager.scheduleAutoSave(field, content);
            });
        } else {
            console.warn('AutoSaveManager not available, falling back to basic auto-save');
            this.setupBasicAutoSave(field);
        }
    }
    
    setupBasicAutoSave(field) {
        const autoSaveRoute = this.options.autoSaveRoute;
        if (!autoSaveRoute) return;

        this.elements.markdownTextarea.addEventListener('input', () => {
            this.scheduleBasicAutoSave(field, autoSaveRoute);
        });
    }

    setupKeyboardShortcuts() {
        this.elements.markdownTextarea?.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key.toLowerCase()) {
                    case 'b':
                        e.preventDefault();
                        this.executeAction('bold');
                        break;
                    case 'i':
                        e.preventDefault();
                        this.executeAction('italic');
                        break;
                    case 'k':
                        e.preventDefault();
                        this.executeAction('link');
                        break;
                    case 'e':
                        e.preventDefault();
                        this.executeAction('code');
                        break;
                }
            }
        });
    }

    setupImageUpload() {
        if (!this.elements.dropZone || !this.options.uploadRoute) return;

        // Drag and drop handlers
        const dragEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
        dragEvents.forEach(eventName => {
            this.elements.markdownTextarea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        this.elements.markdownTextarea.addEventListener('dragenter', () => {
            this.elements.dropZone.classList.remove('hidden');
        });

        this.elements.markdownTextarea.addEventListener('dragleave', (e) => {
            if (!this.container.contains(e.relatedTarget)) {
                this.elements.dropZone.classList.add('hidden');
            }
        });

        this.elements.markdownTextarea.addEventListener('drop', (e) => {
            this.elements.dropZone.classList.add('hidden');
            const files = Array.from(e.dataTransfer.files);
            const imageFiles = files.filter(file => file.type.startsWith('image/'));
            if (imageFiles.length > 0) {
                this.uploadImages(imageFiles);
            }
        });

        // File input handler
        this.boundHandlers.handleImageInput = (e) => {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                this.uploadImages(files);
            }
        };
        
        this.elements.imageInput?.addEventListener('change', this.boundHandlers.handleImageInput);
    }

    executeAction(action, level = null) {
        const textarea = this.elements.markdownTextarea;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        let replacement = '';
        let cursorOffset = 0;

        switch (action) {
            case 'bold':
                replacement = `**${selectedText}**`;
                cursorOffset = selectedText ? 0 : 2;
                break;

            case 'italic':
                replacement = `*${selectedText}*`;
                cursorOffset = selectedText ? 0 : 1;
                break;

            case 'strikethrough':
                replacement = `~~${selectedText}~~`;
                cursorOffset = selectedText ? 0 : 2;
                break;

            case 'heading':
                const headingLevel = level || 2;
                const headingPrefix = '#'.repeat(headingLevel) + ' ';
                replacement = selectedText ? `${headingPrefix}${selectedText}` : headingPrefix;
                cursorOffset = selectedText ? 0 : headingPrefix.length;
                break;

            case 'unordered-list':
                replacement = selectedText ? `- ${selectedText}` : '- ';
                cursorOffset = selectedText ? 0 : 2;
                break;

            case 'ordered-list':
                replacement = selectedText ? `1. ${selectedText}` : '1. ';
                cursorOffset = selectedText ? 0 : 3;
                break;

            case 'quote':
                replacement = selectedText ? `> ${selectedText}` : '> ';
                cursorOffset = selectedText ? 0 : 2;
                break;

            case 'code':
                replacement = `\`${selectedText}\``;
                cursorOffset = selectedText ? 0 : 1;
                break;

            case 'code-block':
                const language = this.promptForLanguage();
                replacement = `\n\`\`\`${language}\n${selectedText}\n\`\`\`\n`;
                cursorOffset = selectedText ? 0 : 4 + language.length;
                break;

            case 'link':
                const url = this.promptForUrl();
                if (url) {
                    const linkText = selectedText || 'link text';
                    replacement = `[${linkText}](${url})`;
                    cursorOffset = selectedText ? 0 : 1;
                }
                break;

            case 'image':
                const imageUrl = this.promptForImageUrl();
                if (imageUrl) {
                    const altText = selectedText || 'image';
                    replacement = `![${altText}](${imageUrl})`;
                    cursorOffset = selectedText ? 0 : 2;
                }
                break;

            case 'image-upload':
                this.elements.imageInput?.click();
                return;

            case 'youtube':
                this.insertYouTubeEmbed();
                return;

            case 'soundcloud':
                this.insertSoundCloudEmbed();
                return;

            case 'toggle-preview':
                this.toggleInlinePreview();
                return;

            default:
                return;
        }

        this.insertText(replacement, start, end, cursorOffset);
    }

    insertText(text, start, end, cursorOffset = 0) {
        const textarea = this.elements.markdownTextarea;
        const before = textarea.value.substring(0, start);
        const after = textarea.value.substring(end);

        textarea.value = before + text + after;
        this.syncToOriginal();

        // Set cursor position
        const newCursorPos = start + text.length - cursorOffset;
        textarea.focus();
        textarea.setSelectionRange(newCursorPos, newCursorPos);

        this.handleContentChange(textarea.value);
    }

    insertYouTubeEmbed() {
        const url = prompt('Enter YouTube URL:');
        if (!url) return;
        
        // XSS monitoring
        if (this.xssMonitor.detectXssAttempt(url, 'youtube_embed')) {
            alert('Invalid URL detected. Please enter a valid YouTube URL.');
            return;
        }

        const videoId = this.extractYouTubeId(url);
        if (!videoId || !this.isValidYouTubeId(videoId)) {
            alert('Invalid YouTube URL');
            return;
        }

        const embed = `\n<div class="youtube-embed">\n  <iframe width="560" height="315" src="https://www.youtube.com/embed/${this.escapeHtml(videoId)}" frameborder="0" allowfullscreen></iframe>\n</div>\n`;
        this.insertAtCursor(embed);
    }

    insertSoundCloudEmbed() {
        const url = prompt('Enter SoundCloud track URL:');
        if (!url) return;
        
        // XSS monitoring and URL validation
        if (this.xssMonitor.detectXssAttempt(url, 'soundcloud_embed')) {
            alert('Invalid URL detected. Please enter a valid SoundCloud URL.');
            return;
        }
        
        if (!this.isValidSoundCloudUrl(url)) {
            alert('Invalid SoundCloud URL');
            return;
        }

        // For now, insert as a link with special markdown
        // The MarkdownService will handle conversion to embed
        const embed = `\n[SoundCloud](${this.escapeHtml(url)})\n`;
        this.insertAtCursor(embed);
    }

    insertAtCursor(text) {
        const textarea = this.elements.markdownTextarea;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        this.insertText(text, start, end);
    }

    extractYouTubeId(url) {
        const patterns = [
            /youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/,
            /youtu\.be\/([a-zA-Z0-9_-]{11})/,
            /youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/
        ];
        
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match && match[1]) {
                return match[1];
            }
        }
        
        return null;
    }
    
    /**
     * Validate YouTube video ID format
     */
    isValidYouTubeId(videoId) {
        return /^[a-zA-Z0-9_-]{11}$/.test(videoId);
    }
    
    /**
     * Validate SoundCloud URL
     */
    isValidSoundCloudUrl(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.protocol === 'https:' && 
                   (urlObj.hostname === 'soundcloud.com' || urlObj.hostname === 'www.soundcloud.com');
        } catch {
            return false;
        }
    }
    
    /**
     * Escape HTML entities
     */
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    promptForLanguage() {
        return prompt('Enter language for syntax highlighting (optional):') || '';
    }

    promptForUrl() {
        const url = prompt('Enter URL:');
        if (url && this.xssMonitor.detectXssAttempt(url, 'link_url')) {
            alert('Invalid URL detected.');
            return null;
        }
        return url;
    }

    promptForImageUrl() {
        const url = prompt('Enter image URL:');
        if (url && this.xssMonitor.detectXssAttempt(url, 'image_url')) {
            alert('Invalid URL detected.');
            return null;
        }
        return url;
    }

    handleContentChange(content) {
        // Monitor for XSS attempts in content
        this.xssMonitor.detectXssAttempt(content, 'markdown_content');
        
        this.syncToOriginal();
        this.state.hasUnsavedChanges = true;
        this.schedulePreviewUpdate();
    }

    syncToOriginal() {
        if (this.elements.originalTextarea) {
            this.elements.originalTextarea.value = this.elements.markdownTextarea.value;
        }
    }

    scheduleBasicAutoSave(field, route) {
        if (this.state.autoSaveTimeout) {
            clearTimeout(this.state.autoSaveTimeout);
        }

        this.state.autoSaveTimeout = setTimeout(() => {
            this.performBasicAutoSave(field, route);
        }, this.options.autoSaveDelay);
    }

    async performBasicAutoSave(field, route) {
        const content = this.elements.markdownTextarea.value;
        const indicator = this.elements.saveIndicator;

        // Cancel any existing requests
        if (this.abortController) {
            this.abortController.abort();
        }
        
        // Create new abort controller for this request
        this.abortController = new AbortController();

        try {
            const response = await fetch(route, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    field: field,
                    value: content
                }),
                signal: this.abortController.signal
            });

            const data = await response.json();

            if (data.success && indicator) {
                indicator.textContent = '✓ Saved';
                indicator.classList.add('text-green-500');
                setTimeout(() => {
                    indicator.textContent = '';
                    indicator.classList.remove('text-green-500');
                }, 2000);
            }

            this.state.hasUnsavedChanges = false;

        } catch (error) {
            // Don't show error if request was aborted (intentional cancellation)
            if (error.name !== 'AbortError') {
                this.errorBoundary.handleError(error, 'auto-save', 'save');
                
                if (indicator) {
                    indicator.textContent = '✗ Save Failed';
                    indicator.classList.add('text-red-500');
                    setTimeout(() => {
                        indicator.textContent = '';
                        indicator.classList.remove('text-red-500');
                    }, 3000);
                }
            }
        } finally {
            // Clear the abort controller reference
            this.abortController = null;
        }
    }

    schedulePreviewUpdate() {
        if (!this.elements.previewContainer || this.elements.previewContainer.classList.contains('hidden')) {
            return;
        }

        if (this.state.previewTimeout) {
            clearTimeout(this.state.previewTimeout);
        }

        this.state.previewTimeout = setTimeout(() => {
            this.updatePreview();
        }, this.options.previewDebounce);
    }

    async updatePreview() {
        const content = this.elements.markdownTextarea.value;
        
        if (!content.trim()) {
            if (this.elements.previewContainer) {
                this.sanitizer.clearElement(this.elements.previewContainer);
                this.sanitizer.createTextNode('Nothing to preview', this.elements.previewContainer, 'text-gray-500');
            }
            return;
        }

        try {
            const html = await this.renderMarkdown(content);
            if (this.elements.previewContainer) {
                // Use secure DOM-based rendering instead of innerHTML
                this.sanitizer.sanitizeAndRender(html, this.elements.previewContainer);
            }
        } catch (error) {
            this.errorBoundary.handleError(error, 'preview', 'update');
            
            if (this.elements.previewContainer) {
                this.sanitizer.clearElement(this.elements.previewContainer);
                this.sanitizer.createTextNode('Preview temporarily unavailable', this.elements.previewContainer, 'text-yellow-600');
            }
        }
    }

    async renderMarkdown(content) {
        if (this.options.previewRoute) {
            // Cancel previous preview request
            if (this.abortController) {
                this.abortController.abort();
            }
            
            this.abortController = new AbortController();
            
            // Use server-side rendering with MarkdownService
            const response = await fetch(this.options.previewRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ markdown: content }),
                signal: this.abortController.signal
            });

            const data = await response.json();
            return data.html;
        } else {
            // Fallback to client-side rendering
            if (typeof marked !== 'undefined') {
                return marked.parse(content);
            }
            return '<p class="text-gray-500">Preview not available</p>';
        }
    }

    toggleInlinePreview() {
        if (!this.elements.previewContainer) return;

        const isHidden = this.elements.previewContainer.classList.contains('hidden');
        
        if (isHidden) {
            this.elements.markdownTextarea.classList.add('hidden');
            this.elements.previewContainer.classList.remove('hidden');
            this.updatePreview();
            this.state.isPreviewMode = true;
        } else {
            this.elements.markdownTextarea.classList.remove('hidden');
            this.elements.previewContainer.classList.add('hidden');
            this.state.isPreviewMode = false;
        }

        // Update toolbar button state
        const previewBtn = this.elements.toolbar?.querySelector('[data-action="toggle-preview"]');
        if (previewBtn) {
            previewBtn.classList.toggle('active', this.state.isPreviewMode);
        }
    }

    async showPreviewModal() {
        if (!this.elements.previewModal || !this.elements.modalPreviewContent) return;

        const content = this.elements.markdownTextarea.value;
        
        if (!content.trim()) {
            alert('Please enter some content first.');
            return;
        }

        // Clear and show loading state securely
        this.sanitizer.clearElement(this.elements.modalPreviewContent);
        this.sanitizer.createTextNode('Loading preview...', this.elements.modalPreviewContent, 'text-gray-500');
        this.elements.previewModal.classList.remove('hidden');

        try {
            const html = await this.renderMarkdown(content);
            // Use secure DOM-based rendering instead of innerHTML
            this.sanitizer.sanitizeAndRender(html, this.elements.modalPreviewContent);
        } catch (error) {
            console.error('Preview error:', error);
            this.sanitizer.clearElement(this.elements.modalPreviewContent);
            this.sanitizer.createTextNode('Failed to generate preview', this.elements.modalPreviewContent, 'text-red-500');
        }
    }

    hidePreviewModal() {
        if (this.elements.previewModal) {
            this.elements.previewModal.classList.add('hidden');
        }
    }

    async uploadImages(files) {
        if (!this.options.uploadRoute) {
            alert('Image upload not configured');
            return;
        }

        const formData = new FormData();
        files.forEach((file, index) => {
            formData.append(`images[${index}]`, file);
        });

        // Cancel any existing upload request
        if (this.abortController) {
            this.abortController.abort();
        }
        
        this.abortController = new AbortController();

        try {
            const response = await fetch(this.options.uploadRoute, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: formData,
                signal: this.abortController.signal
            });

            const data = await response.json();

            if (data.success && data.images) {
                data.images.forEach(image => {
                    const markdown = `![${image.alt || 'Uploaded image'}](${image.url})\n`;
                    this.insertAtCursor(markdown);
                });
            } else {
                alert('Image upload failed: ' + (data.message || 'Unknown error'));
            }

        } catch (error) {
            if (error.name !== 'AbortError') {
                this.errorBoundary.handleError(error, 'upload', 'images');
            }
        } finally {
            this.abortController = null;
        }
    }

    // Public API methods
    getValue() {
        return this.elements.markdownTextarea.value;
    }

    setValue(value) {
        this.elements.markdownTextarea.value = value;
        this.syncToOriginal();
        this.handleContentChange(value);
    }

    focus() {
        this.elements.markdownTextarea?.focus();
    }

    destroy() {
        try {
            console.log('Starting MarkdownEditor cleanup...');
            
            // Cleanup timeouts and nullify references
            if (this.state.autoSaveTimeout) {
                clearTimeout(this.state.autoSaveTimeout);
                this.state.autoSaveTimeout = null;
            }
            if (this.state.previewTimeout) {
                clearTimeout(this.state.previewTimeout);
                this.state.previewTimeout = null;
            }
            
            // Abort any pending fetch requests
            if (this.abortController) {
                this.abortController.abort();
                this.abortController = null;
            }

            // Remove all event listeners using stored bound handlers
            this.removeAllEventListeners();
            
            // Clear DOM element references
            this.elements = null;
            this.container = null;
            
            // Clear state
            this.state = null;
            this.options = null;
            
            // Clear security components
            this.sanitizer = null;
            this.xssMonitor = null;
            
            // Clear bound handlers
            this.boundHandlers = null;
            
            console.log('MarkdownEditor cleanup completed');
            
        } catch (error) {
            console.error('Error during MarkdownEditor cleanup:', error);
        }
    }
    
    removeAllEventListeners() {
        try {
            // Remove input event listener
            if (this.elements?.markdownTextarea && this.boundHandlers?.handleContentChange) {
                this.elements.markdownTextarea.removeEventListener('input', this.boundHandlers.handleContentChange);
            }
            
            // Remove preview button listeners
            if (this.elements?.previewBtn && this.boundHandlers?.handlePreviewClick) {
                this.elements.previewBtn.removeEventListener('click', this.boundHandlers.handlePreviewClick);
            }
            
            if (this.elements?.closePreviewBtn && this.boundHandlers?.handleClosePreview) {
                this.elements.closePreviewBtn.removeEventListener('click', this.boundHandlers.handleClosePreview);
            }
            
            // Remove modal listeners
            if (this.elements?.previewModal && this.boundHandlers?.handleModalClick) {
                this.elements.previewModal.removeEventListener('click', this.boundHandlers.handleModalClick);
            }
            
            // Remove document-level listeners
            if (this.boundHandlers?.handleKeydown) {
                document.removeEventListener('keydown', this.boundHandlers.handleKeydown);
            }
            
            // Remove toolbar listeners
            if (this.elements?.toolbar && this.boundHandlers?.handleToolbarClick) {
                this.elements.toolbar.removeEventListener('click', this.boundHandlers.handleToolbarClick);
            }
            
            // Remove image input listeners
            if (this.elements?.imageInput && this.boundHandlers?.handleImageInput) {
                this.elements.imageInput.removeEventListener('change', this.boundHandlers.handleImageInput);
            }
            
            // Remove drag and drop listeners
            if (this.elements?.markdownTextarea && this.boundHandlers?.handleDragEvents) {
                const dragEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
                dragEvents.forEach(eventName => {
                    this.elements.markdownTextarea.removeEventListener(eventName, this.boundHandlers.handleDragEvents);
                });
            }
            
        } catch (error) {
            console.warn('Error removing event listeners:', error);
        }
    }
}

// Global markdown editor instance registry for proper cleanup
window.markdownEditorInstances = window.markdownEditorInstances || [];

// Auto-initialize markdown editors with proper instance tracking
document.addEventListener('DOMContentLoaded', function() {
    const editors = document.querySelectorAll('[data-markdown-editor]');
    
    editors.forEach(container => {
        try {
            // Extract configuration from data attributes or global config
            const options = {
                uploadRoute: container.dataset.uploadRoute,
                previewRoute: container.dataset.previewRoute,
                autoSaveRoute: container.dataset.autoSaveRoute
            };

            // Initialize editor with error handling
            const editorInstance = new MarkdownEditor(container, options);
            
            // Store instance reference for cleanup
            window.markdownEditorInstances.push(editorInstance);
            container._markdownEditorInstance = editorInstance;
            
        } catch (error) {
            console.error('Failed to initialize markdown editor:', error);
            
            // Show fallback message
            const fallbackMessage = document.createElement('div');
            fallbackMessage.className = 'text-red-500 text-sm p-3 bg-red-50 rounded border border-red-200';
            fallbackMessage.innerHTML = `
                <p><strong>Editor initialization failed.</strong></p>
                <p>Please refresh the page to try again.</p>
            `;
            container.appendChild(fallbackMessage);
        }
    });
});

// Cleanup all editor instances on page unload to prevent memory leaks
window.addEventListener('beforeunload', function() {
    if (window.markdownEditorInstances) {
        window.markdownEditorInstances.forEach(instance => {
            try {
                if (instance && typeof instance.destroy === 'function') {
                    instance.destroy();
                }
            } catch (error) {
                console.warn('Error cleaning up markdown editor instance:', error);
            }
        });
        window.markdownEditorInstances = [];
    }
});

// Page visibility API cleanup for better memory management
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, cleanup non-essential resources
        if (window.markdownEditorInstances) {
            window.markdownEditorInstances.forEach(instance => {
                try {
                    // Cancel any ongoing requests
                    if (instance.abortController) {
                        instance.abortController.abort();
                    }
                    
                    // Clear timeouts
                    if (instance.state?.previewTimeout) {
                        clearTimeout(instance.state.previewTimeout);
                        instance.state.previewTimeout = null;
                    }
                } catch (error) {
                    console.warn('Error during visibility cleanup:', error);
                }
            });
        }
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MarkdownEditor;
}

// Global export for direct script inclusion
window.MarkdownEditor = MarkdownEditor;