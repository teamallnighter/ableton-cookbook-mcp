<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Auth\Notifications\VerifyEmail;

class ShowVerificationEmail extends Command
{
    protected $signature = 'email:show-verification';
    protected $description = 'Show what the email verification email looks like';

    public function handle()
    {
        // Create a temporary user for demonstration
        $user = new User([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'id' => 999
        ]);

        $notification = new VerifyEmail();
        $message = $notification->toMail($user);

        $this->info("=== EMAIL VERIFICATION MESSAGE ===");
        $this->info("Subject: " . $message->subject);
        $this->info("Greeting: " . ($message->greeting ?? 'Hello!'));
        
        if (!empty($message->introLines)) {
            $this->info("\nIntro Lines:");
            foreach ($message->introLines as $line) {
                $this->line("  - " . $line);
            }
        }

        if (!empty($message->actionText) && !empty($message->actionUrl)) {
            $this->info("\nAction Button:");
            $this->info("  Text: " . $message->actionText);
            $this->info("  URL: " . $message->actionUrl);
        }

        if (!empty($message->outroLines)) {
            $this->info("\nOutro Lines:");
            foreach ($message->outroLines as $line) {
                $this->line("  - " . $line);
            }
        }

        $this->info("\nSalutation: " . ($message->salutation ?? 'Regards,'));

        return 0;
    }
}
