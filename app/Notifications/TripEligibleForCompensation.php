<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the Eligibility Engine confirms a monitored trip qualifies
 * for compensation under an air passenger rights regulation.
 */
class TripEligibleForCompensation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Trip $trip)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ident = $this->trip->flightIdent() ?: 'your flight';
        $route = trim(($this->trip->departure_airport ?: '') . ' → ' . ($this->trip->arrival_airport ?: ''), ' →');

        return (new MailMessage)
            ->subject("Good news - your trip {$ident} is eligible for compensation")
            ->greeting('You have a claim!')
            ->line($this->headline())
            ->line($route ? "Route: {$route}" . ($this->trip->departure_date ? ', ' . $this->trip->departure_date->format('d M Y') : '') : '')
            ->line("Legal basis: {$this->trip->eligibility_regulation} - {$this->trip->eligibility_article}.")
            ->action('View your trip', url('/flight-disputes/trips/' . $this->trip->id))
            ->line('We\'ll guide you through the next steps to claim what you\'re owed.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'trip_id'    => $this->trip->id,
            'flight'     => $this->trip->flightIdent(),
            'regulation' => $this->trip->eligibility_regulation,
            'article'    => $this->trip->eligibility_article,
            'confidence' => $this->trip->eligibility_confidence,
            'message'    => $this->headline(),
        ];
    }

    private function headline(): string
    {
        return sprintf(
            'Your monitored trip %s is eligible for compensation under %s (%s).',
            $this->trip->flightIdent() ?: 'Your flight',
            $this->trip->eligibility_regulation,
            $this->trip->eligibility_article
        );
    }
}
