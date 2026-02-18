<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('About') }}
        </h2>
    </x-slot>
<div class="min-h-screen bg-gray-100">
    <!-- Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-vibrant-purple to-vibrant-blue text-white">
        <div class="absolute inset-0 bg-black opacity-20"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                        <span class="text-2xl font-bold">AC</span>
                    </div>
                    <div>
                        <h1 class="text-5xl font-bold mb-2">Ableton Cookbook</h1>
                        <p class="text-xl text-white/90">
                            <a href="https://ableton.recipes" class="hover:underline">ableton.recipes</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        
        <!-- Mission & Vision -->
        <div class="grid md:grid-cols-2 gap-8 mb-16">
            <div class="bg-white rounded-xl shadow-md p-8">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-vibrant-purple/10 rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-vibrant-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-black">Mission</h2>
                </div>
                <p class="text-gray-700 leading-relaxed">
                    Connecting producers through shared workflows to accelerate creative growth and foster authentic musical collaboration.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-md p-8">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-vibrant-green/10 rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-vibrant-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-black">Vision</h2>
                </div>
                <p class="text-gray-700 leading-relaxed">
                    We believe every producer has unique techniques worth sharing and learning. Through Ableton Cookbook, we create a space where music makers upload, discover, and build upon each other's workflows—transforming individual creativity into collective musical innovation. We're not just sharing rack presets; we're cultivating a community where producers grow together, one workflow at a time.
                </p>
            </div>
        </div>

        <!-- What We Do -->
        <div class="bg-white rounded-xl shadow-md p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-6">What We Do</h2>
            <p class="text-gray-700 text-lg leading-relaxed">
                Ableton Cookbook is a next-generation web application built for Ableton Live users. We make it effortless to share, explore, and remix custom Instruments, Audio Effect, and MIDI Rack workflows—unlocking new creative possibilities for every producer.
            </p>
        </div>

        <!-- Why We Exist -->
        <div class="bg-gradient-to-r from-vibrant-purple/5 to-vibrant-blue/5 rounded-xl p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-6">Why We Exist</h2>
            <p class="text-gray-700 text-lg leading-relaxed">
                The Ableton rack system captures the heart of your creative process, packaging it into a reusable "recipe" you can save and share. We built Ableton Cookbook to break down barriers, so no producer has to reinvent the wheel. Discover others' secrets, refine your sound, and pay it forward—all in one place.
            </p>
        </div>

        <!-- Who We Are -->
        <div class="bg-white rounded-xl shadow-md p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-6">Who We Are</h2>
            <div class="flex items-start">
                <div class="w-16 h-16 bg-vibrant-purple rounded-xl flex items-center justify-center mr-6 flex-shrink-0">
                    <span class="text-white font-bold text-xl">BD</span>
                </div>
                <div>
                    <p class="text-gray-700 text-lg leading-relaxed">
                        Founded by <strong class="text-black">Chris Connelly</strong> (aka <em>Bass Daddy Devices / Unnecessary Roughness</em>), Ableton Cookbook is driven by one solo developer's passion for bass music production, Max for Live development, and community-powered learning.
                    </p>
                </div>
            </div>
        </div>

        <!-- How It Works -->
        <div class="bg-white rounded-xl shadow-md p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-8">How It Works</h2>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-vibrant-purple rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                            <span class="text-white font-bold">1</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-black mb-2">Upload & Analyze</h3>
                            <p class="text-gray-700">
                                Drag-and-drop your .adg/.adv/.alp files. Our system reads Ableton version, edition, and workflow structure—then generates an intuitive diagram of nested chains, devices, and signal flow.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-vibrant-green rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                            <span class="text-black font-bold">2</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-black mb-2">Describe & Tag</h3>
                            <p class="text-gray-700">
                                Add a clear title, detailed description, and custom labels for chains and macros. Help others find and understand your unique workflow.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-vibrant-blue rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                            <span class="text-white font-bold">3</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-black mb-2">Explore & Follow</h3>
                            <p class="text-gray-700">
                                Browse community-curated racks by popularity, ratings, or tags. Follow favorite creators, bookmark inspiring racks, and watch your feed evolve with fresh ideas.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-vibrant-orange rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                            <span class="text-white font-bold">4</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-black mb-2">Download & Remix</h3>
                            <p class="text-gray-700">
                                Download any shared rack for free. Learn by reverse-engineering, tweak parameters, and re-upload your own variant to complete the creative loop.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Our Community -->
        <div class="bg-gradient-to-r from-vibrant-green/5 to-vibrant-cyan/5 rounded-xl p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-6">Our Community</h2>
            <p class="text-gray-700 text-lg mb-6">Ableton Cookbook thrives on authentic connections:</p>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-vibrant-green mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-700">Profiles & Followers</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-vibrant-green mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-700">Favorites & Ratings</span>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-vibrant-green mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-700">Reporting & Support</span>
                </div>
            </div>
        </div>

        <!-- Developer API -->
        <div class="bg-white rounded-xl shadow-md p-8 mb-12">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 bg-black rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-black">Developer API</h2>
            </div>
            <p class="text-gray-700 text-lg leading-relaxed">
                Tap into our growing dataset via a free, public API. Build Max for Live devices, dashboards, or new tools—any creative integration is welcome. Documentation and code samples available on GitHub.
            </p>
        </div>

        <!-- Roadmap -->
        <div class="bg-white rounded-xl shadow-md p-8 mb-12">
            <h2 class="text-3xl font-bold text-black mb-6">Roadmap</h2>
            <p class="text-gray-700 text-lg mb-6">We're constantly cooking up new features:</p>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-vibrant-purple rounded-full mr-3"></div>
                        <span class="text-gray-700"><strong>Presets:</strong> Upload and analyze individual device presets (.adg).</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-vibrant-green rounded-full mr-3"></div>
                        <span class="text-gray-700"><strong>Sessions:</strong> Share full Live Set archives (.als) with structural insights.</span>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-vibrant-blue rounded-full mr-3"></div>
                        <span class="text-gray-700"><strong>Versioning:</strong> Track changes and fork workflows—think Git for Ableton.</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 bg-vibrant-orange rounded-full mr-3"></div>
                        <span class="text-gray-700"><strong>Max for Live Integration:</strong> Deep linking and real-time rack sharing from within Live.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Get Started -->
        <div class="bg-gradient-to-r from-vibrant-purple to-vibrant-blue rounded-xl text-white p-8 text-center">
            <h2 class="text-3xl font-bold mb-6">Get Started</h2>
            <p class="text-xl mb-8">Ready to supercharge your productions?</p>
            <div class="grid md:grid-cols-2 gap-8 mb-8">
                <div>
                    <ol class="text-left space-y-3">
                        <li class="flex items-center">
                            <span class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center mr-3 text-sm font-bold">1</span>
                            Create an account
                        </li>
                        <li class="flex items-center">
                            <span class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center mr-3 text-sm font-bold">2</span>
                            Upload your first rack
                        </li>
                    </ol>
                </div>
                <div>
                    <ol class="text-left space-y-3">
                        <li class="flex items-center">
                            <span class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center mr-3 text-sm font-bold">3</span>
                            Join the conversation on our Discord
                        </li>
                        <li class="flex items-center">
                            <span class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center mr-3 text-sm font-bold">4</span>
                            Share your feedback—your ideas shape our next releases!
                        </li>
                    </ol>
                </div>
            </div>
            <p class="text-lg opacity-90 mb-6">
                Ableton Cookbook is more than a library; it's your creative playground. Let's cook up something amazing together.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 bg-white text-vibrant-purple font-medium rounded-lg hover:bg-gray-100 transition-colors">
                    Get Started
                    <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
                <a href="{{ route('home') }}" class="inline-flex items-center px-6 py-3 bg-white/20 text-white font-medium rounded-lg hover:bg-white/30 transition-colors">
                    Browse Racks
                    <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>
</x-app-layout>