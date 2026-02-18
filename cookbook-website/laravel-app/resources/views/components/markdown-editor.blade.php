@props([
    'name' => 'content',
    'value' => '',
    'placeholder' => 'Write your content in Markdown...',
    'rows' => 12,
    'required' => false,
    'maxlength' => null,
    'autoSave' => null,
    'uploadRoute' => null,
    'previewRoute' => null,
    'class' => '',
    'label' => null,
    'helpText' => null,
    'showToolbar' => true,
    'showPreview' => true,
    'showImageUpload' => false
])

<div class="markdown-editor-container" data-markdown-editor>
    @if($label)
        <div class="flex items-center justify-between mb-2">
            <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
                {{ $label }}
                @if($required)
                    <span class="text-red-500">*</span>
                @endif
            </label>
            @if($showPreview && $previewRoute)
                <button type="button" 
                        data-preview-btn 
                        class="text-xs text-blue-600 hover:text-blue-700 underline focus:outline-none">
                    Preview
                </button>
            @endif
        </div>
    @endif

    <div class="border border-gray-300 rounded-md overflow-hidden {{ $class }}" data-editor-container>
        @if($showToolbar)
            <!-- Enhanced Toolbar -->
            <div class="border-b border-gray-300 p-2 bg-gray-50 flex flex-wrap gap-1 items-center" data-toolbar>
                <!-- Text Formatting -->
                <button type="button" data-action="bold" class="toolbar-btn" title="Bold (Ctrl+B)">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5 4v3h5.5a2 2 0 110 4H5v3h6.5a3.5 3.5 0 100-7H11a2.5 2.5 0 100-3H5z"/>
                    </svg>
                </button>
                <button type="button" data-action="italic" class="toolbar-btn" title="Italic (Ctrl+I)">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8 2a1 1 0 000 2h.24l-1.07 10.24H6a1 1 0 100 2h8a1 1 0 100-2h-.24L14.76 4H16a1 1 0 100-2H8z"/>
                    </svg>
                </button>
                <button type="button" data-action="strikethrough" class="toolbar-btn" title="Strikethrough">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
                
                <div class="border-l mx-1 h-6"></div>
                
                <!-- Headings -->
                <button type="button" data-action="heading" data-level="2" class="toolbar-btn" title="Heading 2">
                    <span class="text-xs font-bold">H2</span>
                </button>
                <button type="button" data-action="heading" data-level="3" class="toolbar-btn" title="Heading 3">
                    <span class="text-xs font-bold">H3</span>
                </button>
                
                <div class="border-l mx-1 h-6"></div>
                
                <!-- Lists -->
                <button type="button" data-action="unordered-list" class="toolbar-btn" title="Bullet List">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" data-action="ordered-list" class="toolbar-btn" title="Numbered List">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </button>
                
                <div class="border-l mx-1 h-6"></div>
                
                <!-- Special Formatting -->
                <button type="button" data-action="quote" class="toolbar-btn" title="Quote">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0-7a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" data-action="code" class="toolbar-btn" title="Inline Code">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" data-action="code-block" class="toolbar-btn" title="Code Block">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-3.22l-1.14 1.14a.5.5 0 01-.64.06L8 15H5a2 2 0 01-2-2V5zm5.14 7.06L9.28 13H12a1 1 0 001-1V6a1 1 0 00-1-1H6a1 1 0 00-1 1v6c0 .35.18.65.44.82l1.7-1.76z" clip-rule="evenodd"/>
                    </svg>
                </button>
                
                <div class="border-l mx-1 h-6"></div>
                
                <!-- Links & Media -->
                <button type="button" data-action="link" class="toolbar-btn" title="Insert Link">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/>
                    </svg>
                </button>
                
                @if($showImageUpload && $uploadRoute)
                    <button type="button" data-action="image-upload" class="toolbar-btn" title="Upload Image">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                @endif
                
                <button type="button" data-action="image" class="toolbar-btn" title="Insert Image URL">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                    </svg>
                </button>
                
                <div class="border-l mx-1 h-6"></div>
                
                <!-- Rich Media -->
                <button type="button" data-action="youtube" class="toolbar-btn" title="Embed YouTube Video">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" data-action="soundcloud" class="toolbar-btn" title="Embed SoundCloud Track">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z" clip-rule="evenodd"/>
                    </svg>
                </button>
                
                @if($showPreview)
                    <div class="border-l mx-1 h-6"></div>
                    <button type="button" data-action="toggle-preview" class="toolbar-btn bg-blue-50" title="Toggle Preview">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                @endif
                
                <span class="ml-auto text-xs text-gray-500">Markdown supported</span>
            </div>
        @endif

        <!-- Editor/Preview Container -->
        <div class="relative" data-content-container>
            <!-- Original Textarea (Hidden) -->
            <textarea 
                name="{{ $name }}" 
                id="{{ $name }}"
                class="hidden"
                @if($required) required @endif
                @if($maxlength) maxlength="{{ $maxlength }}" @endif
                @if($autoSave) data-autosave="{{ $autoSave }}" @endif
            >{{ old($name, $value) }}</textarea>

            <!-- Markdown Editor Textarea -->
            <textarea 
                data-markdown-textarea
                class="w-full p-4 font-mono text-sm border-0 resize-none focus:outline-none"
                style="min-height: {{ $rows * 24 }}px"
                placeholder="{{ $placeholder }}"
                @if($autoSave) data-autosave-field="{{ $autoSave }}" @endif
            >{{ old($name, $value) }}</textarea>

            <!-- Preview Container -->
            @if($showPreview)
                <div 
                    data-preview-container 
                    class="w-full p-4 prose prose-lg max-w-none hidden overflow-y-auto"
                    style="min-height: {{ $rows * 24 }}px"
                ></div>
            @endif

            <!-- Drop Zone Overlay -->
            @if($showImageUpload)
                <div data-drop-zone class="absolute inset-0 hidden bg-blue-50 border-2 border-dashed border-blue-300 flex items-center justify-center z-10">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="mt-2 text-sm text-blue-600">Drop images here to upload</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Hidden File Input for Image Uploads -->
    @if($showImageUpload && $uploadRoute)
        <input type="file" data-image-input accept="image/*" class="hidden" multiple>
    @endif

    @if($helpText)
        <div class="flex justify-between mt-1">
            <span class="text-xs text-gray-500">{!! $helpText !!}</span>
            @if($autoSave)
                <span class="text-xs text-gray-400" data-save-indicator></span>
            @endif
        </div>
    @endif
