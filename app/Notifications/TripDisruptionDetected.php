<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Notifications\Concerns\SendsTemplatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once when FlightAware reports a qualifying disruption (long delay or
 * cancellation) on a protected trip. Tells the user their trip may be
 * eligible for compensation - the Eligibility Engine reviews it later.
 */
class TripDisruptionDetected extends Notification implements ShouldQueue
{
    use Queueable, SendsTemplatedMail;

    /** @param array{type: string, delay_minutes?: int} $disruption */
    public function __construct(public Trip $trip, public array $disruption)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): mixed
    {
        $ident = $this->trip->flightIdent() ?: 'your flight';
        $route = trim(($this->trip->departure_airport ?: '') . ' → ' . ($this->trip->arrival_airport ?: ''), ' →');

        $verb = match ($this->disruption['type']) {
            'cancellation' => 'cancelled',
            'diversion'    => 'diverted',
            default        => 'delayed',
        };

        return $this->templatedMail($notifiable, 'trip-disruption-detected', [
            '[NAME]'       => $notifiable->name ?? 'there',
            '[FLIGHT]'     => $ident,
            '[DISRUPTION]' => $verb,
            '[HEADLINE]'   => $this->headline(),
            '[ROUTE]'      => $route,
            '[DATE]'       => $this->trip->departure_date?->format('d M Y') ?? '',
            '[TRIP_URL]'   => url('/flight-disputes/trips/' . $this->trip->id),
        ], (new MailMessage)
            ->subject("Your monitored trip {$ident} was {$verb}")
            ->greeting('Trip Protection alert')
            ->line($this->headline())
            ->line($route ? "Route: {$route}" . ($this->trip->departure_date ? ', ' . $this->trip->departure_date->format('d M Y') : '') : '')
            ->line("We're reviewing your eligibility for compensation - no action is needed from you right now.")
            ->action('View your trip', url('/flight-disputes/trips/' . $this->trip->id))
            ->line('Thank you for protecting your trip with Unjamm.'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'trip_id'       => $this->trip->id,
            'flight'        => $this->trip->flightIdent(),
            'type'          => $this->disruption['type'],
            'delay_minutes' => $this->disruption['delay_minutes'] ?? null,
            'message'       => $this->headline(),
        ];
    }

    private function headline(): string
    {
        $ident = $this->trip->flightIdent() ?: 'Your flight';

        if ($this->disruption['type'] === 'cancellation') {
            return "Your monitored trip {$ident} was cancelled. We're reviewing your eligibility for compensation.";
        }

        if ($this->disruption['type'] === 'diversion') {
            return "Your monitored trip {$ident} was diverted to a different airport. We're reviewing your eligibility for compensation.";
        }

        $minutes = (int) ($this->disruption['delay_minutes'] ?? 0);
        $human   = $minutes >= 60
            ? round($minutes / 60, $minutes % 60 === 0 ? 0 : 1) . ' hours'
            : "{$minutes} minutes";

        return "Your monitored trip {$ident} was delayed by {$human}. We're reviewing your eligibility for compensation.";
    }
}
