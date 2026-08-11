<?php

namespace Tests\Feature;

use App\Jobs\SyncTripFlight;
use App\Models\Trip;
use App\Models\TripMonitorLog;
use App\Models\User;
use App\Notifications\TripDisruptionDetected;
use App\Services\TripMonitoringService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Flight-monitoring validation pass: the gaps the main TripMonitoringTest
 * does not pin down - the exact checkpoint windows, en-route tracking,
 * data survival through API failures, idempotent re-processing, retry
 * recovery, permanent-failure closure, log completeness and job safety.
 *
 * Purely additive verification: no business rules are changed here.
 */
class TripMonitoringValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Mutable AeroAPI payload served by the HTTP fake. */
    private array $flight;

    /** When non-null, flight endpoints return this HTTP status with no body. */
    private ?int $apiFailure = null;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-10 06:00:00');
        Notification::fake();

        config([
            'services.flightaware.api_key'       => 'test-key',
            'trip_monitoring.api_retries'        => 1,
            'trip_monitoring.api_retry_delay_ms' => 1,
            'eligibility.evaluator'              => 'rules',
        ]);

        $this->user   = User::factory()->create();
        $this->flight = $this->flightPayload();

        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $country = ['FRA' => 'DE', 'YUL' => 'CA'][$m[1]] ?? null;

                return Http::response(['code_iata' => $m[1], 'country_code' => $country], $country ? 200 : 404);
            }

            if ($this->apiFailure !== null) {
                return Http::response([], $this->apiFailure);
            }

            return Http::response(['flights' => array_filter([$this->flight])], 200);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── 3. Monitoring schedule ──────────────────────────────

    public function test_checkpoint_windows_match_the_specified_monitoring_plan(): void
    {
        // The configured plan IS the specified one: T-24h, T-6h, T-2h,
        // departure, T+2h, T+4h, T+8h, T+24h (minutes relative to departure).
        $this->assertSame(
            [-1440, -360, -120, 0, 120, 240, 480, 1440],
            config('trip_monitoring.checkpoints')
        );

        // Walking the clock through every window: after each sync the next
        // poll lands exactly on the next configured checkpoint. Departure
        // is 2026-07-11 08:00 UTC.
        $trip      = $this->protectedTrip();
        $departure = Carbon::parse('2026-07-11 08:00:00');
        $offsets   = config('trip_monitoring.checkpoints');

        $this->sync($trip); // registration at T-26h -> first checkpoint is T-24h
        $this->assertTrue($trip->next_poll_at->equalTo($departure->copy()->addMinutes($offsets[0])));

        foreach (array_slice($offsets, 0, -1) as $i => $offset) {
            Carbon::setTestNow($departure->copy()->addMinutes($offset)->addMinute());
            $this->sync($trip);
            $this->assertTrue(
                $trip->next_poll_at->equalTo($departure->copy()->addMinutes($offsets[$i + 1])),
                "After the T{$offset}m poll the next checkpoint must be T{$offsets[$i + 1]}m"
            );
            $this->assertSame(Trip::MONITORING_ACTIVE, $trip->monitoring_status);
        }

        // Past the last checkpoint without completion: monitoring closes out.
        Carbon::setTestNow($departure->copy()->addMinutes(end($offsets))->addMinute());
        $this->sync($trip);
        $this->assertNull($trip->next_poll_at);
        $this->assertSame(Trip::MONITORING_COMPLETED, $trip->monitoring_status);
    }

    public function test_the_monitor_command_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'trips:monitor'));

        $this->assertNotNull($event, 'trips:monitor must be on the scheduler');
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ── 4B. Departed, still in the air ──────────────────────

    public function test_departed_flight_tracks_en_route_without_closing_monitoring(): void
    {
        $trip = $this->protectedTrip();
        $this->sync($trip);

        Carbon::setTestNow('2026-07-11 09:00:00');
        $this->flight = $this->flightPayload([
            'actual_out' => '2026-07-11T08:05:00Z',
            'status'     => 'En Route',
        ]);
        $this->sync($trip);

        $this->assertSame('2026-07-11 08:05:00', $trip->actual_departure->toDateTimeString());
        $this->assertNull($trip->actual_arrival);
        $this->assertSame(Trip::FLIGHT_ON_TIME, $trip->flight_status);
        $this->assertSame(Trip::MONITORING_ACTIVE, $trip->monitoring_status);
        $this->assertNotNull($trip->next_poll_at, 'An airborne flight keeps polling until it lands');
    }

    // ── 4F + 9. Unavailable status / API failure mid-monitoring ──

    public function test_api_failure_mid_monitoring_never_corrupts_the_stored_flight(): void
    {
        $trip = $this->protectedTrip();
        $this->sync($trip);
        $before = $trip->only(['fa_flight_id', 'scheduled_departure', 'scheduled_arrival', 'flight_status']);

        // FlightAware goes dark at the next checkpoint.
        Carbon::setTestNow('2026-07-10 08:01:00');
        $this->apiFailure = 503;
        $this->sync($trip);

        $this->assertSame($before['fa_flight_id'], $trip->fa_flight_id);
        $this->assertTrue($before['scheduled_departure']->equalTo($trip->scheduled_departure));
        $this->assertTrue($before['scheduled_arrival']->equalTo($trip->scheduled_arrival));
        $this->assertSame($before['flight_status'], $trip->flight_status);
        $this->assertNotNull($trip->next_poll_at, 'A failed poll retries at the next checkpoint');

        $log = $trip->monitorLogs()->latest('id')->first();
        $this->assertSame(TripMonitorLog::RESULT_ERROR, $log->result);
        $this->assertSame(503, $log->http_status);
        $this->assertNotNull($log->error_message);

        // The API comes back with a delay: the very next poll recovers.
        $this->apiFailure = null;
        $this->flight     = $this->flightPayload(['arrival_delay' => 260 * 60, 'estimated_in' => '2026-07-11T20:20:00Z', 'status' => 'Delayed']);
        $this->sync($trip);

        $this->assertSame(260, $trip->arrival_delay_minutes);
        $this->assertSame(Trip::FLIGHT_DELAYED, $trip->flight_status);
        $this->assertSame(TripMonitorLog::RESULT_SYNCED, $trip->monitorLogs()->latest('id')->first()->result);
    }

    public function test_lookup_failure_past_the_last_checkpoint_marks_monitoring_failed(): void
    {
        // A trip that flew two days ago and was never matched: FlightAware
        // has nothing, every checkpoint is in the past - the trip must be
        // closed as failed rather than polled forever.
        $this->flight = [];
        $trip = $this->protectedTrip(['departure_date' => '2026-07-08', 'departure_time' => '08:00']);

        $this->sync($trip);

        $this->assertNull($trip->fa_flight_id);
        $this->assertNull($trip->next_poll_at);
        $this->assertSame(Trip::MONITORING_FAILED, $trip->monitoring_status);
        $this->assertSame(TripMonitorLog::RESULT_NOT_FOUND, $trip->monitorLogs()->latest('id')->first()->result);
    }

    // ── 5 + 8 + 11. Idempotent re-processing ────────────────

    public function test_replaying_the_same_flightaware_update_changes_nothing_twice(): void
    {
        $trip = $this->protectedTrip();
        $this->sync($trip);

        // The same qualifying delay arrives three times in a row.
        Carbon::setTestNow('2026-07-11 12:00:00');
        $this->flight = $this->flightPayload(['arrival_delay' => 260 * 60, 'estimated_in' => '2026-07-11T20:20:00Z', 'status' => 'Delayed']);
        $this->sync($trip);
        $this->sync($trip);
        $this->sync($trip);

        $this->assertSame(1, Trip::count(), 'Re-processing must never mint another flight record');
        $this->assertSame(1, $trip->events()->where('type', 'delay')->count(), 'One delay event, not one per poll');
        $this->assertTrue((bool) $trip->potentially_eligible);
        Notification::assertSentToTimes($this->user, TripDisruptionDetected::class, 1);

        // The flight lands; the same completion snapshot arrives three times.
        Carbon::setTestNow('2026-07-11 21:00:00');
        $this->flight = $this->flightPayload([
            'actual_out' => '2026-07-11T12:20:00Z', 'actual_in' => '2026-07-11T20:20:00Z',
            'arrival_delay' => 260 * 60, 'status' => 'Arrived',
        ]);
        $this->sync($trip);
        $this->assertSame(Trip::MONITORING_COMPLETED, $trip->monitoring_status);

        $evaluatedAt = $trip->eligibility_evaluated_at;
        $this->assertNotNull($evaluatedAt, 'Completion of a flagged trip triggers exactly one evaluation');
        $this->assertNotNull($trip->eligibility_status);

        $this->sync($trip);
        $this->sync($trip);

        $this->assertTrue($evaluatedAt->equalTo($trip->eligibility_evaluated_at), 'The engine must not run again for replayed updates');
        $this->assertSame(1, $trip->events()->where('type', 'completed')->count());
        Notification::assertSentToTimes($this->user, TripDisruptionDetected::class, 1);

        // Every poll IS logged - idempotency dedupes outcomes, not the trail.
        $this->assertSame(7, $trip->monitorLogs()->count());
    }

    // ── 10. Monitoring logs ─────────────────────────────────

    public function test_monitor_logs_carry_the_full_picture_and_no_credentials(): void
    {
        $trip = $this->protectedTrip();
        $this->sync($trip);

        Carbon::setTestNow('2026-07-10 08:01:00');
        $this->apiFailure = 500;
        $this->sync($trip);

        $synced = $trip->monitorLogs()->where('result', TripMonitorLog::RESULT_SYNCED)->firstOrFail();
        $this->assertNotNull($synced->polled_at);
        $this->assertSame('registration', $synced->trigger);
        $this->assertSame(200, $synced->http_status);
        $this->assertSame(Trip::FLIGHT_ON_TIME, $synced->flight_status);
        $this->assertSame(0, (int) $synced->arrival_delay_minutes);

        $failed = $trip->monitorLogs()->where('result', TripMonitorLog::RESULT_ERROR)->firstOrFail();
        $this->assertSame(500, $failed->http_status);
        $this->assertNotNull($failed->error_message);

        foreach ($trip->monitorLogs as $log) {
            $this->assertStringNotContainsString('test-key', (string) $log->error_message,
                'The FlightAware API key must never leak into monitoring logs');
        }
    }

    // ── 12. Queue safety ────────────────────────────────────

    public function test_the_sync_job_is_configured_to_retry_and_to_die_quietly(): void
    {
        $trip = $this->protectedTrip();
        $job  = new SyncTripFlight($trip, 'schedule');

        // Retry envelope: 3 attempts, backing off 60s then 300s, 120s budget.
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
        $this->assertSame(120, $job->timeout);
        $this->assertTrue($job->deleteWhenMissingModels, 'A deleted trip must drop the job, not fail it');
        $this->assertSame($trip->id, $job->trip->id, 'The job carries exactly the trip it was dispatched for');

        // A trip deleted between dispatch and execution: the job is a no-op.
        $trip->delete();
        $job->handle(app(TripMonitoringService::class));
        $this->assertSame(0, TripMonitorLog::count());
    }

    // ── Fixtures ────────────────────────────────────────────

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
