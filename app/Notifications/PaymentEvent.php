<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\SendsTemplatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification class for every customer-facing payment event - the
 * template slug decides the email copy (admin-editable, localisable via the
 * Templates module), and the database channel feeds the in-app bell.
 */
class PaymentEvent extends Notification implements ShouldQueue
{
    use Queueable, SendsTemplatedMail;

    public function __construct(
        private readonly Payment $payment,
        private readonly string $title,
        private readonly string $description,
        private readonly ?string $template = null,
        private readonly array $extra = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->template ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): mixed
    {
        $fallback = (new MailMessage())
            ->subject($this->title)
            ->line($this->description)
            ->action('View your claim', $this->claimUrl());

        return $this->templatedMail($notifiable, $this->template, $this->replacements($notifiable), $fallback);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'        => 'payment',
            'title'       => $this->title,
            'description' => $this->description,
            'claim_id'    => $this->payment->claim_id,
            'payment_id'  => $this->payment->id,
            'claim_url'   => $this->claimUrl(),
        ];
    }

    private function replacements(object $notifiable): array
    {
        $payment = $this->payment;

        return [
            '[NAME]'        => $payment->claim?->passenger_name ?: $notifiable->name,
            '[CLAIM]'       => '#' . ($payment->claim?->number ?? $payment->claim_id),
            '[GROSS]'       => $payment->money($payment->gross_amount),
            '[FEE]'         => $payment->money($payment->fee_amount),
            '[FEE_PERCENT]' => rtrim(rtrim(number_format((float) $payment->fee_percent, 2), '0'), '.'),
            '[NET]'         => $payment->money($payment->net_amount),
            '[REFERENCE]'   => $this->extra['reference'] ?? ($payment->reference ?: '-'),
            '[CLAIM_URL]'   => $this->claimUrl(),
        ];
    }

    private function claimUrl(): string
    {
        return url('/flight-disputes/claims/' . encrypt_id($this->payment->claim_id));
    }
}
