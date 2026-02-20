<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $htmlBody;

    public function __construct($subjectLine, $htmlBody)
    {
        $this->subjectLine = $subjectLine;
        $this->htmlBody = $htmlBody;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic',
            with: [
                'htmlBody' => $this->htmlBody,
            ],
        );
    }
}