<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripMonitorLog;
use App\Notifications\TripDisruptionDetected;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trip Protection engine: registers protected trips with FlightAware,
 * polls them at the configured checkpoints (T-24h … T+24h), keeps the
 * local flight record in sync, records every poll and detected event,
 * and flags trips as Potentially Eligible when a qualifying disruption
 * (long delay / cancellation) is reported.
 *
 * It deliberately does NOT calculate compensation or create claims -
 * that is the Eligibility Engine's job, later.
 */
class TripMonitoringService
{
    public function __construct(
        private FlightAwareService $flightAware,
        private EligibilityEngine $eligibility,
    ) {
    }

    /**
     * One monitoring cycle for a trip: register with FlightAware if needed,
     * otherwise refresh by fa_flight_id. Always writes a monitor log row and
     * reschedules the next checkpoint. Returns the log entry.
     */
    public function sync(Trip $trip, string $trigger = 'schedule'): TripMonitorLog
    {
        return $trip->fa_flight_id
            ? $this->refresh($trip, $trigger)
            : $this->register($trip, $trigger);
    }

    /** First contact: match the trip to a FlightAware flight and store its identifiers. */
    private function register(Trip $trip, string $trigger): TripMonitorLog
    {
        $ident = $trip->flightIdent();

        if (!$ident) {
            $trip->forceFill(['monitoring_status' => Trip::MONITORING_FAILED, 'next_poll_at' => null])->save();

            return $this->log($trip, $trigger, TripMonitorLog::RESULT_ERROR, null, 'Trip has no flight number to look up.');
        }

        $result = $this->flightAware->findFlight($ident, $trip->departureMoment(), $trip->departure_airport);

        if (!$result['ok']) {
            $this->scheduleRetryOrFail($trip);

            return $this->log($trip, $trigger, TripMonitorLog::RESULT_NOT_FOUND, $result['status'], $result['error']);
        }

        $flight = $result['data'];
        $trip->forceFill([
            'fa_flight_id'      => $flight['fa_flight_id'] ?? null,
            'fa_ident'          => $flight['ident_iata'] ?? $flight['ident'] ?? $ident,
            'monitoring_status' => Trip::MONITORING_ACTIVE,
        ]);

        // Route reliability is informational and rarely changes - fetch once.
        if (!$trip->route_stats && ($stats = $this->flightAware->historicalStats($ident))) {
            $trip->route_stats = $stats;
            $trip->delay_score = $stats['delay_score'];
        }

        $this->applySnapshot($trip, $flight, notify: true);
        $this->scheduleNextPoll($trip);
        $trip->save();
        $this->maybeEvaluateEligibility($trip);

        return $this->log($trip, $trigger === 'schedule' ? 'registration' : $trigger, TripMonitorLog::RESULT_SYNCED, $result['status']);
    }

    /** Regular poll of an already-registered flight. */
    private function refresh(Trip $trip, string $trigger): TripMonitorLog
    {
        $result = $this->flightAware->getFlight($trip->fa_flight_id);

        if (!$result['ok']) {
            $this->scheduleRetryOrFail($trip);

            return $this->log($trip, $trigger, TripMonitorLog::RESULT_ERROR, $result['status'], $result['error']);
        }

        $this->applySnapshot($trip, $result['data'], notify: true);
        $this->scheduleNextPoll($trip);
        $trip->save();
        $this->maybeEvaluateEligibility($trip);

        return $this->log($trip, $trigger, TripMonitorLog::RESULT_SYNCED, $result['status']);
    }

    /**
     * Run the Eligibility Engine once per trip, as soon as its disruption
     * is final: the trip was flagged during monitoring and the flight has
     * finished (landed, cancelled, or monitoring closed at T+24h).
     */
    private function maybeEvaluateEligibility(Trip $trip): void
    {
        $due = $trip->potentially_eligible
            && !$trip->eligibility_evaluated_at
            && $trip->monitoring_status === Trip::MONITORING_COMPLETED;

        if (!$due) {
            return;
        }

        try {
            $this->eligibility->evaluate($trip);
        } catch (Throwable $e) {
            Log::error('Eligibility evaluation failed', ['trip' => $trip->id, 'error' => $e->getMessage()]);
        }
    }

    // ── Snapshot / event detection ──────────────────────────

