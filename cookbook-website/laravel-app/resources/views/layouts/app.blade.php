<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- SEO Meta Tags --}}
        <x-seo-meta :metaTags="$seoMetaTags ?? app('App\Services\SeoService')->getMetaTags()" />

        {{-- Structured Data --}}
        @if(isset($structuredData))
            <x-structured-data :data="$structuredData" />
        @endif

        {{-- Favicon and App Icons --}}
        <link rel="icon" type="image/png" href="{{ asset('favicon-96x96.png') }}" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}" />
        <meta name="apple-mobile-web-app-title" content="COOKBOOK" />
        <link rel="manifest" href="{{ asset('site.webmanifest') }}" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/css/drum-rack.css', 'resources/js/app.js', 'resources/js/drum-rack-interactions.js'])
        
        <!-- Error Handler (Load Early) -->
        <script src="{{ asset('js/error-handler.js') }}" defer></script>

        <!-- Styles -->
        @livewireStyles

        {{-- Additional Head Content --}}
        @stack('head')

    {{-- Google Analytics --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK491B502K"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZK491B502K');
    </script>


        {{-- Font Awesome Icons --}}
        <script src="https://kit.fontawesome.com/0e3bf45d1b.js" crossorigin="anonymous"></script>
    </head>
    <body class="font-sans antialiased bg-gray-100" itemscope itemtype="https://schema.org/WebPage">
        <x-banner />

        <div class="min-h-screen bg-gray-100">
            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow-sm border-b-2 border-black">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        <div class="text-black">
                            {{ $header }}
                        </div>
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1" role="main">
                {{-- Breadcrumbs --}}
                @if(isset($breadcrumbs) && $breadcrumbs->isNotEmpty())
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <x-breadcrumbs :items="$breadcrumbs" />
                    </div>
                @endif

                {{ $slot }}
            </main>
            
            {{-- Footer --}}
            <x-footer />
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
