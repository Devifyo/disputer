<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real disrupted flights for the landing page's live board, scanned from
 * FlightAware departures at a handful of hub airports. One cached scan per
 * hour - warmed by the scheduler - serves every visitor; the last good scan
 * is kept as a stale backup, and a static sample pool is the final fallback
 * so the board never goes empty and FlightAware is never hammered.
 */
class LiveDisruptionFeedService
{
    private const CACHE_KEY  = 'marketing.live-disruptions';
    private const BACKUP_KEY = 'marketing.live-disruptions.backup';
    private const TTL        = 3590;   // refreshed hourly by the scheduler - visitors always hit warm cache
    private const MAX_ROWS   = 12;

    /** Hubs scanned + the regime used to price the "You get" column. */
    private const AIRPORTS = [
        'LHR' => ['regime' => 'UK'],
        'CDG' => ['regime' => 'EU'],
        'FRA' => ['regime' => 'EU'],
        'AMS' => ['regime' => 'EU'],
        'YYZ' => ['regime' => 'CA'],
        'JFK' => ['regime' => 'US'],
    ];

    /** @return array<int, array{flight: string, route: string, status: string, pay: string}> */
    public function rows(): array
    {
        if (!config('services.flightaware.api_key')) {
            return $this->sample();
        }

        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            try {
                $rows = $this->scan();

                if (count($rows) >= 3) {
                    Cache::forever(self::BACKUP_KEY, $rows);

                    return $rows;
                }

                // Quiet skies: pad the real rows with samples.
                return array_slice(array_merge($rows, $this->sample()), 0, self::MAX_ROWS);
            } catch (Throwable $e) {
                Log::warning('Live disruption scan failed - serving backup', ['error' => $e->getMessage()]);

                return Cache::get(self::BACKUP_KEY, $this->sample());
            }
        });
    }

    private function scan(): array
    {
        $rows = [];

        foreach (self::AIRPORTS as $airport => $meta) {
            $response = Http::withHeaders(['x-apikey' => config('services.flightaware.api_key')])
                ->timeout(10)
                ->get(config('services.flightaware.base_url') . "/airports/{$airport}/flights/departures", [
                    'start'     => now()->subHours(12)->toIso8601ZuluString(),
                    'end'       => now()->toIso8601ZuluString(),
                    'max_pages' => 2,
                ]);

            if (!$response->successful()) {
                continue;
            }

            foreach ($response->json('departures', []) as $flight) {
                $row = $this->mapFlight($flight, $airport, $meta['regime']);
                if ($row) {
                    $rows[$row['flight']] = $row;
                }
            }
        }

        return array_slice(array_values($rows), 0, self::MAX_ROWS);
    }

    private function mapFlight(array $flight, string $airport, string $regime): ?array
    {
        $ident = $flight['ident_iata'] ?? $flight['ident'] ?? null;
        $dest  = $flight['destination']['code_iata'] ?? null;

        // Airline flights only - skip private/GA idents and unknown routes.
        if (!$ident || !$dest || !preg_match('/^([A-Z0-9]{2})\s?(\d{1,4})$/', $ident, $m)) {
            return null;
        }

        $cancelled = (bool) ($flight['cancelled'] ?? false);
        $delayMin  = (int) round(($flight['arrival_delay'] ?? 0) / 60);

        if (!$cancelled && $delayMin < 180) {
            return null;
        }

        // US DOT mandates refunds, not delay compensation - only cancellations board.
        if ($regime === 'US' && !$cancelled) {
            return null;
        }

        return [
            'flight' => $m[1] . ' ' . $m[2],
            'route'  => $airport . ' -> ' . $dest,
            'status' => $cancelled ? 'CANCELLED' : 'DELAYED ' . min(9, intdiv($delayMin, 60)) . 'H',
            'pay'    => $this->pay($regime, $cancelled, $delayMin),
        ];
    }

    /** Typical statutory entitlement for the board's "You get" column. */
    private function pay(string $regime, bool $cancelled, int $delayMin): string
    {
        return match ($regime) {
            'UK'    => '520GBP',
            'CA'    => $cancelled ? '1000CAD' : ($delayMin >= 540 ? '1000CAD' : ($delayMin >= 360 ? '700CAD' : '400CAD')),
            'US'    => 'REFUND',
            default => '600EUR',
        };
    }

    /** Static fallback so the board never goes empty. */
    private function sample(): array
    {
        return [
            ['flight' => 'BA 249', 'route' => 'LHR -> GRU', 'status' => 'DELAYED 4H', 'pay' => '520GBP'],
            ['flight' => 'AC 856', 'route' => 'YYZ -> LHR', 'status' => 'CANCELLED',  'pay' => '1000CAD'],
            ['flight' => 'AF 348', 'route' => 'CDG -> YUL', 'status' => 'DELAYED 3H', 'pay' => '600EUR'],
            ['flight' => 'LH 456', 'route' => 'FRA -> MIA', 'status' => 'DELAYED 4H', 'pay' => '600EUR'],
            ['flight' => 'KL 671', 'route' => 'AMS -> YYZ', 'status' => 'CANCELLED',  'pay' => '600EUR'],
            ['flight' => 'DL 401', 'route' => 'JFK -> LHR', 'status' => 'CANCELLED',  'pay' => 'REFUND'],
        ];
    }
}
