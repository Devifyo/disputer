<?php

namespace App\Mail;

use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A claim email to the airline. Sent from the public claims address, with a
 * per-claim reply-to token so the airline's answer routes straight back to
 * the claim it belongs to.
 */
class AirlineClaimMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array{name: string, path: string}> $files */
    public function __construct(
        public Claim $claim,
        public string $subjectLine,
        public string $bodyText,
        public array $files = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('services.inbound.claims_display'), 'Unjamm Claims'),
            replyTo: [new Address($this->claim->replyAddress(), 'Unjamm Claims')],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.airline-claim',
            with: ['bodyText' => $this->bodyText],
        );
    }

    public function attachments(): array
    {
        return collect($this->files)
            ->map(fn (array $file) => Attachment::fromStorageDisk('local', $file['path'])->as($file['name']))
            ->all();
    }
}
