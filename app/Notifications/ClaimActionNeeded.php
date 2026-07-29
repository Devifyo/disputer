<?php

namespace App\Notifications;

use App\Models\Claim;
use App\Notifications\Concerns\SendsTemplatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A nudge to the customer for the one thing only they can do - confirm the
 * claim, or sign the authorisation. Goes to their inbox AND the in-app bell,
 * because whichever they check first should tell them the same thing.
 */
class ClaimActionNeeded extends Notification implements ShouldQueue
{
    use Queueable, SendsTemplatedMail;

    public const ACTION_CONFIRM = 'confirm';
    public const ACTION_SIGN    = 'sign';

    public function __construct(
        private readonly Claim $claim,
        private readonly string $action,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function isConfirm(): bool
    {
        return $this->action === self::ACTION_CONFIRM;
    }

    private function title(): string
    {
        return $this->isConfirm()
            ? 'Confirm your claim to get it moving'
            : 'One signature left on your claim';
    }

    private function description(): string
    {
        $flight = trim(($this->claim->airline ?? '') . ' ' . ($this->claim->flight_number ?? ''));

        return $this->isConfirm()
            ? "Your {$flight} claim is assessed and ready - confirm the details so we can file it with the airline."
            : "Your {$flight} claim is ready to file as soon as the authorisation is signed.";
    }

    public function toMail(object $notifiable): mixed
    {
        $fallback = (new MailMessage())
            ->subject($this->title())
            ->line($this->description())
            ->action($this->isConfirm() ? 'Confirm my claim' : 'Sign my authorisation', $this->url());

        return $this->templatedMail(
            $notifiable,
            $this->isConfirm() ? 'claim-confirm-reminder' : 'claim-sign-reminder',
            [
                '[NAME]'      => $this->claim->passenger_name ?: $notifiable->name,
                '[FLIGHT]'    => trim(($this->claim->airline ?? '') . ' ' . ($this->claim->flight_number ?? '')),
                '[ROUTE]'     => "{$this->claim->departure_airport} - {$this->claim->arrival_airport}",
                '[AMOUNT]'    => $this->amount(),
                '[CLAIM]'     => '#' . $this->claim->number,
                '[CLAIM_URL]' => $this->url(),
            ],
            $fallback,
        );
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind'        => 'claim_action',
            'title'       => $this->title(),
            'description' => $this->description(),
            'claim_id'    => $this->claim->id,
            'claim_url'   => $this->url(),
        ];
    }

    private function amount(): string
    {
        $amount = (float) $this->claim->compensation_amount * max(1, count($this->claim->passengerNames()));

        return $amount > 0
            ? trim(($this->claim->compensation_currency ?: '') . ' ' . number_format($amount, 2))
            : 'your compensation';
    }

    private function url(): string
    {
        $base = url('/flight-disputes/claims/' . encrypt_id($this->claim->id));

        return $this->isConfirm() ? "{$base}/confirm" : "{$base}/sign";
    }
}
