<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public Newsletter $newsletter;
    public User $user;
    public string $unsubscribeUrl;

    public function __construct(Newsletter $newsletter, User $user)
    {
        $this->newsletter = $newsletter;
        $this->user = $user;
        $this->unsubscribeUrl = route('unsubscribe', [
            'token' => $this->generateUnsubscribeToken($user)
        ]);
    }

    public function build()
    {
        $content = $this->personalizeContent($this->newsletter->content);

        return $this->subject($this->newsletter->subject)
                    ->view('emails.newsletter')
                    ->with([
                        'newsletter' => $this->newsletter,
                        'user' => $this->user,
                        'content' => $content,
                        'unsubscribeUrl' => $this->unsubscribeUrl
                    ]);
    }

    private function personalizeContent(string $content): string
    {
        $replacements = [
            '{name}' => $this->user->name,
            '{username}' => $this->user->username ?? $this->user->name,
            '{email}' => $this->user->email,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function generateUnsubscribeToken(User $user): string
    {
        return base64_encode($user->id . '|' . hash('sha256', $user->email . config('app.key')));
    }
}