<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class NotificationDropdown extends Component
{
    public $showDropdown = false;
    public $unreadCount = 0;
    
    public function mount()
    {
        $this->updateUnreadCount();
    }
    
    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
        
        if ($this->showDropdown) {
            // Mark notifications as seen when opened
            Auth::user()->update(['last_notification_read_at' => now()]);
            $this->updateUnreadCount();
        }
    }
    
    public function markAsRead($notificationId)
    {
        $notification = Auth::user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            $this->updateUnreadCount();
        }
    }
    
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        $this->updateUnreadCount();
    }
    
    private function updateUnreadCount()
    {
        if (Auth::check()) {
            $this->unreadCount = Auth::user()->unreadNotifications()->count();
        }
    }
    
    public function render()
    {
        $notifications = Auth::check() 
            ? Auth::user()->notifications()->latest()->limit(10)->get()
            : collect();
            
        return view('livewire.notification-dropdown', [
            'notifications' => $notifications
        ]);
    }
}
