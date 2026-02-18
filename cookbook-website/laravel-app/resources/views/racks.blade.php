<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <x-seo-meta :metaTags="app('App\Services\SeoService')->getHomeMetaTags()" />

    {{-- Structured Data --}}
    <x-structured-data :data="app('App\Services\SeoService')->getStructuredData('website')" />

    {{-- Favicon and App Icons --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Scripts -->
    
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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100" itemscope itemtype="https://schema.org/WebPage">
    <!-- Navigation -->
    @auth
        @livewire('navigation-menu')
    @else
        <nav class="bg-white border-b-2 border-black">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center space-x-3 hover:opacity-90 transition-opacity" aria-label="Ableton Cookbook - Home">
                            <div class="w-8 h-8 bg-vibrant-purple rounded flex items-center justify-center">
                                <span class="text-white font-bold text-sm" aria-hidden="true">AC</span>
                            </div>
                            <span class="text-black font-bold hidden sm:block">Ableton Cookbook</span>
                        </a>
                    </div>

                    <!-- Guest Auth Links -->
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" class="link">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="btn-primary">
                            Register
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <!-- Main Content -->
    <main class="min-h-screen bg-gray-100" role="main">
        <div class="sr-only">
            <h1>Ableton Cookbook - Share and Discover Ableton Live Racks</h1>
            <p>Browse and download high-quality Ableton Live racks including instrument racks, audio effect racks, and MIDI racks shared by music producers worldwide.</p>
        </div>
        @livewire('rack-browser')
        
        <!-- Recent Blog Posts Section -->
        @if(isset($recentBlogPosts) && $recentBlogPosts->isNotEmpty())
            <section class="bg-white py-12">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">Latest from the Blog</h2>
                        <p class="text-gray-600 max-w-2xl mx-auto">
                            Stay updated with the latest tips, tricks, and insights for music production with Ableton Live.
                        </p>
                    </div>
                    
                    <div class="grid md:grid-cols-3 gap-8">
                        @foreach($recentBlogPosts as $post)
                            <article class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-shadow">
                                @if($post->featured_image_path)
                                    <div class="h-48 bg-gray-200 overflow-hidden">
                                        <img src="{{ asset('storage/' . $post->featured_image_path) }}" 
                                             alt="{{ $post->title }}" 
                                             class="w-full h-full object-cover">
                                    </div>
                                @endif
                                
                                <div class="p-6">
                                    <div class="flex items-center mb-3">
                                        <span class="inline-block px-3 py-1 text-xs font-medium text-white rounded-full"
                                              style="background-color: {{ $post->category->color }}">
                                            {{ $post->category->name }}
                                        </span>
                                        <span class="text-sm text-gray-500 ml-3">
                                            {{ $post->published_at->format('M j, Y') }}
                                        </span>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600 transition-colors">
                                            {{ $post->title }}
                                        </a>
                                    </h3>
                                    
                                    <p class="text-gray-600 line-clamp-3 mb-4">
                                        {{ $post->excerpt }}
                                    </p>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500">
                                            By {{ $post->author->name }}
                                        </span>
                                        <a href="{{ route('blog.show', $post->slug) }}" 
                                           class="text-blue-600 hover:text-blue-800 font-medium text-sm transition-colors">
                                            Read More →
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    
                    <div class="text-center mt-8">
                        <a href="{{ route('blog.index') }}" 
                           class="inline-block bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                            View All Posts
                        </a>
                    </div>
                </div>
            </section>
        @endif
    </main>

    {{-- Footer --}}
    <x-footer />

    @livewireScripts
</body>
</html>