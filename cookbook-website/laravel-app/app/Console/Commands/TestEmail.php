<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Exception;

class TestEmail extends Command
{
    protected $signature = 'email:test {email?}';
    protected $description = 'Send a test email';

    public function handle()
    {
        $email = $this->argument('email') ?: 'admin@ableton.recipes';
        
        try {
            Mail::raw('This is a test email from Ableton Cookbook!', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email from Ableton Cookbook');
            });
            
            $this->info("✅ Test email sent successfully to {$email}");
            return 0;
        } catch (Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            return 1;
        }
    }
}
