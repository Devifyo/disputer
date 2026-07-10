<?php

namespace Tests\Feature;

use App\Jobs\SyncTripFlight;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\TripMonitorLog;
use App\Models\User;
use App\Notifications\TripDisruptionDetected;
use App\Services\TripMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Trip Protection — FlightAware monitoring engine.
 *
 * All FlightAware traffic is faked; these tests cover registration,
 * event detection, eligibility flagging, notifications, poll scheduling
 * and the trips:monitor dispatcher.
 */
class TripMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Mutable AeroAPI flight payload returned by the HTTP fake. */
    private array $flight;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-10 06:00:00');
        Notification::fake();

        config([
            'services.flightaware.api_key'       => 'test-key',
            'trip_monitoring.api_retries'        => 1,
            'trip_monitoring.api_retry_delay_ms' => 1,
        ]);

        $this->user   = User::factory()->create();
        $this->flight = $this->flightPayload();
    }

    /**
     * Every AeroAPI call returns the current $this->flight — tests mutate it
     * between syncs to simulate the flight's state changing. Kept out of
     * setUp because the first registered Http::fake stub wins, which would
     * shadow per-test fakes.
     */
    private function fakeLiveFlight(): void
    {
        Http::fake(fn () => Http::response(['flights' => [$this->flight]], 200));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Registration ────────────────────────────────────────

    public function test_registration_binds_flightaware_identifiers_and_stores_times(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();

        $this->sync($trip);

        $this->assertSame('ACA845-1700000000-airline-0001', $trip->fa_flight_id);
        $this->assertSame('AC845', $trip->fa_ident);
        $this->assertSame(Trip::MONITORING_ACTIVE, $trip->monitoring_status);
        $this->assertSame('2026-07-11 08:00:00', $trip->scheduled_departure->toDateTimeString());
        $this->assertSame('2026-07-11 16:00:00', $trip->scheduled_arrival->toDateTimeString());
        $this->assertNotNull($trip->last_synced_at);

        $log = $trip->monitorLogs()->first();
        $this->assertSame(TripMonitorLog::RESULT_SYNCED, $log->result);
        $this->assertSame(200, $log->http_status);
    }

    public function test_far_future_trip_registers_via_published_schedule(): void
    {
        // 5 days out — beyond the ~2-day /flights horizon, so the published
        // schedule is used and no fa_flight_id exists yet.
        $this->flight = $this->flightPayload();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/schedules/')) {
                return Http::response(['scheduled' => [[
                    'ident'             => 'ACA845',
                    'actual_ident_iata' => 'AC845',
                    'fa_flight_id'      => null,
                    'scheduled_out'     => '2026-07-15T08:00:00Z',
                    'scheduled_in'      => '2026-07-15T16:00:00Z',
                    'origin_iata'       => 'FRA',
                    'destination_iata'  => 'YUL',
                ]]], 200);
            }

            // historicalStats() still queries /flights/{ident}.
            return Http::response(['flights' => []], 200);
        });

        $trip = $this->protectedTrip(['departure_date' => '2026-07-15']);
        $this->sync($trip);

        $this->assertNull($trip->fa_flight_id);
        $this->assertSame(Trip::MONITORING_ACTIVE, $trip->monitoring_status);
        $this->assertSame(Trip::FLIGHT_SCHEDULED, $trip->flight_status);
        $this->assertSame('2026-07-15 08:00:00', $trip->scheduled_departure->toDateTimeString());
        // Next checkpoint is T-24h, when /flights can bind the fa_flight_id.
        $this->assertSame('2026-07-14 08:00:00', $trip->next_poll_at->toDateTimeString());
    }

    public function test_unmatched_flight_is_logged_and_retried(): void
    {
        Http::fake(fn () => Http::response(['flights' => []], 200));

        $trip = $this->protectedTrip();
        $this->sync($trip);

        $this->assertNull($trip->fa_flight_id);
        $this->assertSame(Trip::MONITORING_PENDING, $trip->monitoring_status);
        $this->assertNotNull($trip->next_poll_at); // retries, does not give up
        $this->assertSame(TripMonitorLog::RESULT_NOT_FOUND, $trip->monitorLogs()->first()->result);
    }

    public function test_api_failure_is_logged_with_status_and_error(): void
    {
        Http::fake(fn () => Http::response(['detail' => 'Rate limit exceeded'], 429));

        $trip = $this->protectedTrip();
        $this->sync($trip);

        $log = $trip->monitorLogs()->first();
        $this->assertSame(TripMonitorLog::RESULT_NOT_FOUND, $log->result);
        $this->assertSame(429, $log->http_status);
        $this->assertStringContainsString('Rate limit', $log->error_message);
    }

    // ── Event detection & notifications ─────────────────────

    public function test_qualifying_delay_flags_trip_and_notifies_user_once(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();
        $this->sync($trip); // register on time

        // FlightAware now reports a 4-hour delay.
        $this->flight['estimated_out']   = '2026-07-11T12:00:00Z';
        $this->flight['departure_delay'] = 4 * 3600;
        $this->flight['arrival_delay']   = 4 * 3600;
        $this->sync($trip);

        $this->assertTrue($trip->potentially_eligible);
        $this->assertSame('potentially_eligible', $trip->displayStatus());
        $this->assertNotNull($trip->disruption_notified_at);

        $event = $trip->events()->where('type', TripEvent::TYPE_DELAY)->first();
        $this->assertTrue($event->qualifying);
        $this->assertStringContainsString('delayed by 4 hours', $event->description);

        Notification::assertSentToTimes($this->user, TripDisruptionDetected::class, 1);

        // The delay persists on the next poll — the user is NOT notified again.
        $this->sync($trip);
        Notification::assertSentToTimes($this->user, TripDisruptionDetected::class, 1);
    }

    public function test_minor_delay_is_recorded_but_does_not_notify(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();
        $this->flight['departure_delay'] = 30 * 60;
        $this->sync($trip);

        $this->assertSame(Trip::FLIGHT_DELAYED, $trip->flight_status);
        $this->assertFalse($trip->potentially_eligible);
        $this->assertFalse($trip->events()->where('type', TripEvent::TYPE_DELAY)->first()->qualifying);
        Notification::assertNothingSent();
    }

    public function test_cancellation_flags_trip_notifies_and_stops_monitoring(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();
        $this->sync($trip);

        $this->flight['cancelled'] = true;
        $this->sync($trip);

        $this->assertSame(Trip::FLIGHT_CANCELLED, $trip->flight_status);
        $this->assertTrue($trip->potentially_eligible);
        $this->assertSame('eligibility_review_pending', $trip->displayStatus());
        $this->assertSame(Trip::MONITORING_COMPLETED, $trip->monitoring_status);
        $this->assertNull($trip->next_poll_at);
        $this->assertTrue($trip->events()->where('type', TripEvent::TYPE_CANCELLATION)->exists());
        Notification::assertSentToTimes($this->user, TripDisruptionDetected::class, 1);
    }

    public function test_gate_and_schedule_changes_are_recorded_as_events(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();
        $this->sync($trip); // gate B22, scheduled 08:00

        $this->flight['gate_origin']   = 'C31';
        $this->flight['scheduled_out'] = '2026-07-11T08:30:00Z';
        $this->sync($trip);

        $gate = $trip->events()->where('type', TripEvent::TYPE_GATE_CHANGE)->first();
        $this->assertStringContainsString('from B22 to C31', $gate->description);

        $schedule = $trip->events()->where('type', TripEvent::TYPE_SCHEDULE_CHANGE)->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-07-11 08:30:00', $trip->scheduled_departure->toDateTimeString());
    }

    public function test_completed_flight_closes_monitoring(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip();
        $this->sync($trip);

        $this->flight['actual_out'] = '2026-07-11T08:05:00Z';
        $this->flight['actual_in']  = '2026-07-11T15:55:00Z';
        $this->sync($trip);

        $this->assertSame(Trip::FLIGHT_COMPLETED, $trip->flight_status);
        $this->assertSame(Trip::MONITORING_COMPLETED, $trip->monitoring_status);
        $this->assertSame('completed', $trip->displayStatus());
        $this->assertNull($trip->next_poll_at);
        $this->assertTrue($trip->events()->where('type', TripEvent::TYPE_COMPLETED)->exists());
    }

    // ── Poll scheduling ─────────────────────────────────────

    public function test_next_poll_advances_to_the_next_configured_checkpoint(): void
    {
        $this->fakeLiveFlight();

        $trip = $this->protectedTrip(); // departs 2026-07-11 08:00, now is 07-10 06:00
        $this->sync($trip);

        // T-24h (07-10 08:00) is the first future checkpoint.
        $this->assertSame('2026-07-10 08:00:00', $trip->next_poll_at->toDateTimeString());

        Carbon::setTestNow('2026-07-11 07:00:00'); // between T-2h and departure
        $this->sync($trip);
        $this->assertSame('2026-07-11 08:00:00', $trip->next_poll_at->toDateTimeString());

        Carbon::setTestNow('2026-07-12 09:00:00'); // past the last checkpoint (T+24h)
        $this->sync($trip);
        $this->assertNull($trip->next_poll_at);
        $this->assertSame(Trip::MONITORING_COMPLETED, $trip->monitoring_status);
    }

    public function test_monitor_command_dispatches_only_due_trips(): void
    {
        Queue::fake();

        $due = $this->protectedTrip(['next_poll_at' => now()->subMinute()]);
        $this->protectedTrip(['next_poll_at' => now()->addHour()]);                                        // not due yet
        $this->protectedTrip(['next_poll_at' => now()->subMinute(), 'monitoring_status' => 'completed']);  // finished

        $this->artisan('trips:monitor')->assertSuccessful();

        Queue::assertPushed(SyncTripFlight::class, 1);
        Queue::assertPushed(SyncTripFlight::class, fn ($job) => $job->trip->is($due));
    }

    // ── Helpers ─────────────────────────────────────────────

    private function sync(Trip $trip): void
    {
        app(TripMonitoringService::class)->sync($trip, 'schedule');
        $trip->refresh();
    }

    private function protectedTrip(array $attrs = []): Trip
    {
        return Trip::create(array_merge([
            'user_id'           => $this->user->id,
            'source'            => Trip::SOURCE_MANUAL,
            'status'            => Trip::STATUS_PROTECTED,
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC845',
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'YUL',
            'departure_date'    => '2026-07-11',
            'departure_time'    => '08:00',
            'passengers'        => ['Test Passenger'],
            'monitoring_status' => Trip::MONITORING_PENDING,
        ], $attrs));
    }

    /** AeroAPI /flights payload for an on-time AC845 departing 2026-07-11 08:00 UTC. */
    private function flightPayload(array $overrides = []): array
    {
        return array_merge([
            'fa_flight_id'     => 'ACA845-1700000000-airline-0001',
            'ident'            => 'ACA845',
            'ident_iata'       => 'AC845',
            'origin'           => ['code_iata' => 'FRA', 'code_icao' => 'EDDF'],
            'destination'      => ['code_iata' => 'YUL', 'code_icao' => 'CYUL'],
            'scheduled_out'    => '2026-07-11T08:00:00Z',
            'estimated_out'    => '2026-07-11T08:00:00Z',
            'scheduled_in'     => '2026-07-11T16:00:00Z',
            'estimated_in'     => '2026-07-11T16:00:00Z',
            'actual_out'       => null,
            'actual_in'        => null,
            'departure_delay'  => 0,
            'arrival_delay'    => 0,
            'gate_origin'      => 'B22',
            'gate_destination' => null,
            'cancelled'        => false,
            'status'           => 'Scheduled',
        ], $overrides);
    }
}
