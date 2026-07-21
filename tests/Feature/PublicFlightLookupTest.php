<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketing\PublicFlightLookupService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Public "check your flight" - visitors get a provisional read before they
 * sign up. No account, no AI, no database writes, and never a dead end.
 */
class PublicFlightLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.flightaware.api_key' => 'test-key']);
        config(['services.gemini.api_key' => 'test-key']); // must stay unused
    }

    private function fakeFlight(array $overrides = []): void
    {
        $flight = array_merge([
            'ident_iata'    => 'AC123',
            'cancelled'     => false,
            'arrival_delay' => 240 * 60,
            'origin'        => ['code_iata' => 'YYZ', 'code' => 'CYYZ'],
            'destination'   => ['code_iata' => 'LHR', 'code' => 'EGLL'],
            'scheduled_out' => '2026-07-20T14:00:00Z',
        ], $overrides);

        Http::fake([
            '*/flights/AC123*'  => Http::response(['flights' => [$flight]], 200),
            '*/airports/YYZ*'   => Http::response(['country_code' => 'CA'], 200),
            '*/airports/LHR*'   => Http::response(['country_code' => 'GB'], 200),
            '*generativelanguage*' => Http::response([], 500),
        ]);
    }

    private function lookup(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('flight-lookup'), array_merge([
            'flight' => 'AC123',
            'date'   => '2026-07-20',
        ], $payload));
    }

    public function test_a_delayed_flight_returns_a_provisional_eligible_verdict(): void
    {
        $this->fakeFlight();

        $data = $this->lookup()->assertOk()->json('data');

        $this->assertTrue($data['found']);
        $this->assertTrue($data['eligible']);
        $this->assertSame('eligible', $data['status']);
        $this->assertSame('YYZ', $data['flight']['origin']);
        $this->assertSame('LHR', $data['flight']['destination']);
        $this->assertSame('4h', $data['flight']['status']);
        $this->assertSame(240, $data['flight']['delay_min']);
        $this->assertStringContainsString('may be eligible', $data['headline']);
        $this->assertStringContainsString('account', strtolower($data['cta']));
    }

    public function test_a_cancelled_flight_is_flagged_as_worth_claiming(): void
    {
        $this->fakeFlight(['cancelled' => true, 'arrival_delay' => 0]);

        $data = $this->lookup()->assertOk()->json('data');

        $this->assertTrue($data['flight']['cancelled']);
        $this->assertSame('Cancelled', $data['flight']['status']);
        $this->assertTrue($data['eligible']);
    }

    public function test_an_on_time_flight_is_told_the_truth_not_sold_a_claim(): void
    {
        $this->fakeFlight(['arrival_delay' => 5 * 60]);

        $data = $this->lookup()->assertOk()->json('data');

        $this->assertSame('not_disrupted', $data['status']);
        $this->assertFalse($data['eligible']);
        $this->assertStringNotContainsString('may be eligible', $data['headline']);
    }

    public function test_a_short_delay_is_explained_rather_than_dismissed(): void
    {
        $this->fakeFlight(['arrival_delay' => 90 * 60]);

        $data = $this->lookup()->assertOk()->json('data');

        $this->assertSame('not_disrupted', $data['status']);
        $this->assertStringContainsString('below the 3-hour threshold', $data['detail']);
        // Still offers a route forward - a missed connection may still qualify.
        $this->assertNotEmpty($data['cta']);
    }

    public function test_an_untrackable_flight_still_converts(): void
    {
        Http::fake(['*' => Http::response(['flights' => []], 200)]);

        $data = $this->lookup(['date' => '2026-01-05'])->assertOk()->json('data');

        $this->assertFalse($data['found']);
        $this->assertSame('not_found', $data['status']);
        $this->assertStringContainsString('does not mean you have no claim', $data['detail']);
        $this->assertNotEmpty($data['cta']);
    }

    public function test_flightaware_failure_never_dead_ends_the_visitor(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $data = $this->lookup()->assertOk()->json('data');

        $this->assertFalse($data['found']);
        $this->assertNotEmpty($data['cta']);
    }

    public function test_repeat_lookups_are_served_from_cache(): void
    {
        $this->fakeFlight();

        $this->lookup()->assertOk();
        $this->lookup()->assertOk();
        $this->lookup()->assertOk();

        // One flight lookup for three visitors asking the same question.
        Http::assertSentCount(3); // 1 flight + 2 airport country lookups
    }

    public function test_the_public_endpoint_never_calls_the_ai(): void
    {
        $this->fakeFlight();

        $this->lookup()->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
    }

    public function test_garbage_input_is_rejected_before_any_api_call(): void
    {
        Http::fake();

        $this->lookup(['flight' => 'DROP TABLE'])->assertJsonValidationErrors('flight');
        $this->lookup(['flight' => ''])->assertJsonValidationErrors('flight');
        $this->lookup(['date' => 'not-a-date'])->assertJsonValidationErrors('date');
        $this->lookup(['date' => '2010-01-01'])->assertJsonValidationErrors('date');

        Http::assertNothingSent();
    }

    public function test_the_endpoint_is_throttled(): void
    {
        $this->fakeFlight();

        // 10 requests a minute; the 11th is refused.
        for ($i = 0; $i < 10; $i++) {
            $this->lookup(['flight' => 'AC' . (100 + $i)])->assertOk();
        }

        $this->lookup(['flight' => 'AC999'])->assertStatus(429);
    }

    public function test_no_account_or_claim_is_created_by_looking_up_a_flight(): void
    {
        $this->fakeFlight();

        $this->lookup()->assertOk();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('claims', 0);
    }

    public function test_the_landing_page_offers_the_search_next_to_the_live_badge(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Search your flight', $html);
        $this->assertStringContainsString('ujSearchForm', $html);
        // Sits with the LIVE badge, not buried elsewhere on the page.
        $this->assertLessThan(
            strpos($html, '>LIVE'),
            strpos($html, 'Search your flight'),
            'The search button should render immediately before the LIVE badge.'
        );
    }

    public function test_the_card_carries_airline_cities_and_local_times(): void
    {
        Http::fake([
            '*/flights/AI2768*' => Http::response(['flights' => [[
                'ident_iata'      => 'AI2768',
                'operator_iata'   => 'AI',
                'operator_icao'   => 'AIC',
                'cancelled'       => false,
                'departure_delay' => -360,
                'arrival_delay'   => -1080,
                'scheduled_out'   => '2026-07-21T10:40:00Z',
                'actual_out'      => '2026-07-21T10:34:00Z',
                'scheduled_in'    => '2026-07-21T13:10:00Z',
                'actual_in'       => '2026-07-21T12:52:00Z',
                'progress_percent' => 100,
                'status'          => 'Landed / Taxiing',
                'origin'          => ['code_iata' => 'CCU', 'city' => 'Kolkata (Calcutta)', 'name' => "Netaji Subhash Chandra Bose Int'l", 'timezone' => 'Asia/Kolkata'],
                'destination'     => ['code_iata' => 'DEL', 'city' => 'New Delhi', 'name' => "Indira Gandhi Int'l", 'timezone' => 'Asia/Kolkata'],
            ]]], 200),
            '*/operators/AIC*' => Http::response(['name' => 'Air India'], 200),
            '*'                => Http::response(['country_code' => 'IN'], 200),
        ]);

        $flight = $this->postJson(route('flight-lookup'), ['flight' => 'AI2768', 'date' => '2026-07-21'])
            ->assertOk()->json('data.flight');

        $this->assertSame('Air India', $flight['airline']);
        $this->assertSame('Landed / Taxiing', $flight['status_text']);
        $this->assertSame(100, $flight['progress']);

        // Times in the airport's own zone, not UTC - 10:34Z is 16:04 IST.
        $this->assertSame('Kolkata (Calcutta)', $flight['from']['city']);
        $this->assertSame('16:04', $flight['from']['actual']);
        $this->assertSame('16:10', $flight['from']['scheduled']);
        $this->assertSame('IST', $flight['from']['timezone']);
        $this->assertSame('6m early', $flight['from']['delta']);
        $this->assertFalse($flight['from']['late']);

        $this->assertSame('New Delhi', $flight['to']['city']);
        $this->assertSame('18:22', $flight['to']['actual']);
        $this->assertSame('18m early', $flight['to']['delta']);
    }

    public function test_a_late_arrival_is_labelled_late_not_early(): void
    {
        $this->fakeFlight();

        $flight = $this->lookup()->assertOk()->json('data.flight');

        $this->assertTrue($flight['to']['late']);
        $this->assertSame('4h late', $flight['to']['delta']);
    }

    public function test_an_unknown_carrier_leaves_the_airline_blank_rather_than_guessing(): void
    {
        Http::fake([
            '*/flights/*'   => Http::response(['flights' => [[
                'ident_iata' => 'XX999', 'cancelled' => true, 'arrival_delay' => 0,
                'scheduled_out' => '2026-07-20T14:00:00Z',
                'origin' => ['code_iata' => 'CDG'], 'destination' => ['code_iata' => 'JFK'],
            ]]], 200),
            '*/operators/*' => Http::response([], 404),
            '*'             => Http::response(['country_code' => 'FR'], 200),
        ]);

        $flight = $this->postJson(route('flight-lookup'), ['flight' => 'XX999', 'date' => '2026-07-20'])
            ->assertOk()->json('data.flight');

        $this->assertNull($flight['airline']);
        $this->assertSame('XX999', $flight['ident']);
    }

    public function test_a_cancelled_flight_shows_no_actual_times_delta_or_progress(): void
    {
        // FlightAware keeps the schedule on a cancelled flight; presenting it
        // as "2m early" would tell the passenger their flight arrived.
        Http::fake([
            '*/flights/YX4477*' => Http::response(['flights' => [[
                'ident_iata'      => 'YX4477',
                'cancelled'       => true,
                'departure_delay' => -120,
                'arrival_delay'   => -120,
                'scheduled_out'   => '2026-07-21T10:00:00Z',
                'actual_out'      => '2026-07-21T09:58:00Z',
                'scheduled_in'    => '2026-07-21T11:45:00Z',
                'actual_in'       => '2026-07-21T11:43:00Z',
                'progress_percent' => 100,
                'origin'          => ['code_iata' => 'DTW', 'city' => 'Detroit', 'timezone' => 'America/Detroit'],
                'destination'     => ['code_iata' => 'LGA', 'city' => 'New York', 'timezone' => 'America/New_York'],
            ]]], 200),
            '*' => Http::response(['country_code' => 'US'], 200),
        ]);

        $flight = $this->postJson(route('flight-lookup'), ['flight' => 'YX4477', 'date' => '2026-07-21'])
            ->assertOk()->json('data.flight');

        $this->assertTrue($flight['cancelled']);
        $this->assertSame(0, $flight['progress']);

        foreach (['from', 'to'] as $end) {
            $this->assertNull($flight[$end]['actual'], "{$end} must have no actual time");
            $this->assertNull($flight[$end]['delta'], "{$end} must not claim early/late");
            $this->assertFalse($flight[$end]['late']);
            // The schedule is still useful context.
            $this->assertNotNull($flight[$end]['scheduled']);
        }
    }

    public function test_signed_in_visitors_are_not_asked_to_create_an_account(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('var authed = true', $html);
        $this->assertStringContainsString('Start this claim in your account', $html);

        // Guests still get the signup wording.
        Auth::logout();
        $guestHtml = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('var authed = false', $guestHtml);
        $this->assertStringContainsString('Free to check', $guestHtml);
    }

    public function test_guests_are_offered_the_zero_friction_email_route(): void
    {
        config(['services.inbound.claims_display' => 'claims@unjamm.com']);

        $html = $this->get('/')->assertOk()->getContent();

        // Forwarding a ticket creates the claim AND the account - no form.
        $this->assertStringContainsString('Email ticket', $html);
        $this->assertStringContainsString('mailto:claims@unjamm.com', $html);
        $this->assertStringContainsString('set up your account automatically', $html);

        // Signed-in visitors already have an account, so the block is skipped.
        $this->assertStringContainsString('if (authed) { return \'\'; }', $html);
    }

    public function test_service_handles_a_malformed_date_without_throwing(): void
    {
        $result = app(PublicFlightLookupService::class)->lookup('AC123', 'nonsense');

        $this->assertFalse($result['found']);
        $this->assertSame('invalid', $result['status']);
    }
}
