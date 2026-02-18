@props(['submit'])

<div {{ $attributes->merge(['class' => '']) }}>
    <form wire:submit="{{ $submit }}">
        <!-- Section Header -->
        <div class="card-header">
            <h3 class="text-lg font-medium text-ableton-light">{{ $title }}</h3>
            <p class="mt-1 text-sm text-ableton-light/70">{{ $description }}</p>
        </div>

        <!-- Form Content -->
        <div class="card-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{ $form }}
            </div>
        </div>

        @if (isset($actions))
            <!-- Actions -->
            <div class="card-header border-t border-ableton-light/10 bg-ableton-gray/50">
                <div class="flex items-center justify-end space-x-4">
                    {{ $actions }}
                </div>
            </div>
        @endif
    </form>
</div>
