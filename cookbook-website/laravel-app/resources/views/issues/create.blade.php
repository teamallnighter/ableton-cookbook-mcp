<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Breadcrumbs --}}
        <x-breadcrumbs :items="[
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Submit Issue', 'url' => route('issues.create')]
        ]" />

        <div class="card card-body">
            <h1 class="text-3xl font-bold mb-2 text-black">Submit an Issue or Upload a Rack</h1>
            <p class="text-gray-600 mb-8">Report issues, submit new racks, or provide feedback</p>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-vibrant-red/10 border border-vibrant-red rounded-lg">
                    <h3 class="font-bold text-vibrant-red mb-2">Please fix the following errors:</h3>
                    <ul class="list-disc list-inside text-vibrant-red">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-6 p-4 bg-vibrant-green/10 border border-vibrant-green rounded-lg">
                    <p class="text-vibrant-green font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('issues.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Hidden field for rack_id if reporting specific rack --}}
                @if(request('rack_id'))
                    <input type="hidden" name="rack_id" value="{{ request('rack_id') }}">
                @endif

                {{-- Issue Type --}}
                <div>
                    <label for="issue_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Issue Type <span class="text-vibrant-red">*</span>
                    </label>
                    <select name="issue_type_id" id="issue_type_id" required 
                            onchange="toggleFileUpload()" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                        <option value="">Select an issue type...</option>
                        @foreach($issueTypes as $type)
                            <option value="{{ $type->id }}" 
                                    data-allows-upload="{{ $type->allows_file_upload ? 'true' : 'false' }}"
                                    {{ old('issue_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->display_name }} - {{ $type->description }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Title <span class="text-vibrant-red">*</span>
                    </label>
                    <input type="text" name="title" id="title" required maxlength="255"
                           value="{{ old('title') }}"
                           placeholder="Brief description of your issue or rack submission"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description <span class="text-vibrant-red">*</span>
                    </label>
                    <textarea name="description" id="description" required rows="5"
                              placeholder="Provide detailed information about your issue or rack..."
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">{{ old('description') }}</textarea>
                </div>

                {{-- Priority --}}
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select name="priority" id="priority" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                        <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>

                {{-- Contact Information (for anonymous users) --}}
                @guest
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="submitter_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Your Name (Optional)
                            </label>
                            <input type="text" name="submitter_name" id="submitter_name"
                                   value="{{ old('submitter_name') }}"
                                   placeholder="Your name for follow-up"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                        </div>

                        <div>
                            <label for="submitter_email" class="block text-sm font-medium text-gray-700 mb-2">
                                Your Email (Optional)
                            </label>
                            <input type="email" name="submitter_email" id="submitter_email"
                                   value="{{ old('submitter_email') }}"
                                   placeholder="Your email for updates"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                        </div>
                    </div>
                @endguest

                {{-- File Upload Section (conditionally shown) --}}
                <div id="fileUploadSection" class="hidden p-6 bg-gray-50 rounded-lg">
                    <h3 class="text-lg font-semibold mb-4">File Upload</h3>
                    
                    <div class="space-y-4">
                        {{-- File Upload --}}
                        <div>
                            <label for="rack_file" class="block text-sm font-medium text-gray-700 mb-2">
                                Rack File (.adg, .adv, .als, .zip)
                            </label>
                            <input type="file" name="rack_file" id="rack_file" 
                                   accept=".adg,.adv,.als,.zip"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                            <p class="text-sm text-gray-500 mt-1">Maximum file size: 50MB. Supported formats: .adg (Device Group), .adv (Device), .als (Live Set), .zip</p>
                        </div>

                        {{-- Rack Details --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="rack_name" class="block text-sm font-medium text-gray-700 mb-2">Rack Name</label>
                                <input type="text" name="rack_name" id="rack_name"
                                       value="{{ old('rack_name') }}"
                                       placeholder="Name of your rack/preset"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                            </div>

                            <div>
                                <label for="ableton_version" class="block text-sm font-medium text-gray-700 mb-2">Ableton Live Version</label>
                                <select name="ableton_version" id="ableton_version"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                                    <option value="">Select version...</option>
                                    <option value="12" {{ old('ableton_version') === '12' ? 'selected' : '' }}>Live 12</option>
                                    <option value="11" {{ old('ableton_version') === '11' ? 'selected' : '' }}>Live 11</option>
                                    <option value="10" {{ old('ableton_version') === '10' ? 'selected' : '' }}>Live 10</option>
                                    <option value="other" {{ old('ableton_version') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="rack_description" class="block text-sm font-medium text-gray-700 mb-2">Rack Description</label>
                            <textarea name="rack_description" id="rack_description" rows="3"
                                      placeholder="Describe what this rack does, how to use it, etc."
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">{{ old('rack_description') }}</textarea>
                        </div>

                        <div>
                            <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                            <input type="text" name="tags" id="tags"
                                   value="{{ old('tags') }}"
                                   placeholder="drum, bass, synth, effects, etc."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-vibrant-green focus:ring focus:ring-vibrant-green focus:ring-opacity-50">
                            <p class="text-sm text-gray-500 mt-1">Separate tags with commas (e.g., "drum, techno, aggressive")</p>
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="flex justify-end">
                    <button type="submit" 
                            class="px-6 py-3 bg-vibrant-green text-black font-semibold rounded-md hover:bg-vibrant-green/90 transition-colors">
                        Submit Issue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleFileUpload() {
            const issueTypeSelect = document.getElementById('issue_type_id');
            const fileUploadSection = document.getElementById('fileUploadSection');
            const rackFileInput = document.getElementById('rack_file');
            
            const selectedOption = issueTypeSelect.options[issueTypeSelect.selectedIndex];
            const allowsUpload = selectedOption?.dataset?.allowsUpload === 'true';
            
            if (allowsUpload) {
                fileUploadSection.classList.remove('hidden');
                rackFileInput.required = true;
            } else {
                fileUploadSection.classList.add('hidden');
                rackFileInput.required = false;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', toggleFileUpload);
    </script>
</x-app-layout>
