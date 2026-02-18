<x-app-layout>
    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Breadcrumbs --}}
                <x-breadcrumbs :items="[
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Legal', 'url' => route('legal.index')],
                    ['name' => 'Copyright & DMCA', 'url' => route('legal.copyright')]
                ]" />

                {{-- Page Header --}}
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-black">Copyright & DMCA</h1>
                </div>

                {{-- DMCA Notice --}}
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <div class="font-semibold text-blue-800">Copyright Compliance</div>
                            <div class="text-sm text-blue-700">We respect intellectual property rights and comply with Canadian copyright law and DMCA procedures</div>
                        </div>
                    </div>
                </div>

                {{-- Copyright Policy Content --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-8 sm:px-8 prose prose-lg max-w-none">
                        {!! $copyright !!}
                    </div>
                </div>

                {{-- Quick Action Buttons --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-6 sm:px-8">
                        <h3 class="text-lg font-semibold text-black mb-4">Copyright Actions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 border border-red-200 rounded-lg bg-red-50">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <span class="font-medium text-red-800">Report Copyright Infringement</span>
                                </div>
                                <p class="text-sm text-red-700 mb-3">If you believe your copyrighted work has been infringed, submit a takedown request.</p>
                                <a href="mailto:[copyright email]?subject=Copyright%20Infringement%20Claim" 
                                   class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 transition-colors">
                                    Submit DMCA Request
                                    <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            </div>
                            
                            <div class="p-4 border border-green-200 rounded-lg bg-green-50">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="font-medium text-green-800">Submit Counter-Notice</span>
                                </div>
                                <p class="text-sm text-green-700 mb-3">If your content was removed in error, you can submit a counter-notification.</p>
                                <a href="mailto:[copyright email]?subject=DMCA%20Counter-Notice" 
                                   class="inline-flex items-center px-3 py-2 border border-green-300 text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50 transition-colors">
                                    File Counter-Notice
                                    <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
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
            </div>
        </div>
    </div>

    @push('head')
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('legal.copyright') }}">
    @endpush
</x-app-layout>