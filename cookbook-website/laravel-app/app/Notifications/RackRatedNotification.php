<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Rack;
use App\Models\User;

class RackRatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $rack;
    public $rater;
    public $rating;

    public function __construct(Rack $rack, User $rater, $rating)
    {
        $this->rack = $rack;
        $this->rater = $rater;
        $this->rating = $rating;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Your rack "' . $this->rack->title . '" received a ' . $this->rating . '-star rating!')
                    ->greeting('Great news!')
                    ->line($this->rater->name . ' rated your rack "' . $this->rack->title . '" ' . $this->rating . ' stars.')
                    ->action('View Rack', route('racks.show', $this->rack))
                    ->line('Thank you for sharing your music with the community!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->rater->name . ' rated your rack "' . $this->rack->title . '" ' . $this->rating . ' stars',
            'rack_id' => $this->rack->id,
            'rack_title' => $this->rack->title,
            'rater_name' => $this->rater->name,
            'rating' => $this->rating,
        ];
    }
}