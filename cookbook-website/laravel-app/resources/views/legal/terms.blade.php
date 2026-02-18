<x-app-layout>
    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Breadcrumbs --}}
                <x-breadcrumbs :items="[
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Legal', 'url' => route('legal.index')],
                    ['name' => 'Terms of Service', 'url' => route('legal.terms')]
                ]" />

                {{-- Page Header --}}
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-black">Terms of Service</h1>
                </div>

                {{-- Terms Content --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-8 sm:px-8 prose prose-lg max-w-none">
                        {!! $terms !!}
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-4 sm:px-8">
                        <h3 class="text-lg font-semibold text-black mb-4">Other Legal Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <a href="{{ route('legal.privacy') }}" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-vibrant-purple hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-vibrant-purple mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <div>
                                    <div class="font-medium text-black">Privacy Policy</div>
                                    <div class="text-sm text-gray-600">PIPEDA compliance</div>
                                </div>
                            </a>
                            
                            <a href="{{ route('legal.copyright') }}" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-vibrant-purple hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-vibrant-purple mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <div class="font-medium text-black">Copyright & DMCA</div>
                                    <div class="text-sm text-gray-600">IP protection</div>
                                </div>
                            </a>
                            
                            <a href="{{ route('legal.cookies') }}" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-vibrant-purple hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-vibrant-purple mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <div>
                                    <div class="font-medium text-black">Cookie Policy</div>
                                    <div class="text-sm text-gray-600">Tracking & cookies</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Contact Information --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-4 sm:px-8 text-center">
                        <h3 class="text-lg font-semibold text-black mb-2">Questions About These Terms?</h3>
                        <p class="text-gray-600 mb-4">Contact us if you have any questions about our Terms of Service.</p>
                        <div class="text-sm text-gray-500">
                            Christopher Connelly<br>
                            10 Albion Street, Sackville, New Brunswick, Canada E4L 1G6<br>
                            Email: [contact email]
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('head')
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('legal.terms') }}">
    @endpush
</x-app-layout>