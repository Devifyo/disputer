<?php

namespace App\Services\Claims;

use App\Models\AirlineEmailTemplate;
use App\Models\Claim;
use Illuminate\Support\Carbon;

/**
 * Turns a template into the letter that actually goes out: every
 * {{variable}} replaced with this claim's data.
 *
 * The values come from the claim and the Eligibility Engine's stored verdict
 * - the renderer never decides law or money, it only formats what those
 * already determined.
 */
class TemplateRenderer
{
    /** Every variable an admin may use, with a plain-language description. */
    public const VARIABLES = [
        'passenger_name'      => "The claim's lead passenger",
        'airline_name'        => 'Airline the claim is against',
        'flight_number'       => 'Flight number, e.g. AC1540',
        'booking_reference'   => 'Booking reference / PNR',
        'departure_airport'   => 'Departure airport code',
        'arrival_airport'     => 'Arrival airport code',
        'scheduled_departure' => 'Scheduled departure (date and time)',
        'actual_departure'    => 'Actual departure, when known',
        'scheduled_arrival'   => 'Scheduled arrival (date and time)',
        'actual_arrival'      => 'Actual arrival, when known',
        'delay_duration'      => 'Arrival delay in hours and minutes',
        'claim_reference'     => 'Unjamm claim reference',
        'compensation_amount' => 'Amount claimed, formatted',
        'currency'            => 'Currency of the compensation',
        'regulation'          => 'Regulation the claim rests on (APPR, EU261…)',
        'article'             => 'Article or section relied on',
        'today_date'          => "Today's date",
    ];

    /** @return array<string, string> variable => value for this claim */
    public function values(Claim $claim): array
    {
        $claim->loadMissing('itinerary');

        $amount   = (float) $claim->compensation_amount;
        $paxCount = max(1, count($claim->passengerNames()));

        // Verified flight times come from the tracking snapshot; the claim's
        // own flight date is the fallback when the flight predates tracking.
        $flight = is_array($claim->flight_snapshot) ? $claim->flight_snapshot : [];

        return [
            'passenger_name'      => (string) ($claim->passenger_name ?: $claim->user?->name),
            'airline_name'        => (string) $claim->airline,
            'flight_number'       => (string) $claim->flight_number,
            'booking_reference'   => (string) ($claim->booking_reference ?: '-'),
            'departure_airport'   => (string) $claim->departure_airport,
            'arrival_airport'     => (string) $claim->arrival_airport,
            'scheduled_departure' => $this->moment($flight['scheduled_departure'] ?? $claim->flight_date),
            'actual_departure'    => $this->moment($flight['actual_departure'] ?? null),
            'scheduled_arrival'   => $this->moment($flight['scheduled_arrival'] ?? null),
            'actual_arrival'      => $this->moment($flight['actual_arrival'] ?? null),
            'delay_duration'      => $this->duration(
                $claim->flight_arrival_delay_minutes ?? $claim->reported_arrival_delay_minutes
            ),
            'claim_reference'     => (string) ($claim->reference ?: $claim->number),
            'compensation_amount' => $amount > 0
                ? trim(($claim->compensation_currency ?: '') . ' ' . number_format($amount * $paxCount, 2))
                : '-',
            'currency'            => (string) ($claim->compensation_currency ?: ''),
            'regulation'          => (string) ($claim->eligibility_regulation ?: '-'),
            'article'             => (string) ($claim->eligibility_article ?: '-'),
            'today_date'          => now()->format('d F Y'),
        ];
    }

    /** Replace every {{variable}} in a piece of text. */
    public function render(string $text, Claim $claim): string
    {
        $values = $this->values($claim);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function (array $match) use ($values) {
            $key = strtolower($match[1]);

            // An unknown placeholder is left visible rather than silently
            // blanked - an admin must see that it did not resolve.
            return $values[$key] ?? $match[0];
        }, $text);
    }

    /** @return array{subject: string, body: string} */
    public function renderTemplate(AirlineEmailTemplate $template, Claim $claim): array
    {
        return [
            'subject' => $this->render($template->subject, $claim),
            'body'    => $this->render($template->body, $claim),
        ];
    }

    /** Placeholders in the text that this renderer cannot fill. */
    public function unknownVariables(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($name) => strtolower($name))
            ->reject(fn ($name) => array_key_exists($name, self::VARIABLES))
            ->unique()->values()->all();
    }

    private function moment($value): string
    {
        if (!$value) {
            return '-';
        }

        $moment = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $moment->format($moment->format('H:i') === '00:00' ? 'd F Y' : 'd F Y, H:i');
    }

    private function duration(?int $minutes): string
    {
        if (!$minutes) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        return trim(($hours ? "{$hours}h " : '') . ($rest ? "{$rest}m" : '')) ?: '-';
    }
}
