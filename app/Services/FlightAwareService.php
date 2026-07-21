<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin client for FlightAware AeroAPI v4 (https://aeroapi.flightaware.com).
 *
 * Every call returns a result array:
 *   ['ok' => bool, 'status' => ?int, 'data' => ?array, 'error' => ?string]
 * so callers (TripMonitoringService) can log the HTTP status and error
 * message into the monitoring history without try/catch noise.
 */
class FlightAwareService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.flightaware.api_key');
    }

    /**
     * Find the specific operation of a flight ident (e.g. "AC845") around a
     * given departure moment and return the raw AeroAPI flight payload —
     * including its fa_flight_id, FlightAware's canonical identifier.
     */
    public function findFlight(string $ident, ?Carbon $departureAround, ?string $originAirport = null): array
    {
        // AeroAPI /flights only tracks ~2 days ahead. Further out, match the
        // published schedule instead — it has no fa_flight_id yet, so the
        // T-24h checkpoint re-registers and binds the live identifier.
        if ($departureAround && $departureAround->gt(now()->addHours(40))) {
            return $this->findScheduled($ident, $departureAround, $originAirport);
        }

        $query = [];
        if ($departureAround) {
            // AeroAPI window filter; pad a day each side for timezone drift,
            // clamped to the 2-days-ahead bound /flights accepts.
            $query['start'] = $departureAround->copy()->subDay()->toDateString();
            $query['end']   = $departureAround->copy()->addDays(2)->min(now()->addDays(2))->toDateString();
        }

        $result = $this->get("/flights/{$ident}", $query);
        if (!$result['ok']) {
            return $result;
        }

        $flights = $result['data']['flights'] ?? [];
        $match   = $this->bestMatch($flights, $departureAround, $originAirport);

        if (!$match) {
            return ['ok' => false, 'status' => $result['status'], 'data' => null,
                    'error' => "No FlightAware flight matched {$ident} on " . ($departureAround?->toDateString() ?: 'unknown date')];
        }

        return ['ok' => true, 'status' => $result['status'], 'data' => $match, 'error' => null];
    }

    /** Fetch the latest data for a flight already registered by fa_flight_id. */
    public function getFlight(string $faFlightId): array
    {
        $result = $this->get('/flights/' . rawurlencode($faFlightId), ['ident_type' => 'fa_flight_id']);
        if (!$result['ok']) {
            return $result;
        }

        $flight = $result['data']['flights'][0] ?? null;
        if (!$flight) {
            return ['ok' => false, 'status' => $result['status'], 'data' => null, 'error' => 'FlightAware returned no data for this flight.'];
        }

        return ['ok' => true, 'status' => $result['status'], 'data' => $flight, 'error' => null];
    }

    /**
     * Historical reliability of this flight ident, computed from its recent
     * completed operations (AeroAPI returns roughly the last 11 days).
     * Informational only — shown as "route statistics" on the trip page.
     */
    public function historicalStats(string $ident): ?array
    {
        $result = $this->get("/flights/{$ident}");
        if (!$result['ok']) {
            return null;
        }

        $past = collect($result['data']['flights'] ?? [])
            ->filter(fn ($f) => !empty($f['actual_in']) && empty($f['cancelled']));

        $total = collect($result['data']['flights'] ?? [])
            ->filter(fn ($f) => !empty($f['actual_in']) || !empty($f['cancelled']));

        if ($past->isEmpty()) {
            return null;
        }

        $arrivalDelays   = $past->map(fn ($f) => max(0, (int) round(($f['arrival_delay'] ?? 0) / 60)));
        $departureDelays = $past->map(fn ($f) => max(0, (int) round(($f['departure_delay'] ?? 0) / 60)));
        $onTime          = $arrivalDelays->filter(fn ($m) => $m <= 15)->count();
        $cancelled       = $total->filter(fn ($f) => !empty($f['cancelled']))->count();

        $avgArrival   = (int) round($arrivalDelays->avg());
        $avgDeparture = (int) round($departureDelays->avg());
        $onTimePct    = (int) round($onTime / max(1, $past->count()) * 100);

        // 0 (very reliable) → 100 (very disrupted): blend of lateness and
        // how often the flight misses the 15-minute on-time window.
        $delayScore = (int) round(min(100, (100 - $onTimePct) * 0.6 + min(60, $avgArrival) / 60 * 100 * 0.4));

        return [
            'sample_size'             => $past->count(),
            'avg_departure_delay_min' => $avgDeparture,
            'avg_arrival_delay_min'   => $avgArrival,
            'on_time_percentage'      => $onTimePct,
            'cancelled_count'         => $cancelled,
            'delay_score'             => $delayScore,
            'computed_at'             => now()->toIso8601String(),
        ];
    }

    /**
     * Match a flight more than ~2 days out against the published schedule
     * (AeroAPI /schedules). Returns a payload in the same shape as /flights
     * so callers can treat both alike; fa_flight_id is usually still null.
     */
    private function findScheduled(string $ident, Carbon $departureAround, ?string $originAirport = null): array
    {
        [$airline, $number] = $this->splitIdent($ident);
        if (!$airline || !$number) {
            return ['ok' => false, 'status' => null, 'data' => null, 'error' => "Could not split '{$ident}' into airline and flight number."];
        }

        $result = $this->get(
            sprintf('/schedules/%s/%s', $departureAround->copy()->subDay()->toDateString(), $departureAround->copy()->addDay()->toDateString()),
            ['airline' => $airline, 'flight_number' => $number]
        );
        if (!$result['ok']) {
            return $result;
        }

        $entries = collect($result['data']['scheduled'] ?? []);

        if ($originAirport) {
            $byOrigin = $entries->filter(fn ($s) => in_array(
                strtoupper($originAirport),
                array_map('strtoupper', array_filter([$s['origin'] ?? null, $s['origin_iata'] ?? null, $s['origin_icao'] ?? null])),
                true
            ));
            if ($byOrigin->isNotEmpty()) {
                $entries = $byOrigin;
            }
        }

        $entry = $entries
            ->filter(fn ($s) => !empty($s['scheduled_out']))
            ->sortBy(fn ($s) => abs(Carbon::parse($s['scheduled_out'])->diffInSeconds($departureAround, false)))
            ->first();

        if (!$entry) {
            return ['ok' => false, 'status' => $result['status'], 'data' => null,
                    'error' => "No scheduled flight matched {$ident} on {$departureAround->toDateString()}."];
        }

        return ['ok' => true, 'status' => $result['status'], 'error' => null, 'data' => [
            'fa_flight_id'  => $entry['fa_flight_id'] ?? null,
            'ident'         => $entry['actual_ident'] ?? $entry['ident'] ?? $ident,
            'ident_iata'    => $entry['actual_ident_iata'] ?? $entry['ident_iata'] ?? $ident,
            'scheduled_out' => $entry['scheduled_out'] ?? null,
            'scheduled_in'  => $entry['scheduled_in'] ?? null,
            'origin'        => ['code_iata' => $entry['origin_iata'] ?? null, 'code_icao' => $entry['origin_icao'] ?? null],
            'destination'   => ['code_iata' => $entry['destination_iata'] ?? null, 'code_icao' => $entry['destination_icao'] ?? null],
            'status'        => 'Scheduled',
            'cancelled'     => false,
        ]];
    }

    /**
     * "AC845" → ["AC", "845"]. IATA airline codes can be alphanumeric
     * (F8, U2, 3M), so a 2-char code just needs at least one letter;
     * 3-char ICAO codes are letters only. Returns [null, null] when
     * unparseable.
     */
    private function splitIdent(string $ident): array
    {
        return preg_match('/^((?=[A-Z0-9]*[A-Z])[A-Z0-9]{2}|[A-Z]{3})\s*(\d{1,4}[A-Z]?)$/i', trim($ident), $m)
            ? [strtoupper($m[1]), $m[2]]
            : [null, null];
    }

    /**
     * Airport metadata (country code, timezone, coordinates) by IATA/ICAO
     * code. Airports don't move — cached forever.
     */
    /** Carrier display name for an ICAO operator code, e.g. AIC -> "Air India". */
    public function operatorName(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return Cache::rememberForever("flightaware.operator.{$code}", function () use ($code) {
            $result = $this->get('/operators/' . rawurlencode($code));

            return $result['ok'] ? ($result['data']['name'] ?? null) : null;
        });
    }

    public function airportInfo(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return Cache::rememberForever("flightaware.airport.{$code}", function () use ($code) {
            $result = $this->get('/airports/' . rawurlencode($code));

            return $result['ok'] ? $result['data'] : null;
        });
    }

    // ── Internals ───────────────────────────────────────────

    /** GET with auth + retry; never throws. */
    private function get(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => null, 'data' => null, 'error' => 'FlightAware API key is not configured.'];
        }

        try {
            /** @var Response $response */
            $response = Http::baseUrl(rtrim(config('services.flightaware.base_url'), '/'))
                ->withHeaders(['x-apikey' => config('services.flightaware.api_key')])
                ->timeout(20)
                ->retry(
                    (int) config('trip_monitoring.api_retries', 2),
                    (int) config('trip_monitoring.api_retry_delay_ms', 500),
                    // Retry network errors and 5xx/429 only — 4xx won't heal.
                    fn ($e) => !($e instanceof RequestException)
                        || $e->response->serverError() || $e->response->status() === 429,
                    throw: false
                )
                ->get($path, $query);
        } catch (Throwable $e) {
            Log::warning('FlightAware request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'data' => null, 'error' => $e->getMessage()];
        }

        if (!$response->successful()) {
            $message = $response->json('detail') ?: ('AeroAPI HTTP ' . $response->status());

            return ['ok' => false, 'status' => $response->status(), 'data' => null, 'error' => $message];
        }

        return ['ok' => true, 'status' => $response->status(), 'data' => $response->json() ?: [], 'error' => null];
    }

    /** Pick the operation closest to the expected departure (matching origin when known). */
    private function bestMatch(array $flights, ?Carbon $departureAround, ?string $originAirport): ?array
    {
        $candidates = collect($flights)->filter(fn ($f) => !empty($f['scheduled_out']) || !empty($f['scheduled_off']));

        if ($originAirport) {
            $byOrigin = $candidates->filter(function ($f) use ($originAirport) {
                $codes = array_filter([
                    $f['origin']['code'] ?? null,
                    $f['origin']['code_iata'] ?? null,
                    $f['origin']['code_icao'] ?? null,
                ]);

                return in_array(strtoupper($originAirport), array_map('strtoupper', $codes), true);
            });
            if ($byOrigin->isNotEmpty()) {
                $candidates = $byOrigin;
            }
        }

        if (!$departureAround) {
            return $candidates->first();
        }

        return $candidates
            ->sortBy(function ($f) use ($departureAround) {
                $out = Carbon::parse($f['scheduled_out'] ?? $f['scheduled_off']);

                return abs($out->diffInSeconds($departureAround, false));
            })
            ->filter(function ($f) use ($departureAround) {
                $out = Carbon::parse($f['scheduled_out'] ?? $f['scheduled_off']);

                // Never bind to an operation more than 36h away from the
                // user's stated departure — that's a different day's flight.
                return abs($out->diffInHours($departureAround, false)) <= 36;
            })
            ->first();
    }
}
