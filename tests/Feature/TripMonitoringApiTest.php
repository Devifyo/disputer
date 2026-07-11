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

        // No flight-status poll — airport-metadata lookups are fine.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/flights/'));
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

    public function test_reporting_denied_boarding_runs_eligibility_review(): void
    {
        config(['eligibility.evaluator' => 'rules']);
        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $country = ['FRA' => 'DE', 'YUL' => 'CA'][$m[1]] ?? null;

                return Http::response(['code_iata' => $m[1], 'country_code' => $country], $country ? 200 : 404);
            }

            return Http::response([], 200);
        });

        $trip = $this->trip([
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'YUL',
            'flight_status'     => Trip::FLIGHT_COMPLETED,
            'monitoring_status' => Trip::MONITORING_COMPLETED,
            'actual_arrival'    => now()->subHours(3),
        ]);

        Storage::fake('local');

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.report', $trip), [
                'type'    => 'denied_boarding',
                'answers' => [
                    ['question' => 'Did you volunteer to give up your seat?', 'answer' => 'No - I was denied against my will'],
                    ['question' => 'What reason did the airline give?', 'answer' => 'Overbooking'],
                ],
                'documents' => [UploadedFile::fake()->create('boarding-pass.pdf', 120, 'application/pdf')],
            ])
            ->assertOk()
            ->assertJsonPath('data.reported_disruption', 'denied_boarding')
            ->assertJsonPath('data.eligibility.regulation', 'EU261')
            ->assertJsonPath('data.eligibility.article', 'Articles 4 & 7');

        $trip->refresh();
        $this->assertTrue($trip->potentially_eligible);
        $this->assertCount(2, $trip->report_details['questions']);
        $this->assertSame('boarding-pass.pdf', $trip->report_details['documents'][0]['name']);
        Storage::disk('local')->assertExists($trip->report_details['documents'][0]['path']);
    }

    public function test_report_funnel_serves_adaptive_steps_with_curated_fallback(): void
    {
        Http::fake(); // no Gemini -> curated questions, served one at a time

        $trip    = $this->trip();
        $answers = [];

        // Walk the funnel answer by answer until it signals done.
        for ($i = 0; $i < 6; $i++) {
            $step = $this->actingAs($this->user)
                ->postJson(route('user.itineraries.api.trips.report.questions', $trip), [
                    'type' => 'denied_boarding', 'answers' => $answers,
                ])
                ->assertOk()
                ->json('data');

            if ($step['done']) {
                break;
            }
            $answers[] = ['question' => $step['question']['question'], 'answer' => $step['question']['options'][0] ?? 'test'];
        }

        $this->assertTrue($step['done']);
        $this->assertCount(4, $answers); // the curated denied-boarding set
        $this->assertSame('Did you check in and arrive at the gate before boarding closed?', $answers[0]['question']);
        $this->assertNotEmpty($step['documents']['examples']); // upload step arrives with done
    }

    public function test_reporting_is_rejected_before_departure_and_for_bad_types(): void
    {
        $trip    = $this->trip(); // future flight, phase "scheduled"
        $answers = [['question' => 'q', 'answer' => 'a']];

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.report', $trip), ['type' => 'denied_boarding', 'answers' => $answers])
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.report', $trip), ['type' => 'alien_abduction', 'answers' => $answers])
            ->assertStatus(422);
    }

    // ── Trip → claim handoff ────────────────────────────────

    public function test_eligible_trip_creates_one_claim_per_passenger(): void
    {
        $trip = $this->eligibleTrip(passengers: ['Tenzin Hagyal', 'Pema Hagyal']);

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.claim', $trip))
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $claims = $trip->claims()->get();
        $this->assertCount(2, $claims);
        $this->assertEqualsCanonicalizing(['Tenzin Hagyal', 'Pema Hagyal'], $claims->pluck('passenger_name')->all());
        $this->assertSame('delayed', $claims->first()->disruption_type);
        $this->assertSame('YEG', $claims->first()->departure_airport);
        $this->assertTrue($trip->events()->where('type', 'claim_created')->exists());

        // The trip leaves the action-needed state once its claim is filed.
        $this->actingAs($this->user)
            ->getJson(route('user.itineraries.api.trips.show', $trip))
            ->assertJsonPath('data.display_status', 'claim_filed')
            ->assertJsonPath('data.display_status_label', 'Claim Filed')
            ->assertJsonPath('data.can_claim', false);
    }

    public function test_claim_creation_is_idempotent(): void
    {
        $trip = $this->eligibleTrip(passengers: ['Tenzin Hagyal']);

        $this->actingAs($this->user)->postJson(route('user.itineraries.api.trips.claim', $trip))->assertCreated();
        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.claim', $trip))
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, $trip->claims()->count());
    }

    public function test_non_eligible_trip_cannot_create_a_claim(): void
    {
        $trip = $this->trip(); // never evaluated

        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.trips.claim', $trip))
            ->assertStatus(422);

        $this->assertSame(0, $trip->claims()->count());
    }

    public function test_claim_creation_is_forbidden_for_other_users(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->actingAs($stranger)
            ->postJson(route('user.itineraries.api.trips.claim', $this->eligibleTrip()))
            ->assertForbidden();
    }

    // ── Helpers ─────────────────────────────────────────────

    private function eligibleTrip(array $passengers = ['Test Passenger']): Trip
    {
        return $this->trip([
            'departure_airport'      => 'YEG',
            'arrival_airport'        => 'YYZ',
            'departure_date'         => now()->subDay()->toDateString(),
            'flight_status'          => Trip::FLIGHT_COMPLETED,
            'monitoring_status'      => Trip::MONITORING_COMPLETED,
            'potentially_eligible'   => true,
            'arrival_delay_minutes'  => 390,
            'passengers'             => $passengers,
            'passenger_name'         => $passengers[0],
            'eligibility_status'     => 'eligible',
            'eligibility_regulation' => 'APPR',
            'eligibility_article'    => 'Section 19(1)(a)',
            'eligibility_confidence' => 80,
            'eligibility_evaluated_at' => now(),
        ]);
    }

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
