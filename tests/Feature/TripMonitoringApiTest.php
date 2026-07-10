<?php

namespace Tests\Feature;

use App\Jobs\SyncTripFlight;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Trip Protection — customer-facing monitoring API.
 */
class TripMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.flightaware.api_key' => 'test-key']);
        Role::findOrCreate('user');

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_monitoring_endpoint_returns_events_but_never_raw_poll_logs(): void
    {
        $trip = $this->trip();
        $trip->events()->create([
            'type'        => 'delay',
            'description' => 'Flight AC845 is delayed by 4 hours.',
            'qualifying'  => true,
            'detected_at' => now(),
        ]);
        $trip->monitorLogs()->create([
            'polled_at'     => now(),
            'trigger'       => 'schedule',
            'result'        => 'error',
            'http_status'   => 500,
            'error_message' => 'Internal provider error — must not leak to customers',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('user.itineraries.api.trips.monitoring', $trip))
            ->assertOk()
            ->assertJsonPath('data.events.0.description', 'Flight AC845 is delayed by 4 hours.')
            ->assertJsonPath('data.events.0.qualifying', true)
            ->assertJsonMissingPath('data.logs');

        $this->assertStringNotContainsString('Internal provider error', $response->getContent());
    }

    public function test_monitoring_endpoint_is_forbidden_for_other_users(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->actingAs($stranger)
            ->getJson(route('user.itineraries.api.trips.monitoring', $this->trip()))
            ->assertForbidden();
    }

    public function test_manual_sync_refreshes_the_trip_from_flightaware(): void
    {
        Http::fake(fn () => Http::response(['flights' => [[
            'fa_flight_id'    => 'ACA845-1700000000-airline-0001',
            'ident_iata'      => 'AC845',
            'scheduled_out'   => now()->addDay()->toIso8601String(),
            'estimated_out'   => now()->addDay()->toIso8601String(),
            'departure_delay' => 0,
            'cancelled'       => false,
            'status'          => 'Scheduled',
        ]]], 200));

        $trip = $this->trip([
            'fa_flight_id'      => 'ACA845-1700000000-airline-0001',
            'monitoring_status' => Trip::MONITORING_ACTIVE,
            'last_synced_at'    => now()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.sync', $trip))
            ->assertOk()
            ->assertJsonPath('data.flight_status', Trip::FLIGHT_ON_TIME)
            ->assertJsonPath('data.fa_flight_id', 'ACA845-1700000000-airline-0001');

        $this->assertSame('manual', $trip->monitorLogs()->first()->trigger);
    }

    public function test_manual_sync_is_throttled_when_recently_synced(): void
    {
        Http::fake();

        $trip = $this->trip([
            'fa_flight_id'      => 'ACA845-1700000000-airline-0001',
            'monitoring_status' => Trip::MONITORING_ACTIVE,
            'last_synced_at'    => now()->subSeconds(10),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.sync', $trip))
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_creating_a_trip_queues_flightaware_registration(): void
    {
        Queue::fake();
        Storage::fake('local');

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.store'), [
                'departure_airport' => 'FRA',
                'arrival_airport'   => 'YUL',
                'airline'           => 'Air Canada',
                'flight_number'     => 'AC845',
                'departure_date'    => now()->addDays(3)->toDateString(),
                'departure_time'    => '08:00',
                'passengers'        => ['Test Passenger'],
                'ticket'            => UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $trip = Trip::first();
        $this->assertSame(Trip::MONITORING_PENDING, $trip->monitoring_status);
        $this->assertNotNull($trip->next_poll_at); // trips:monitor backstop
        Queue::assertPushed(SyncTripFlight::class, fn ($job) => $job->trip->is($trip));
    }

    public function test_trip_summary_exposes_monitoring_fields(): void
    {
        $trip = $this->trip([
            'fa_flight_id'         => 'ACA845-1700000000-airline-0001',
            'monitoring_status'    => Trip::MONITORING_ACTIVE,
            'flight_status'        => Trip::FLIGHT_DELAYED,
            'potentially_eligible' => true,
            'last_synced_at'       => now()->subMinutes(2),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('user.itineraries.api.trips.show', $trip))
            ->assertOk()
            ->assertJsonPath('data.display_status', 'potentially_eligible')
            ->assertJsonPath('data.display_status_label', 'Potentially Eligible')
            ->assertJsonPath('data.monitoring', true)
            ->assertJsonPath('data.last_synced_human', '2 minutes ago');
    }

    // ── Helpers ─────────────────────────────────────────────

    private function trip(array $attrs = []): Trip
    {
        return Trip::create(array_merge([
            'user_id'           => $this->user->id,
            'source'            => Trip::SOURCE_MANUAL,
            'status'            => Trip::STATUS_PROTECTED,
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC845',
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'YUL',
            'departure_date'    => now()->addDays(3)->toDateString(),
            'departure_time'    => '08:00',
            'passengers'        => ['Test Passenger'],
        ], $attrs));
    }
}
