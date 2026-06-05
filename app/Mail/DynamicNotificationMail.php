<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $dynamicSubject;
    public string $dynamicContent;

    public function __construct(string $subject, string $content)
    {
        $this->dynamicSubject = $subject;
        $this->dynamicContent = $content;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dynamicSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->dynamicContent,
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
