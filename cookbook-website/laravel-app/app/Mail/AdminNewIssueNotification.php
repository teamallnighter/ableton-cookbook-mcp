<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewIssueNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $issueId;
    public $title;
    public $issueType;

    public function __construct($issueId, $title, $issueType)
    {
        $this->issueId = $issueId;
        $this->title = $title;
        $this->issueType = $issueType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Issue Submitted #{$this->issueId} - {$this->issueType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-issue-notification',
        );
    }
}
