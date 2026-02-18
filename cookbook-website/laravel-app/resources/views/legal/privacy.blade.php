<x-app-layout>
    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Breadcrumbs --}}
                <x-breadcrumbs :items="[
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Legal', 'url' => route('legal.index')],
                    ['name' => 'Privacy Policy', 'url' => route('legal.privacy')]
                ]" />

                {{-- Page Header --}}
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-black">Privacy Policy</h1>
                </div>

                {{-- PIPEDA Compliance Badge --}}
                <div class="bg-vibrant-green/10 border border-vibrant-green rounded-lg p-4 mb-8">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-vibrant-green mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <div>
                            <div class="font-semibold text-vibrant-green">PIPEDA Compliant</div>
                            <div class="text-sm text-gray-700">This privacy policy complies with Canada's Personal Information Protection and Electronic Documents Act</div>
                        </div>
                    </div>
                </div>

                {{-- Privacy Policy Content --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-8 sm:px-8 prose prose-lg max-w-none">
                        {!! $privacy !!}
                    </div>
                </div>

                {{-- Privacy Rights Summary --}}
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg mb-8">
                    <div class="px-6 py-6 sm:px-8">
                        <h3 class="text-lg font-semibold text-black mb-4">Your Privacy Rights Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">Access Your Data</div>
                                        <div class="text-sm text-gray-600">Request a copy of all personal information we have about you</div>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">Correct Information</div>
                                        <div class="text-sm text-gray-600">Update or correct any inaccurate personal information</div>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">Withdraw Consent</div>
                                        <div class="text-sm text-gray-600">Opt out of marketing communications and optional data processing</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">Data Portability</div>
                                        <div class="text-sm text-gray-600">Export your data in a machine-readable format</div>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">Delete Account</div>
                                        <div class="text-sm text-gray-600">Request deletion of your account and associated data</div>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-vibrant-green mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <div class="font-medium">File Complaints</div>
                                        <div class="text-sm text-gray-600">Contact the Privacy Commissioner of Canada if unsatisfied</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="text-sm text-blue-800">
                                    To exercise any of these rights, please contact our Privacy Officer using the information provided in this policy.
                                </div>
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
            </div>
        </div>
    </div>

    @push('head')
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ route('legal.privacy') }}">
    @endpush
</x-app-layout>