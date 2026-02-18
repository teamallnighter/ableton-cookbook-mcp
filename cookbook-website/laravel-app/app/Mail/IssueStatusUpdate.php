<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $issueId;
    public $oldStatus;
    public $newStatus;
    public $comment;

    public function __construct($issueId, $oldStatus, $newStatus, $comment = null)
    {
        $this->issueId = $issueId;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->comment = $comment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Issue #{$this->issueId} Status Update - " . ucfirst(str_replace('_', ' ', $this->newStatus)),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.issue-status-update',
        );
    }
}
