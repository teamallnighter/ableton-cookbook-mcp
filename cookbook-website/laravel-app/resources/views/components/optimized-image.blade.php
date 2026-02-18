@props([
    'src' => '', 
    'alt' => '', 
    'class' => '', 
    'lazy' => true,
    'responsive' => true,
    'fallback' => '/images/default-rack.jpg',
    'sizes' => '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw'
])

@php
    // Generate responsive image sources if enabled
    $responsiveSources = [];
    if ($responsive && $src) {
        $basePath = pathinfo($src, PATHINFO_DIRNAME);
        $filename = pathinfo($src, PATHINFO_FILENAME);
        $extension = pathinfo($src, PATHINFO_EXTENSION);
        
        // Generate different sizes (you would generate these server-side)
        $responsiveSources = [
            'small' => $basePath . '/' . $filename . '_400w.' . $extension,
            'medium' => $basePath . '/' . $filename . '_800w.' . $extension,
            'large' => $basePath . '/' . $filename . '_1200w.' . $extension,
        ];
    }
    
    // Fallback to default image if src is empty
    $imageSrc = $src ?: $fallback;
    
    // Optimize alt text for SEO
    $optimizedAlt = $alt ?: 'Ableton Live rack preview';
@endphp

@if($responsive && !empty($responsiveSources))
<picture class="{{ $class }}">
    {{-- WebP sources for better compression --}}
    <source 
        srcset="{{ str_replace($extension, 'webp', $responsiveSources['small']) }} 400w,
                {{ str_replace($extension, 'webp', $responsiveSources['medium']) }} 800w,
                {{ str_replace($extension, 'webp', $responsiveSources['large']) }} 1200w"
        sizes="{{ $sizes }}"
        type="image/webp"
    >
    
    {{-- Fallback sources --}}
    <source 
        srcset="{{ $responsiveSources['small'] }} 400w,
                {{ $responsiveSources['medium'] }} 800w,
                {{ $responsiveSources['large'] }} 1200w"
        sizes="{{ $sizes }}"
    >
    
    <img 
        src="{{ $imageSrc }}"
        alt="{{ $optimizedAlt }}"
        class="{{ $class }}"
        @if($lazy)
            loading="lazy"
            decoding="async"
        @endif
        onerror="this.onerror=null; this.src='{{ $fallback }}';"
    >
</picture>
@else
<img 
    src="{{ $imageSrc }}"
    alt="{{ $optimizedAlt }}"
    class="{{ $class }}"
    @if($lazy)
        loading="lazy"
        decoding="async"
    @endif
    onerror="this.onerror=null; this.src='{{ $fallback }}';"
>
@endif