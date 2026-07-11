<?php

namespace App\Notifications;

use App\Models\Trip;
use App\Notifications\Concerns\SendsTemplatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when our team reviews a trip and concludes it doesn't qualify for
 * compensation - always carries the human-written reason.
 */
class TripEligibilityRejected extends Notification implements ShouldQueue
{
    use Queueable, SendsTemplatedMail;

    public function __construct(public Trip $trip, public string $reason)
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

        return $this->templatedMail($notifiable, 'trip-eligibility-rejected', [
            '[NAME]'     => $notifiable->name ?? 'there',
            '[FLIGHT]'   => $ident,
            '[ROUTE]'    => $route,
            '[DATE]'     => $this->trip->departure_date?->format('d M Y') ?? '',
            '[REASON]'   => $this->reason,
            '[TRIP_URL]' => url('/flight-disputes/trips/' . $this->trip->id),
        ], (new MailMessage)
            ->subject("Update on your trip {$ident} - review complete")
            ->greeting('Your review is complete')
            ->line("Our team has reviewed your case for {$ident}" . ($route ? " ({$route})" : '') . ' and unfortunately it doesn\'t qualify for compensation.')
            ->line("Reason: {$this->reason}")
            ->action('View your trip', url('/flight-disputes/trips/' . $this->trip->id))
            ->line('If you have new information or documents that change the picture, you can reach us any time through support.'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'trip_id' => $this->trip->id,
            'flight'  => $this->trip->flightIdent(),
            'reason'  => $this->reason,
            'message' => sprintf('Your trip %s was reviewed: it doesn\'t qualify for compensation. %s', $this->trip->flightIdent(), $this->reason),
        ];
    }
}