</div>

<!-- Preview Modal (if not inline preview) -->
@if($showPreview && $previewRoute)
    <div data-preview-modal class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-medium">Preview</h3>
                    <button type="button" data-close-preview class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div data-modal-preview-content class="p-4 overflow-y-auto max-h-[60vh] prose prose-lg max-w-none">
                    Loading preview...
                </div>
            </div>
        </div>
    </div>
@endif

<style>
.toolbar-btn {
    @apply px-2 py-1 border border-gray-300 rounded hover:bg-gray-200 focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-700 transition-colors;
}

.toolbar-btn.active {
    @apply bg-blue-100 border-blue-300 text-blue-700;
}

.markdown-editor-container .prose {
    @apply text-sm;
}

.markdown-editor-container .prose h1 {
    @apply text-xl font-bold mt-4 mb-2;
}

.markdown-editor-container .prose h2 {
    @apply text-lg font-bold mt-3 mb-2;
}

.markdown-editor-container .prose h3 {
    @apply text-base font-bold mt-2 mb-1;
}

.markdown-editor-container .prose pre {
    @apply bg-gray-100 p-3 rounded overflow-x-auto text-xs;
}

.markdown-editor-container .prose code {
    @apply bg-gray-100 px-1 py-0.5 rounded text-xs;
}

.markdown-editor-container .prose blockquote {
    @apply border-l-4 border-gray-300 pl-4 italic text-gray-700;
}

/* YouTube and SoundCloud embeds */
.youtube-embed, .soundcloud-embed {
    @apply my-4;
}

.youtube-embed iframe, .soundcloud-embed iframe {
    @apply w-full rounded;
}
</style>