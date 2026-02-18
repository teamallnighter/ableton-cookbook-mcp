<?php

namespace App\Services;

use App\Models\JobNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comprehensive notification service for job status updates
 * 
 * This service handles all types of job-related notifications including
 * progress updates, completion notifications, failure alerts, and retry notifications.
 */
class JobNotificationService
{
    /**
     * Notification channels and their priorities
     */
    private const CHANNELS = [
        'browser' => 1,      // Real-time browser notifications
        'email' => 2,        // Email notifications
        'webhook' => 3,      // Webhook notifications
        'sms' => 4,          // SMS notifications (premium)
    ];
    
    /**
     * Default notification preferences by notification type
     */
    private const DEFAULT_PREFERENCES = [
        'progress' => ['browser'],
        'completion' => ['browser', 'email'],
        'failure' => ['browser', 'email'],
        'retry' => ['browser'],
        'escalation' => ['email'],
    ];
    
    /**
     * Send progress notification
     */
    public function sendProgressNotification(
        int $userId,
        string $jobId,
        string $title,
        string $message,
        array $data = []
    ): void {
        $this->createAndSendNotification(
            $userId,
            $jobId,
            'progress',
            $title,
            $message,
            $data
        );
    }
    
    /**
     * Send completion notification
     */
    public function sendCompletionNotification(
        int $userId,
        string $jobId,
        string $title,
        string $message,
        array $data = []
    ): void {
        $this->createAndSendNotification(
            $userId,
            $jobId,
            'completion',
            $title,
            $message,
            $data
        );
    }
    
    /**
     * Send failure notification
     */
    public function sendFailureNotification(
        int $userId,
        string $jobId,
        string $title,
        string $message,
        array $data = []
    ): void {
        $this->createAndSendNotification(
            $userId,
            $jobId,
            'failure',
            $title,
            $message,
            array_merge($data, ['severity' => 'high'])
        );
    }
    
    /**
     * Send retry notification
     */
    public function sendRetryNotification(
        int $userId,
        string $jobId,
        string $title,
        string $message,
        array $data = []
    ): void {
        $this->createAndSendNotification(
            $userId,
            $jobId,
            'retry',
            $title,
            $message,
            $data
        );
    }
    
    /**
     * Send escalation notification (to support team)
     */
    public function sendEscalationNotification(
        string $jobId,
        string $title,
        string $message,
        array $data = []
    ): void {
        // Send to support team instead of individual user
        $supportTeamUsers = $this->getSupportTeamUsers();
        
        foreach ($supportTeamUsers as $supportUser) {
            $this->createAndSendNotification(
                $supportUser->id,
                $jobId,
                'escalation',
                $title,
                $message,
                array_merge($data, [
                    'is_escalation' => true,
                    'severity' => 'critical'
                ])
            );
        }
    }
    
    /**
     * Send batch notifications for multiple jobs
     */
    public function sendBatchNotifications(
        int $userId,
        array $notifications
    ): void {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('User not found for batch notifications', ['user_id' => $userId]);
                return;
            }
            
            $userPreferences = $this->getUserNotificationPreferences($user);
            
