<?php

namespace App\Mail;

use App\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSupportMessage extends Mailable
{
    use Queueable, SerializesModels;

    public $supportMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(SupportMessage $supportMessage)
    {
        $this->supportMessage = $supportMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Support Request: ' . $this->supportMessage->name,
            // This allows you to hit "Reply" and email the user directly!
            replyTo: [
                new Address($this->supportMessage->email, $this->supportMessage->name),
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.support.new-message',
        );
    }
}