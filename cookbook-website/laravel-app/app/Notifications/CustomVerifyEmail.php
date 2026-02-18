<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends BaseVerifyEmail
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Welcome to Ableton Cookbook - Verify Your Email')
            ->greeting('Welcome to Ableton Cookbook!')
            ->line('Thank you for joining our creative community of Ableton Live enthusiasts.')
            ->line('To get started and access all features, please verify your email address by clicking the button below:')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 60 minutes for your security.')
            ->line('If you did not create an account with Ableton Cookbook, you can safely ignore this email - no account was created.')
            ->line('---')
            ->line('**About Ableton Cookbook**: We\'re a community-driven platform for sharing Ableton Live racks, techniques, and creative resources.')
            ->line('Questions? Contact us at admin@ableton.recipes')
            ->salutation('Best regards,')
            ->salutation('The Ableton Cookbook Team')
            ->salutation('https://ableton.recipes');
    }
}
