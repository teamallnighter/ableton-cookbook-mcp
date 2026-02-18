@props(['metaTags' => []])

{{-- Basic Meta Tags --}}
<title>{{ $metaTags['title'] ?? config('app.name') }}</title>
<meta name="description" content="{{ $metaTags['description'] ?? '' }}">
<meta name="keywords" content="{{ $metaTags['keywords'] ?? '' }}">
<meta name="robots" content="{{ $metaTags['robots'] ?? 'index, follow' }}">
<meta name="author" content="{{ $metaTags['article_author'] ?? config('app.name') }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $metaTags['canonical_url'] ?? request()->url() }}">

{{-- Open Graph Meta Tags --}}
<meta property="og:type" content="{{ $metaTags['og_type'] ?? 'website' }}">
<meta property="og:title" content="{{ $metaTags['og_title'] ?? $metaTags['title'] ?? config('app.name') }}">
<meta property="og:description" content="{{ $metaTags['og_description'] ?? $metaTags['description'] ?? '' }}">
<meta property="og:url" content="{{ $metaTags['og_url'] ?? request()->url() }}">
<meta property="og:site_name" content="{{ $metaTags['og_site_name'] ?? config('app.name') }}">
@if(isset($metaTags['og_image']))
<meta property="og:image" content="{{ $metaTags['og_image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
@endif

{{-- Article Meta Tags (for rack pages) --}}
@if(isset($metaTags['article_author']))
<meta property="article:author" content="{{ $metaTags['article_author'] }}">
@endif
@if(isset($metaTags['article_published_time']))
<meta property="article:published_time" content="{{ $metaTags['article_published_time'] }}">
@endif
@if(isset($metaTags['article_modified_time']))
<meta property="article:modified_time" content="{{ $metaTags['article_modified_time'] }}">
@endif

{{-- Profile Meta Tags (for user pages) --}}
@if(isset($metaTags['profile_first_name']))
<meta property="profile:first_name" content="{{ $metaTags['profile_first_name'] }}">
@endif
@if(isset($metaTags['profile_last_name']))
<meta property="profile:last_name" content="{{ $metaTags['profile_last_name'] }}">
@endif
@if(isset($metaTags['profile_username']))
<meta property="profile:username" content="{{ $metaTags['profile_username'] }}">
@endif

{{-- Twitter Card Meta Tags --}}
<meta name="twitter:card" content="{{ $metaTags['twitter_card'] ?? 'summary' }}">
<meta name="twitter:title" content="{{ $metaTags['twitter_title'] ?? $metaTags['title'] ?? config('app.name') }}">
<meta name="twitter:description" content="{{ $metaTags['twitter_description'] ?? $metaTags['description'] ?? '' }}">
@if(isset($metaTags['twitter_image']))
<meta name="twitter:image" content="{{ $metaTags['twitter_image'] }}">
@endif
<meta name="twitter:site" content="@abletonCookbook">
<meta name="twitter:creator" content="@abletonCookbook">

{{-- Additional SEO Meta Tags --}}
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<meta name="application-name" content="{{ config('app.name') }}">
<meta name="theme-color" content="#ff6b00">
<meta name="msapplication-TileColor" content="#ff6b00">

{{-- Preconnect to external domains for performance --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="preconnect" href="https://cdn.jsdelivr.net">

{{-- Language and locale --}}
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
<meta http-equiv="content-language" content="{{ str_replace('_', '-', app()->getLocale()) }}">