            foreach ($notifications as $notification) {
                $this->createAndSendNotification(
                    $userId,
                    $notification['job_id'],
                    $notification['type'],
                    $notification['title'],
                    $notification['message'],
                    $notification['data'] ?? [],
                    $userPreferences
                );
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send batch notifications', [
                'user_id' => $userId,
                'notification_count' => count($notifications),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get user's notification history
     */
    public function getUserNotificationHistory(
        int $userId,
        int $limit = 50,
        array $types = []
    ): array {
        $query = JobNotification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);
            
        if (!empty($types)) {
            $query->whereIn('type', $types);
        }
        
        $notifications = $query->get();
        
        return $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'job_id' => $notification->job_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'status' => $notification->status,
                'channel' => $notification->channel,
                'sent_at' => $notification->sent_at,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        })->toArray();
    }
    
    /**
     * Mark notifications as read
     */
    public function markAsRead(int $userId, array $notificationIds): int
    {
        return JobNotification::where('user_id', $userId)
            ->whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return JobNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int
    {
        return JobNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $olderThanDays = 30): int
    {
        $cutoffDate = now()->subDays($olderThanDays);
        
        $deletedCount = JobNotification::where('created_at', '<', $cutoffDate)
            ->where('status', 'sent')
            ->delete();
            
        Log::info('Cleaned up old job notifications', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate
        ]);
        
        return $deletedCount;
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(): array
    {
        $now = now();
        $dayAgo = $now->copy()->subDay();
        $weekAgo = $now->copy()->subWeek();
        
        return [
            'total_sent_today' => JobNotification::where('sent_at', '>=', $dayAgo)
                ->where('status', 'sent')
                ->count(),
            'total_failed_today' => JobNotification::where('created_at', '>=', $dayAgo)
                ->where('status', 'failed')
                ->count(),
            'unread_notifications' => JobNotification::whereNull('read_at')
                ->where('created_at', '>=', $weekAgo)
                ->count(),
            'by_type_last_7_days' => $this->getNotificationsByType($weekAgo),
            'by_channel_last_7_days' => $this->getNotificationsByChannel($weekAgo),
            'delivery_success_rate' => $this->getDeliverySuccessRate($weekAgo),
        ];
    }
    
    /**
     * Update user notification preferences
     */
    public function updateUserPreferences(int $userId, array $preferences): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return false;
            }
            
            // Validate preferences format
            $validatedPreferences = $this->validateNotificationPreferences($preferences);
            
            // Store preferences (assuming user has a notification_preferences JSON column)
            $user->update([
                'notification_preferences' => $validatedPreferences
            ]);
            
            Log::info('User notification preferences updated', [
                'user_id' => $userId,
                'preferences' => $validatedPreferences
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to update user notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Create and send notification through appropriate channels
     */
    private function createAndSendNotification(
        int $userId,
        string $jobId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?array $userPreferences = null
    ): void {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('User not found for notification', [
                    'user_id' => $userId,
                    'job_id' => $jobId
                ]);
                return;
            }
            
            if (!$userPreferences) {
                $userPreferences = $this->getUserNotificationPreferences($user);
            }
            
            $channels = $this->getChannelsForNotification($type, $userPreferences);
            
            foreach ($channels as $channel) {
                $notification = JobNotification::create([
                    'job_id' => $jobId,
                    'user_id' => $userId,
                    'type' => $type,
                    'channel' => $channel,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                    'status' => 'pending',
                    'scheduled_for' => now(),
                ]);
                
                $this->sendNotificationThroughChannel($notification, $user);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to create and send notification', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send notification through specific channel
     */
    private function sendNotificationThroughChannel(JobNotification $notification, User $user): void
    {
        try {
            switch ($notification->channel) {
                case 'browser':
                    $this->sendBrowserNotification($notification, $user);
                    break;
                    
                case 'email':
                    $this->sendEmailNotification($notification, $user);
                    break;
                    
                case 'webhook':
                    $this->sendWebhookNotification($notification, $user);
                    break;
                    
                case 'sms':
                    $this->sendSmsNotification($notification, $user);
                    break;
                    
                default:
                    throw new \Exception("Unsupported notification channel: {$notification->channel}");
            }
            
            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);
            
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'data' => array_merge($notification->data ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ])
            ]);
            
            Log::error('Notification delivery failed', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send browser notification via real-time broadcasting
     */
    private function sendBrowserNotification(JobNotification $notification, User $user): void
    {
        Event::dispatch('notification.browser', [
            'user_id' => $user->id,
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification(JobNotification $notification, User $user): void
    {
        // Implementation would depend on your mail system
        // This is a simplified example
        
        $mailData = [
            'user' => $user,
            'notification' => $notification,
            'job_id' => $notification->job_id,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
        ];
        
        $mailTemplate = $this->getEmailTemplate($notification->type);
        
        Mail::send($mailTemplate, $mailData, function ($message) use ($user, $notification) {
            $message->to($user->email, $user->name)
                ->subject($notification->title);
        });
    }
    
    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(JobNotification $notification, User $user): void
    {
        $webhookUrl = $user->webhook_url ?? null;
        
        if (!$webhookUrl) {
            throw new \Exception('User does not have webhook URL configured');
        }
        
        $payload = [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'job_id' => $notification->job_id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'timestamp' => now()->toISOString(),
        ];
        
        // Send HTTP POST request to webhook URL
        // Implementation would depend on your HTTP client preference
    }
    
    /**
     * Send SMS notification
     */
    private function sendSmsNotification(JobNotification $notification, User $user): void
    {
        $phoneNumber = $user->phone_number ?? null;
        
        if (!$phoneNumber) {
            throw new \Exception('User does not have phone number configured');
        }
        
        // Implementation would depend on your SMS provider (Twilio, etc.)
        $smsMessage = $this->formatSmsMessage($notification);
        
        // Send SMS via your provider
    }
    
    /**
     * Get user's notification preferences
     */
    private function getUserNotificationPreferences(User $user): array
    {
        $preferences = $user->notification_preferences ?? [];
        
        // Merge with defaults
        foreach (self::DEFAULT_PREFERENCES as $type => $channels) {
            if (!isset($preferences[$type])) {
                $preferences[$type] = $channels;
            }
        }
        
        return $preferences;
    }
    
    /**
     * Get channels for specific notification type
     */
    private function getChannelsForNotification(string $type, array $userPreferences): array
    {
        return $userPreferences[$type] ?? self::DEFAULT_PREFERENCES[$type] ?? ['browser'];
    }
    
    /**
     * Get support team users
     */
    private function getSupportTeamUsers(): array
    {
        // This would return users with support team role
        // Implementation depends on your user/role system
        return User::where('role', 'support')->get()->toArray();
    }
    
    /**
     * Validate notification preferences format
     */
    private function validateNotificationPreferences(array $preferences): array
    {
        $validated = [];
        $validChannels = array_keys(self::CHANNELS);
        $validTypes = array_keys(self::DEFAULT_PREFERENCES);
        
        foreach ($preferences as $type => $channels) {
            if (!in_array($type, $validTypes)) {
                continue;
            }
            
            $validatedChannels = array_filter($channels, function ($channel) use ($validChannels) {
                return in_array($channel, $validChannels);
            });
            
            if (!empty($validatedChannels)) {
                $validated[$type] = array_values($validatedChannels);
            }
        }
        
        return $validated;
    }
    
    /**
     * Get email template for notification type
     */
    private function getEmailTemplate(string $type): string
    {
        return match($type) {
            'completion' => 'emails.job-completion',
            'failure' => 'emails.job-failure',
            'retry' => 'emails.job-retry',
            'escalation' => 'emails.job-escalation',
            default => 'emails.job-notification',
        };
    }
    
    /**
     * Format SMS message
     */
    private function formatSmsMessage(JobNotification $notification): string
    {
        $message = $notification->title;
        
        if (strlen($notification->message) + strlen($message) < 140) {
            $message .= ': ' . $notification->message;
        }
        
        return substr($message, 0, 160); // SMS character limit
    }
    
    /**
     * Get notifications by type for statistics
     */
    private function getNotificationsByType(Carbon $since): array
    {
        return JobNotification::where('created_at', '>=', $since)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }
    
    /**
     * Get notifications by channel for statistics
     */
    private function getNotificationsByChannel(Carbon $since): array
    {
        return JobNotification::where('created_at', '>=', $since)
            ->selectRaw('channel, COUNT(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();
    }
    
    /**
     * Get delivery success rate
     */
    private function getDeliverySuccessRate(Carbon $since): float
    {
        $total = JobNotification::where('created_at', '>=', $since)->count();
        
        if ($total === 0) {
            return 0.0;
        }
        
        $successful = JobNotification::where('created_at', '>=', $since)
            ->where('status', 'sent')
            ->count();
            
        return ($successful / $total) * 100;
    }
}