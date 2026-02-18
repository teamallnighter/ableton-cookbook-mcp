<footer class="bg-white border-t-2 border-black mt-16" role="contentinfo" itemscope itemtype="https://schema.org/WPFooter">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            {{-- Brand Column --}}
            <div class="md:col-span-1">
                <div class="flex items-center space-x-3 mb-4">
                    <x-application-logo class="w-10 h-10 text-black" />
                    <span class="text-black font-bold text-lg">Ableton Cookbook</span>
                </div>
                <p class="text-gray-600 text-sm mb-4">
                    A community platform for sharing Ableton Live racks and music production techniques.
                </p>
                <div class="text-sm text-gray-500">
                    <div>© {{ date('Y') }} Christopher Connelly</div>
                    <div>Sackville, New Brunswick, Canada</div>
                </div>
            </div>

            {{-- Platform Links --}}
            <div class="md:col-span-1">
                <h3 class="font-semibold text-black mb-4">Platform</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="{{ route('home') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Browse Racks
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('racks.upload') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Upload Rack
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            About
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Community Links --}}
            <div class="md:col-span-1">
                <h3 class="font-semibold text-black mb-4">Community</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="#" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Community Guidelines
                        </a>
                    </li>
                    <li>
                        <a href="#" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Help & Support
                        </a>
                    </li>
                    <li>
                        <a href="#" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Contact Us
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Legal Links --}}
            <div class="md:col-span-1">
                <h3 class="font-semibold text-black mb-4">Legal</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a href="{{ route('legal.terms') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Terms of Service
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.privacy') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Privacy Policy
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-vibrant-green text-black ml-1">
                                PIPEDA
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.copyright') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Copyright & DMCA
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.cookies') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors">
                            Cookie Policy
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('legal.index') }}" class="text-gray-600 hover:text-vibrant-purple transition-colors font-medium">
                            All Legal Info
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Divider --}}
        <div class="border-t border-gray-200 mt-8 pt-8">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                {{-- Canadian Compliance Notice --}}
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zM7.5 15L10 12.5L12.5 15L15 12.5L12.5 10L15 7.5L12.5 5L10 7.5L7.5 5L5 7.5L7.5 10L5 12.5L7.5 15z"/>
                    </svg>
                    Compliant with Canadian Federal & Provincial Law
                </div>

                {{-- Legal Disclaimer --}}
                <div class="text-xs text-gray-400 text-center md:text-right max-w-md">
                    This platform is not affiliated with Ableton AG. Ableton Live and related trademarks are property of Ableton AG.
                </div>
            </div>

            {{-- Accessibility Statement --}}
            <div class="mt-4 text-center">
                <p class="text-xs text-gray-400">
                    Committed to accessibility in accordance with the 
                    <a href="https://accessible.canada.ca/" target="_blank" class="hover:text-gray-600 underline">
                        Accessible Canada Act
                    </a>
                    and WCAG 2.1 Level AA standards.
                </p>
            </div>
        </div>
    </div>

    {{-- Structured Data for Organization --}}
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Ableton Cookbook',
        'url' => config('app.url'),
        'logo' => asset('favicon.svg'),
        'description' => 'A community platform for sharing Ableton Live racks and music production techniques',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => '10 Albion Street',
            'addressLocality' => 'Sackville',
            'addressRegion' => 'New Brunswick',
            'postalCode' => 'E4L 1G6',
            'addressCountry' => 'CA'
        ],
        'founder' => [
            '@type' => 'Person',
            'name' => 'Christopher Connelly'
        ],
        'sameAs' => []
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
</footer>