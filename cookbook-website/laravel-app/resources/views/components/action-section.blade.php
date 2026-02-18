<div {{ $attributes->merge(['class' => '']) }}>
    <!-- Section Header -->
    <div class="card-header">
        <h3 class="text-lg font-medium text-ableton-light">{{ $title }}</h3>
        <p class="mt-1 text-sm text-ableton-light/70">{{ $description }}</p>
    </div>

    <!-- Content -->
    <div class="card-body">
        {{ $content }}
    </div>
</div>
