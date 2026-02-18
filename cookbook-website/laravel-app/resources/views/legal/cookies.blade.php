<x-app-layout>
    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Breadcrumbs --}}
                <x-breadcrumbs :items="[
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Legal', 'url' => route('legal.index')],
                    ['name' => 'Cookie Policy', 'url' => route('legal.cookies')]
                ]" />

                {{-- Page Header --}}
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-black">Cookie Policy</h1>
                </div>

                {{-- Cookie Policy Content --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-8 sm:px-8 prose prose-lg max-w-none">
                        {!! $cookies !!}
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-4 sm:px-8">
                        <h3 class="text-lg font-semibold text-black mb-4">Other Legal Information</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <a href="{{ route('legal.terms') }}" 
                               class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-vibrant-purple hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-vibrant-purple mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <div>
                                    <div class="font-medium text-black">Terms of Service</div>
                                    <div class="text-sm text-gray-600">User agreement</div>
                                </div>
                            </a>
                            
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('head')
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('legal.cookies') }}">
    @endpush
</x-app-layout>