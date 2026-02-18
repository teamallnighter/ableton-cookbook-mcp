<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewIssueConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $issueId;

    public function __construct($issueId)
    {
        $this->issueId = $issueId;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Issue #{$this->issueId} Submitted Successfully - Ableton Cookbook",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-issue-confirmation',
        );
    }
}
