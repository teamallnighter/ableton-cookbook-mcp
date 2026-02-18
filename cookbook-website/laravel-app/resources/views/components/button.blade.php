@php
    $variant = $attributes->get('variant', 'primary');
    $size = $attributes->get('size', 'md');
    
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed';
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs rounded',
        'md' => 'px-4 py-2 text-sm rounded',
        'lg' => 'px-6 py-3 text-base rounded-lg',
    ];
    
    $variantClasses = [
        'primary' => 'bg-ableton-accent text-ableton-black hover:opacity-90 focus:ring-2 focus:ring-ableton-accent focus:ring-offset-2 focus:ring-offset-ableton-black',
        'secondary' => 'bg-ableton-gray text-ableton-light border border-ableton-light/20 hover:bg-ableton-light/10 focus:ring-2 focus:ring-ableton-light focus:ring-offset-2 focus:ring-offset-ableton-black',
        'danger' => 'bg-ableton-danger text-ableton-black hover:opacity-90 focus:ring-2 focus:ring-ableton-danger focus:ring-offset-2 focus:ring-offset-ableton-black',
        'warning' => 'bg-ableton-warning text-ableton-black hover:opacity-90 focus:ring-2 focus:ring-ableton-warning focus:ring-offset-2 focus:ring-offset-ableton-black',
        'ghost' => 'text-ableton-light hover:bg-ableton-light/10 focus:ring-2 focus:ring-ableton-light focus:ring-offset-2 focus:ring-offset-ableton-black',
    ];
    
    $classes = $baseClasses . ' ' . $sizeClasses[$size] . ' ' . $variantClasses[$variant];
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $classes])->except(['variant', 'size']) }}>
    {{ $slot }}
</button>
