<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\IssueStatusUpdate;
use App\Mail\NewIssueConfirmation;
use App\Mail\AdminNewIssueNotification;

class NotificationService
{
    public function sendIssueStatusUpdate($issueId, $email, $oldStatus, $newStatus, $comment = null)
    {
        try {
            Mail::to($email)->send(new IssueStatusUpdate($issueId, $oldStatus, $newStatus, $comment));
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send issue status update email', [
                'issue_id' => $issueId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendNewIssueConfirmation($issueId, $email)
    {
        try {
            Mail::to($email)->send(new NewIssueConfirmation($issueId));
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send issue confirmation email', [
                'issue_id' => $issueId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function notifyAdminNewIssue($issueId, $title, $issueType)
    {
        $adminEmail = config('mail.admin_email', config('mail.from.address'));
        
        try {
            Mail::to($adminEmail)->send(new AdminNewIssueNotification($issueId, $title, $issueType));
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification email', [
                'issue_id' => $issueId,
                'admin_email' => $adminEmail,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