    /**
     * Map an AeroAPI flight payload onto the trip, diff it against the
     * previous state, record detected events, and (optionally) notify the
     * user when a qualifying disruption first appears.
     */
    private function applySnapshot(Trip $trip, array $flight, bool $notify): void
    {
        $old = $trip->only([
            'flight_status', 'scheduled_departure', 'scheduled_arrival',
            'origin_gate', 'destination_gate', 'actual_arrival',
            'departure_delay_minutes', 'arrival_delay_minutes',
        ]);

        $trip->forceFill([
            'scheduled_departure'     => $this->time($flight, 'scheduled_out', 'scheduled_off'),
            'scheduled_arrival'       => $this->time($flight, 'scheduled_in', 'scheduled_on'),
            'estimated_departure'     => $this->time($flight, 'estimated_out', 'estimated_off'),
            'estimated_arrival'       => $this->time($flight, 'estimated_in', 'estimated_on'),
            'actual_departure'        => $this->time($flight, 'actual_out', 'actual_off'),
            'actual_arrival'          => $this->time($flight, 'actual_in', 'actual_on'),
            'departure_delay_minutes' => isset($flight['departure_delay']) ? (int) round($flight['departure_delay'] / 60) : null,
            'arrival_delay_minutes'   => isset($flight['arrival_delay']) ? (int) round($flight['arrival_delay'] / 60) : null,
            'origin_gate'             => $flight['gate_origin'] ?? $trip->origin_gate,
            'destination_gate'        => $flight['gate_destination'] ?? $trip->destination_gate,
            'origin_terminal'         => $flight['terminal_origin'] ?? $trip->origin_terminal,
            'destination_terminal'    => $flight['terminal_destination'] ?? $trip->destination_terminal,
            'route_distance_miles'    => $flight['route_distance'] ?? $trip->route_distance_miles,
            'progress_percent'        => $flight['progress_percent'] ?? $trip->progress_percent,
            'flight_status_text'      => $flight['status'] ?? null,
            'last_synced_at'          => now(),
        ]);

        // Fill airline/route gaps FlightAware can resolve better than parsing.
        if (!$trip->airline && !empty($flight['operator_iata'])) {
            $trip->airline = $flight['operator_iata'];
        }

        $trip->flight_status = $this->normalizeStatus($trip, $flight);

        $this->detectEvents($trip, $old, $flight);

        // A qualifying disruption flags the trip for eligibility review and
        // notifies the user - once. No compensation math here.
        $disruption = $this->qualifyingDisruption($trip, $flight);
        if ($disruption && !$trip->potentially_eligible) {
            $trip->potentially_eligible = true;

            if ($notify && $trip->user && !$trip->disruption_notified_at) {
                try {
                    $trip->user->notify(new TripDisruptionDetected($trip, $disruption));
                    $trip->disruption_notified_at = now();
                } catch (Throwable $e) {
                    Log::error('Trip disruption notification failed', ['trip' => $trip->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    private function detectEvents(Trip $trip, array $old, array $flight): void
    {
        // Cancellation
        if (!empty($flight['cancelled']) && $old['flight_status'] !== Trip::FLIGHT_CANCELLED) {
            $this->event($trip, TripEvent::TYPE_CANCELLATION, "Flight {$trip->flightIdent()} was cancelled.", [], qualifying: true);
        }

        // Delay (recorded whenever it grows past the notable threshold)
        $notable    = (int) config('trip_monitoring.notable_delay_minutes', 15);
        $qualifying = (int) config('trip_monitoring.qualifying_delay_minutes', 180);
        $newDelay   = max((int) $trip->departure_delay_minutes, (int) $trip->arrival_delay_minutes);
        $oldDelay   = max((int) ($old['departure_delay_minutes'] ?? 0), (int) ($old['arrival_delay_minutes'] ?? 0));

        if ($newDelay >= $notable && $newDelay !== $oldDelay) {
            $this->event(
                $trip,
                TripEvent::TYPE_DELAY,
                "Flight {$trip->flightIdent()} is delayed by " . $this->humanMinutes($newDelay) . '.',
                ['previous_delay_min' => $oldDelay, 'delay_min' => $newDelay],
                qualifying: $newDelay >= $qualifying
            );
        }

        // Gate changes (origin / destination)
        foreach (['origin_gate' => 'Departure gate', 'destination_gate' => 'Arrival gate'] as $field => $label) {
            if ($old[$field] && $trip->{$field} && $old[$field] !== $trip->{$field}) {
                $this->event($trip, TripEvent::TYPE_GATE_CHANGE, "{$label} changed from {$old[$field]} to {$trip->{$field}}.", [
                    'from' => $old[$field], 'to' => $trip->{$field},
                ]);
            }
        }

        // Schedule changes
        foreach (['scheduled_departure' => 'Scheduled departure', 'scheduled_arrival' => 'Scheduled arrival'] as $field => $label) {
            $before = $old[$field];
            $after  = $trip->{$field};
            if ($before && $after && !$before->equalTo($after)) {
                $this->event($trip, TripEvent::TYPE_SCHEDULE_CHANGE, "{$label} moved from {$before->format('d M H:i')} to {$after->format('d M H:i')} (UTC).", [
                    'from' => $before->toIso8601String(), 'to' => $after->toIso8601String(),
                ]);
            }
        }

        // Completion
        if (!$old['actual_arrival'] && $trip->actual_arrival) {
            $delay = max(0, (int) $trip->arrival_delay_minutes);
            $this->event($trip, TripEvent::TYPE_COMPLETED, "Flight {$trip->flightIdent()} arrived" . ($delay >= 15 ? ' ' . $this->humanMinutes($delay) . ' late.' : ' on time.'), [
                'arrival_delay_min' => $delay,
            ]);
        }
    }

    /** Returns a short disruption descriptor when the trip qualifies for review. */
    private function qualifyingDisruption(Trip $trip, array $flight): ?array
    {
        if (!empty($flight['cancelled'])) {
            return ['type' => 'cancellation'];
        }

        $threshold = (int) config('trip_monitoring.qualifying_delay_minutes', 180);
        $delay     = max((int) $trip->departure_delay_minutes, (int) $trip->arrival_delay_minutes);

        return $delay >= $threshold ? ['type' => 'delay', 'delay_minutes' => $delay] : null;
    }

    private function normalizeStatus(Trip $trip, array $flight): string
    {
        if (!empty($flight['cancelled'])) {
            return Trip::FLIGHT_CANCELLED;
        }
        if ($trip->actual_arrival) {
            return Trip::FLIGHT_COMPLETED;
        }

        $notable = (int) config('trip_monitoring.notable_delay_minutes', 15);
        if (max((int) $trip->departure_delay_minutes, (int) $trip->arrival_delay_minutes) >= $notable) {
            return Trip::FLIGHT_DELAYED;
        }

        // FlightAware has live estimates → the flight is tracking on time.
        if ($trip->estimated_departure || $trip->actual_departure) {
            return Trip::FLIGHT_ON_TIME;
        }

        return Trip::FLIGHT_SCHEDULED;
    }

    // ── Poll scheduling ─────────────────────────────────────

    /** Advance next_poll_at to the next configured checkpoint (or stop monitoring). */
    private function scheduleNextPoll(Trip $trip): void
    {
        $departure = $trip->departureMoment();
        $finished  = in_array($trip->flight_status, [Trip::FLIGHT_CANCELLED, Trip::FLIGHT_COMPLETED], true);

        if ($finished || !$departure) {
            $trip->monitoring_status = $finished ? Trip::MONITORING_COMPLETED : $trip->monitoring_status;
            $trip->next_poll_at      = null;

            return;
        }

        foreach (config('trip_monitoring.checkpoints', []) as $offsetMinutes) {
            $at = $departure->copy()->addMinutes($offsetMinutes);
            if ($at->isFuture()) {
                $trip->next_poll_at = $at;

                return;
            }
        }

        // Past the last checkpoint (T+24h) without completion - close out.
        $trip->monitoring_status = Trip::MONITORING_COMPLETED;
        $trip->next_poll_at      = null;
    }

    /**
     * A failed lookup/poll retries at the next checkpoint; once the last
     * checkpoint is behind us the trip is marked failed instead of polling
     * forever.
     */
    private function scheduleRetryOrFail(Trip $trip): void
    {
        $this->scheduleNextPoll($trip);

        if (!$trip->next_poll_at) {
            $trip->monitoring_status = $trip->fa_flight_id ? Trip::MONITORING_COMPLETED : Trip::MONITORING_FAILED;
        } elseif (!$trip->fa_flight_id) {
            // Not matched yet: also retry sooner than a far-away checkpoint.
            $retryAt = now()->addHours(6);
            if ($retryAt->lessThan($trip->next_poll_at)) {
                $trip->next_poll_at = $retryAt;
            }
        }

        $trip->save();
    }

    // ── Helpers ─────────────────────────────────────────────

    private function event(Trip $trip, string $type, string $description, array $data = [], bool $qualifying = false): void
    {
        $trip->events()->create([
            'type'        => $type,
            'description' => $description,
            'data'        => $data ?: null,
            'qualifying'  => $qualifying,
            'detected_at' => now(),
        ]);
    }

    private function log(Trip $trip, string $trigger, string $result, ?int $httpStatus, ?string $error = null): TripMonitorLog
    {
        return $trip->monitorLogs()->create([
            'polled_at'               => now(),
            'trigger'                 => $trigger,
            'flight_status'           => $trip->flight_status,
            'departure_delay_minutes' => $trip->departure_delay_minutes,
            'arrival_delay_minutes'   => $trip->arrival_delay_minutes,
            'http_status'             => $httpStatus,
            'result'                  => $result,
            'error_message'           => $error,
        ]);
    }

    private function time(array $flight, string ...$keys): ?Carbon
    {
        foreach ($keys as $key) {
            if (!empty($flight[$key])) {
                return Carbon::parse($flight[$key]);
            }
        }

        return null;
    }

    private function humanMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m ? "{$h}h {$m}m" : ($h === 1 ? '1 hour' : "{$h} hours");
    }
}
