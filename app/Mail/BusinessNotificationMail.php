<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $recipientName,
        public readonly string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.communication');
    }
}
