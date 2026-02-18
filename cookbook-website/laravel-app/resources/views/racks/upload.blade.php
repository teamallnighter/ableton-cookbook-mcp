<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :items="[
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Upload Rack', 'url' => route('racks.upload')]
        ]" />

        <!-- Progress Indicator -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <!-- Step 1: Upload (Active) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        1
                    </div>
                    <span class="ml-2 text-black font-semibold">Upload</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-gray-300"></div>
                
                <!-- Step 2: Annotate (Inactive) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                        2
                    </div>
                    <span class="ml-2 text-gray-600">Annotate</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-gray-300"></div>
                
                <!-- Step 3: Details (Inactive) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                        3
                    </div>
                    <span class="ml-2 text-gray-600">Details</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card card-body text-center">
            <h1 class="text-3xl font-bold mb-2 text-black">Upload Your Ableton Rack</h1>
            <p class="text-gray-600 mb-8">Share your creative racks with the community</p>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-vibrant-red/10 border border-vibrant-red rounded-lg">
                    <div class="text-vibrant-red font-medium mb-2">Please fix the following errors:</div>
                    <ul class="text-vibrant-red text-sm list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-6 p-4 bg-vibrant-green/10 border border-vibrant-green rounded-lg">
                    <div class="text-vibrant-green font-medium">{{ session('success') }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('racks.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                
                <!-- File Upload Zone -->
                <div class="mb-8">
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 bg-gray-50 hover:border-vibrant-green transition-colors duration-300" 
                         id="dropZone"
                         ondrop="handleDrop(event)" 
                         ondragover="handleDragOver(event)" 
                         ondragleave="handleDragLeave(event)"
                         onclick="document.getElementById('rackFile').click()">
                        
                        <div id="dropZoneContent">
                            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="text-xl text-gray-600 font-semibold mb-2">Drop your .adg file here</p>
                            <p class="text-gray-500 mb-4">or click to browse</p>
                            <p class="text-sm text-gray-400">Maximum file size: 10MB</p>
                        </div>
                        
                        <div id="fileInfo" class="hidden">
                            <svg class="mx-auto h-12 w-12 text-vibrant-green mb-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-vibrant-green font-semibold" id="fileName"></p>
                            <p class="text-sm text-gray-500" id="fileSize"></p>
                        </div>
                        
                        <input type="file" 
                               id="rackFile" 
                               name="rack_file" 
                               accept=".adg" 
                               class="hidden"
                               onchange="handleFileSelect(event)"
                               required>
                    </div>
                    
                    @error('rack_file')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Terms of Service Agreement -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <input type="checkbox" 
                               id="tosAcceptance" 
                               name="tos_acceptance" 
                               class="mt-1 h-4 w-4 text-vibrant-purple focus:ring-vibrant-purple border-gray-300 rounded"
                               required>
                        <label for="tosAcceptance" class="ml-3 text-sm text-gray-700">
                            I agree to the 
                            <a href="{{ route('legal.terms') }}" 
                               target="_blank" 
                               class="text-vibrant-purple hover:underline font-medium">Terms of Service</a>
                            and 
                            <a href="{{ route('legal.privacy') }}" 
                               target="_blank" 
                               class="text-vibrant-purple hover:underline font-medium">Privacy Policy</a>.
                            I confirm that I have the right to upload and share this content, and that it does not infringe on any third-party intellectual property rights.
                        </label>
                    </div>
                    @error('tos_acceptance')
                        <p class="mt-2 text-sm text-red-600 ml-7">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Upload Button -->
                <div class="flex justify-center">
                    <button type="submit" 
                            id="uploadBtn"
                            class="px-8 py-3 bg-vibrant-green text-black font-bold rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                        <span id="uploadBtnText">Upload & Analyze Rack</span>
                        <svg id="uploadSpinner" class="hidden animate-spin -ml-1 mr-3 h-5 w-5 text-black inline" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Info Section -->
            <div class="mt-12 text-left">
                <h2 class="text-xl font-bold text-black mb-4">What happens next?</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="text-center p-4">
                        <div class="w-12 h-12 rounded-full bg-vibrant-green/20 flex items-center justify-center mx-auto mb-3">
                            <span class="text-vibrant-green font-bold">1</span>
                        </div>
                        <h3 class="font-semibold text-black mb-2">Analysis</h3>
                        <p class="text-sm text-gray-600">We'll analyze your rack structure, devices, and chains automatically.</p>
                    </div>
                    <div class="text-center p-4">
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center mx-auto mb-3">
                            <span class="text-gray-600 font-bold">2</span>
                        </div>
                        <h3 class="font-semibold text-gray-600 mb-2">Customize</h3>
                        <p class="text-sm text-gray-600">Name your chains for better organization and understanding.</p>
                    </div>
                    <div class="text-center p-4">
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center mx-auto mb-3">
                            <span class="text-gray-600 font-bold">3</span>
                        </div>
                        <h3 class="font-semibold text-gray-600 mb-2">Publish</h3>
                        <p class="text-sm text-gray-600">Add description, tags, and share with the community.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // File drag and drop functionality
        function handleDragOver(e) {
            e.preventDefault();
            document.getElementById('dropZone').classList.add('border-vibrant-green', 'bg-vibrant-green/5');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            document.getElementById('dropZone').classList.remove('border-vibrant-green', 'bg-vibrant-green/5');
        }

        function handleDrop(e) {
            e.preventDefault();
            document.getElementById('dropZone').classList.remove('border-vibrant-green', 'bg-vibrant-green/5');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.name.toLowerCase().endsWith('.adg')) {
                    document.getElementById('rackFile').files = files;
                    displayFileInfo(file);
                } else {
                    alert('Please upload an .adg file');
                }
            }
        }

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                displayFileInfo(file);
            }
        }

        function displayFileInfo(file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            
            document.getElementById('dropZoneContent').classList.add('hidden');
            document.getElementById('fileInfo').classList.remove('hidden');
            document.getElementById('fileName').textContent = fileName;
            document.getElementById('fileSize').textContent = fileSize;
        }

        // Handle form submission
        document.querySelector('form').addEventListener('submit', function() {
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnText = document.getElementById('uploadBtnText');
            const uploadSpinner = document.getElementById('uploadSpinner');
            
            uploadBtn.disabled = true;
            uploadBtnText.textContent = 'Uploading...';
            uploadSpinner.classList.remove('hidden');
        });
    </script>
</x-app-layout>
