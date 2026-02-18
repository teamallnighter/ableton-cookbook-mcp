@props(['rack' => null, 'user' => null, 'limit' => 5])

{{-- Internal linking component for SEO --}}
@if($rack)
    {{-- Related racks by same user --}}
    @php
        $relatedRacks = $rack->user->racks()
            ->published()
            ->where('id', '!=', $rack->id)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    @endphp
    
    @if($relatedRacks->count() > 0)
        <div class="mt-8 p-6 bg-white border-2 border-black rounded-lg">
            <h3 class="text-lg font-bold text-black mb-4">More Racks by {{ $rack->user->name }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($relatedRacks as $relatedRack)
                    <a href="{{ route('racks.show', $relatedRack) }}" 
                       class="block p-3 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                       title="Download {{ $relatedRack->title }} - {{ ucfirst($relatedRack->rack_type) }} Rack">
                        <h4 class="font-medium text-black text-sm mb-1">{{ $relatedRack->title }}</h4>
                        <p class="text-xs text-gray-600">{{ ucfirst($relatedRack->rack_type) }} Rack</p>
                        @if($relatedRack->average_rating > 0)
                            <div class="flex items-center mt-1">
                                <span class="text-xs text-gray-500">★ {{ number_format($relatedRack->average_rating, 1) }}</span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Similar racks by category --}}
    @if($rack->category)
        @php
            $similarRacks = \App\Models\Rack::published()
                ->where('category', $rack->category)
                ->where('id', '!=', $rack->id)
                ->where('user_id', '!=', $rack->user_id)
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        @endphp
        
        @if($similarRacks->count() > 0)
            <div class="mt-6 p-6 bg-white border-2 border-black rounded-lg">
                <h3 class="text-lg font-bold text-black mb-4">Similar {{ $rack->category }} Racks</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($similarRacks as $similarRack)
                        <a href="{{ route('racks.show', $similarRack) }}" 
                           class="block p-3 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                           title="Download {{ $similarRack->title }} by {{ $similarRack->user->name }}">
                            <h4 class="font-medium text-black text-sm mb-1">{{ $similarRack->title }}</h4>
                            <p class="text-xs text-gray-600">by 
                                <a href="{{ route('users.show', $similarRack->user) }}" 
                                   class="text-vibrant-blue hover:underline"
                                   title="View {{ $similarRack->user->name }}'s profile">{{ $similarRack->user->name }}</a>
                            </p>
                            @if($similarRack->average_rating > 0)
                                <div class="flex items-center mt-1">
                                    <span class="text-xs text-gray-500">★ {{ number_format($similarRack->average_rating, 1) }}</span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endif

@if($user)
    {{-- Featured racks by user --}}
    @php
        $featuredRacks = $user->racks()
            ->published()
            ->orderBy('average_rating', 'desc')
            ->orderBy('downloads_count', 'desc')
            ->limit($limit)
            ->get();
    @endphp
    
    @if($featuredRacks->count() > 0)
        <div class="mt-8 p-6 bg-white border-2 border-black rounded-lg">
            <h3 class="text-lg font-bold text-black mb-4">Top Rated Racks by {{ $user->name }}</h3>
            <div class="space-y-3">
                @foreach($featuredRacks as $featuredRack)
                    <a href="{{ route('racks.show', $featuredRack) }}" 
                       class="flex items-center justify-between p-3 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                       title="Download {{ $featuredRack->title }} - {{ ucfirst($featuredRack->rack_type) }} Rack">
                        <div>
                            <h4 class="font-medium text-black">{{ $featuredRack->title }}</h4>
                            <p class="text-sm text-gray-600">{{ ucfirst($featuredRack->rack_type) }} Rack</p>
                        </div>
                        <div class="text-right">
                            @if($featuredRack->average_rating > 0)
                                <div class="text-sm text-gray-600">★ {{ number_format($featuredRack->average_rating, 1) }}</div>
                            @endif
                            <div class="text-xs text-gray-500">{{ $featuredRack->downloads_count }} downloads</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Other producers in similar categories --}}
    @php
        $userCategories = $user->racks()->published()->distinct('category')->pluck('category')->filter();
        $similarProducers = \App\Models\User::whereHas('racks', function($query) use ($userCategories) {
                $query->published()->whereIn('category', $userCategories);
            })
            ->where('id', '!=', $user->id)
            ->with(['racks' => function($query) {
                $query->published()->limit(1);
            }])
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->filter(function($producer) {
                return $producer->racks->count() > 0;
            });
    @endphp
    
    @if($similarProducers->count() > 0)
        <div class="mt-6 p-6 bg-white border-2 border-black rounded-lg">
            <h3 class="text-lg font-bold text-black mb-4">Similar Music Producers</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($similarProducers as $producer)
                    <a href="{{ route('users.show', $producer) }}" 
                       class="flex items-center p-3 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
                       title="View {{ $producer->name }}'s Ableton racks and profile">
                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                            <span class="text-sm font-bold text-gray-600">{{ substr($producer->name, 0, 1) }}</span>
                        </div>
                        <div>
                            <h4 class="font-medium text-black">{{ $producer->name }}</h4>
                            <p class="text-sm text-gray-600">{{ $producer->racks_count }} racks</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endif