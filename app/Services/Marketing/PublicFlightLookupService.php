<?php

namespace App\Services\Marketing;

use App\Models\Airline;
use App\Services\Eligibility\CompensationCalculator;
use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityEngine;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\Evaluators\RuleBasedEligibilityEvaluator;
use App\Services\FlightAwareService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public "check your flight" lookup for visitors who have not signed up.
 *
 * Deliberately NOT the full claim pipeline: it runs the deterministic rule
 * evaluator only - no AI call, no database writes, no account - so it is
 * cheap enough to expose anonymously. Every answer is provisional and says
 * so; the real verdict comes from the Eligibility Engine once the visitor
 * creates a claim.
 *
 * Every distinct flight+date is cached, so repeat lookups (and bots that get
 * past the throttle) cost nothing at FlightAware.
 */
class PublicFlightLookupService
{
    private const TTL       = 1800;  // 30 min - a live flight's status can move
    private const TTL_EMPTY = 900;   // don't re-query unknown flights as often

    public function __construct(
        private FlightAwareService $flightAware,
        private EligibilityEngine $engine,
        private CompensationCalculator $calculator,
    ) {
    }

    /**
     * @return array{found: bool, status: string, headline: string, detail: string,
     *               flight: ?array, eligible: ?bool, estimate: ?string, cta: string}
     */
    public function lookup(string $ident, string $date): array
    {
        $ident = strtoupper(preg_replace('/\s+/', '', $ident));
        $key   = 'flight-lookup:' . Str::slug("{$ident}-{$date}");

        try {
            $flightDate = Carbon::parse($date)->setTime(12, 0);
        } catch (Throwable) {
            return $this->notFound('That date did not look right - try again with the departure date.');
        }

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->build($ident, $flightDate);
        } catch (Throwable $e) {
            Log::warning('Public flight lookup failed', ['ident' => $ident, 'error' => $e->getMessage()]);

            // Never dead-end a visitor on our own failure.
            return $this->unknown($ident, $flightDate);
        }

        Cache::put($key, $result, $result['found'] ? self::TTL : self::TTL_EMPTY);

