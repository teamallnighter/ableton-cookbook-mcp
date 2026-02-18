<div class="relative">
    <!-- Notification Bell -->
    <button 
        wire:click="toggleDropdown"
        class="relative p-2 rounded hover:bg-gray-100 transition-colors text-black"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-3.5-3.5a9 9 0 1 0-13 0L7 17h5m3 0v1a3 3 0 1 1-6 0v-1m3 0H9"/>
        </svg>
        
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 bg-vibrant-red text-white text-xs rounded-full h-5 w-5 flex items-center justify-center border border-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown -->
    @if($showDropdown)
        <div class="absolute right-0 mt-2 w-80 bg-white border-2 border-black rounded-lg shadow-lg z-50">
            
            <!-- Header -->
            <div class="p-4 border-b-2 border-black flex items-center justify-between">
                <h3 class="font-bold text-black">Notifications</h3>
                @if($unreadCount > 0)
                    <button 
                        wire:click="markAllAsRead"
                        class="text-sm text-vibrant-blue hover:text-vibrant-purple transition-colors font-medium"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
            
            <!-- Notifications List -->
            <div class="max-h-96 overflow-y-auto">
                @if($notifications->count() > 0)
                    @foreach($notifications as $notification)
                        <div class="p-4 border-b-2 border-black hover:bg-gray-100 cursor-pointer {{ $notification->read_at ? '' : 'bg-gray-50' }}" 
                             wire:click="markAsRead('{{ $notification->id }}')">
                            
                            <div class="flex items-start gap-3">
                                <!-- Icon based on notification type -->
                                <div class="flex-shrink-0 mt-1">
                                    @if(str_contains($notification->type, 'RackRated'))
                                        <div class="w-8 h-8 bg-star-yellow rounded-full flex items-center justify-center border-2 border-black">
                                            <svg class="w-4 h-4 text-black" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        </div>
                                    @elseif(str_contains($notification->type, 'NewRack'))
                                        <div class="w-8 h-8 bg-vibrant-blue rounded-full flex items-center justify-center border-2 border-black">
                                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 bg-vibrant-red rounded-full flex items-center justify-center border-2 border-black">
                                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-black">
                                        @if(isset($notification->data['message']))
                                            {{ $notification->data['message'] }}
                                        @else
                                            New notification
                                        @endif
                                    </p>
                                    <p class="text-xs mt-1 text-gray-600">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                
                                @if(!$notification->read_at)
                                    <div class="flex-shrink-0">
                                        <div class="w-3 h-3 bg-vibrant-blue rounded-full border border-black"></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-3 border-2 border-black">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-3.5-3.5a9 9 0 1 0-13 0L7 17h5m3 0v1a3 3 0 1 1-6 0v-1m3 0H9"/>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-600">No notifications yet</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    <!-- Click outside to close -->
    @if($showDropdown)
        <div class="fixed inset-0 z-40" wire:click="toggleDropdown"></div>
    @endif
</div>
