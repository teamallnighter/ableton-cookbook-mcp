{{-- Redirect to new legal terms page --}}
<script>
    window.location.href = "{{ route('legal.terms') }}";
</script>

{{-- Fallback content --}}
<x-guest-layout>
    <div class="pt-4 bg-gray-100">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="bg-white shadow-md overflow-hidden sm:rounded-lg p-8">
                    <h1 class="text-2xl font-bold text-black mb-4">Redirecting to Legal Information</h1>
                    <p class="text-gray-600 mb-6">
                        Our legal information has been updated and moved to a new location for better accessibility and compliance.
                    </p>
                    <a href="{{ route('legal.terms') }}" 
                       class="inline-flex items-center px-6 py-3 bg-vibrant-purple text-white font-medium rounded-lg hover:bg-vibrant-purple/90 transition-colors">
                        View Terms of Service
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