        return $result;
    }

    private function build(string $ident, Carbon $flightDate): array
    {
        $lookup = $this->flightAware->findFlight($ident, $flightDate);

        if (!($lookup['ok'] ?? false) || empty($lookup['data'])) {
            return $this->unknown($ident, $flightDate);
        }

        $data      = $lookup['data'];
        $cancelled = (bool) ($data['cancelled'] ?? false);
        $delay     = (int) round(((int) ($data['arrival_delay'] ?? 0)) / 60);
        $origin    = $data['origin']['code_iata'] ?? $data['origin']['code'] ?? null;
        $dest      = $data['destination']['code_iata'] ?? $data['destination']['code'] ?? null;

        $flight = [
            'ident'       => $data['ident_iata'] ?? $ident,
            'airline'     => $this->airlineName($data),
            'origin'      => $origin,
            'destination' => $dest,
            'date'        => $flightDate->format('d M Y'),
            'status'      => $cancelled ? 'Cancelled' : ($delay >= 15 ? $this->delayLabel($delay) : 'On time'),
            'status_text' => $cancelled ? 'Cancelled' : ($data['status'] ?? null),
            'delay_min'   => max(0, $delay),
            'cancelled'   => $cancelled,
            // A cancelled flight never departed or arrived: its "actual"
            // times and progress are leftovers from the schedule, so they
            // are suppressed rather than shown as an on-time journey.
            'progress'    => $cancelled ? 0 : (int) ($data['progress_percent'] ?? 0),
            'from'        => $this->endpoint($data['origin'] ?? [], $data, 'out', $cancelled),
            'to'          => $this->endpoint($data['destination'] ?? [], $data, 'in', $cancelled),
        ];

        // Nothing went wrong - be honest rather than manufacturing a claim.
        if (!$cancelled && $delay < 180) {
            return [
                'found'    => true,
                'status'   => 'not_disrupted',
                'headline' => $delay >= 15 ? 'This flight was delayed, but not by enough.' : 'Good news - this flight ran on time.',
                'detail'   => $delay >= 15
                    ? sprintf('A %s delay is below the 3-hour threshold where compensation normally starts. If you were rebooked and arrived much later than this, start a claim and we will check the full picture.', $this->delayLabel($delay))
                    : 'We could not find a delay or cancellation on this flight. If your journey was still disrupted - a missed connection, denied boarding or a downgrade - start a claim and we will look properly.',
                'flight'   => $flight,
                'eligible' => false,
                'estimate' => null,
                'cta'      => 'Something still went wrong? Start a claim',
            ];
        }

        [$eligible, $estimate] = $this->provisionalVerdict($flightDate, $origin, $dest, $cancelled, $delay);

        return [
            'found'    => true,
            'status'   => $eligible ? 'eligible' : 'disrupted',
            'headline' => $eligible
                ? ($estimate ? "Your flight may be eligible for up to {$estimate}." : 'Your flight may be eligible for compensation.')
                : 'This flight was disrupted - it is worth checking.',
            'detail'   => $cancelled
                ? 'The airline cancelled this flight. Cancellations within the airline\'s control usually carry compensation on top of a refund or rebooking.'
                : sprintf('This flight arrived %s late. Delays of 3 hours or more usually carry compensation when the cause was within the airline\'s control.', $this->delayLabel($delay)),
            'flight'   => $flight,
            'eligible' => $eligible,
            'estimate' => $estimate,
            'cta'      => 'Create a free account to continue your claim',
        ];
    }

    /**
     * One end of the journey, in the airport's own local time - the way a
     * passenger reads a boarding pass.
     *
     * @return array{code: ?string, city: ?string, airport: ?string, scheduled: ?string,
     *               actual: ?string, timezone: ?string, delta: ?string, late: bool}
     */
    private function endpoint(array $airport, array $data, string $leg, bool $cancelled = false): array
    {
        $timezone  = $airport['timezone'] ?? 'UTC';
        $scheduled = $data["scheduled_{$leg}"] ?? null;
        $actual    = $cancelled ? null : ($data["actual_{$leg}"] ?? $data["estimated_{$leg}"] ?? null);

        $local = function (?string $iso) use ($timezone): ?string {
            if (!$iso) {
                return null;
            }

            try {
                return Carbon::parse($iso)->setTimezone($timezone)->format('H:i');
            } catch (Throwable) {
                return null;
            }
        };

        $minutes = $cancelled
            ? 0
            : (int) round(((int) ($data[$leg === 'out' ? 'departure_delay' : 'arrival_delay'] ?? 0)) / 60);

        return [
            'code'      => $airport['code_iata'] ?? $airport['code'] ?? null,
            'city'      => $airport['city'] ?? null,
            'airport'   => $airport['name'] ?? null,
            'timezone'  => $this->timezoneAbbreviation($timezone, $scheduled),
            'scheduled' => $local($scheduled),
            'actual'    => $local($actual),
            'delta'     => $minutes === 0 ? null : $this->delayLabel(abs($minutes)) . ($minutes > 0 ? ' late' : ' early'),
            'late'      => $minutes > 0,
        ];
    }

    /** "IST", "BST" - the label a passenger recognises on their ticket. */
    private function timezoneAbbreviation(string $timezone, ?string $reference): ?string
    {
        try {
            return Carbon::parse($reference ?: 'now')->setTimezone($timezone)->format('T');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Carrier name: our own directory first, then FlightAware's operator
     * record (cached forever - airline names do not change), then nothing,
     * so the card shows the flight number alone rather than "AI flight".
     */
    private function airlineName(array $data): ?string
    {
        $iata = $data['operator_iata'] ?? null;

        if ($iata && ($name = Airline::where('iata_code', $iata)->value('name'))) {
            return $name;
        }

        $icao = $data['operator_icao'] ?? $data['operator'] ?? null;

        if (!$icao) {
            return null;
        }

        return Cache::rememberForever("flight-operator:{$icao}", function () use ($icao) {
            try {
                return $this->flightAware->operatorName($icao);
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * Provisional verdict from the deterministic rules only - no AI, no
     * stored decision. Returns [eligible, "EUR 600" | null].
     *
     * @return array{0: bool, 1: ?string}
     */
    private function provisionalVerdict(Carbon $date, ?string $origin, ?string $dest, bool $cancelled, int $delay): array
    {
        if (!$origin || !$dest) {
            return [true, null];
        }

        $context = new EligibilityContext(
            ref:                 'public-lookup',
            airline:             null,
            flightNumber:        null,
            flightDate:          $date,
            departureAirport:    $origin,
            arrivalAirport:      $dest,
            originCountry:       $this->engine->countryOf($origin),
            destinationCountry:  $this->engine->countryOf($dest),
            cancelled:           $cancelled,
            arrivalDelayMinutes: max(0, $delay),
            delayIsActual:       true,
        );

        $outcomes = collect(app(RuleBasedEligibilityEvaluator::class)->evaluate($context))
            ->filter(fn (EligibilityResult $r) => $r->eligible)
            ->sortByDesc(fn (EligibilityResult $r) => $r->confidence);

        $best = $outcomes->first();

        if (!$best) {
            return [false, null];
        }

        $compensation = $this->calculator->calculate($best, $context, null, null);
        $amount       = $compensation['amount'] ?? null;

        return [
            true,
            $amount ? trim(($compensation['currency'] ?? '') . ' ' . number_format((float) $amount, 0)) : null,
        ];
    }

    /** Flight not in the tracking window - still a conversion opportunity. */
    private function unknown(string $ident, Carbon $date): array
    {
        return [
            'found'    => false,
            'status'   => 'not_found',
            'headline' => 'We could not find that flight automatically.',
            'detail'   => sprintf(
                'Live tracking only reaches back about 10 days, and %s on %s is outside that window or was not found. That does not mean you have no claim - most claims are older than 10 days. Start one and our team will assess it from your ticket.',
                $ident, $date->format('d M Y')
            ),
            'flight'   => null,
            'eligible' => null,
            'estimate' => null,
            'cta'      => 'Check my claim anyway - it is free',
        ];
    }

    private function notFound(string $detail): array
    {
        return [
            'found'    => false,
            'status'   => 'invalid',
            'headline' => 'We need a little more to go on.',
            'detail'   => $detail,
            'flight'   => null,
            'eligible' => null,
            'estimate' => null,
            'cta'      => 'Start a claim instead',
        ];
    }

    private function delayLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        return $hours > 0
            ? trim("{$hours}h " . ($mins ? "{$mins}m" : ''))
            : "{$mins}m";
    }
}
