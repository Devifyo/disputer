<?php

namespace Tests\Feature;

use App\Jobs\EvaluateClaim;
use App\Models\Claim;
use App\Models\User;
use App\Services\Eligibility\ClaimEligibilityService;
use App\Services\Eligibility\CompensationCalculator;
use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityResult;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Claims (past flights) run the same FlightAware verification + eligibility
 * engine + compensation calculation as monitored trips.
 */
class ClaimEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private const GEO = [
        'FRA' => ['DE', 50.03, 8.54],   // Frankfurt
        'YUL' => ['CA', 45.47, -73.74], // Montreal (FRA-YUL ~5,850 km)
        'LHR' => ['GB', 51.47, -0.46],  // London
        'AMS' => ['NL', 52.31, 4.76],   // Amsterdam (LHR-AMS ~370 km)
        'JFK' => ['US', 40.64, -73.78],
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.flightaware.api_key' => 'test-key',
            'eligibility.evaluator'        => 'rules',
        ]);

        $this->user = User::factory()->create();
    }

    // ── Compensation calculator ─────────────────────────────

    public function test_eu261_pays_600_eur_for_a_long_haul_delay(): void
    {
        $this->fakeAirportsOnly();

        $comp = $this->calculate('EU261', 'Article 7(1)', $this->context('FRA', 'YUL', delay: 300));

        $this->assertSame(600.0, $comp['amount']);
        $this->assertSame('EUR', $comp['currency']);
    }

    public function test_eu261_halves_long_haul_compensation_for_delays_under_4_hours(): void
    {
        $this->fakeAirportsOnly();

        $comp = $this->calculate('EU261', 'Article 7(1)', $this->context('FRA', 'YUL', delay: 200));

        $this->assertSame(300.0, $comp['amount']);
        $this->assertStringContainsString('reduced 50%', $comp['basis']);
    }

    public function test_uk261_pays_gbp_tier_for_short_haul(): void
    {
        $this->fakeAirportsOnly();

        $comp = $this->calculate('UK261', 'Article 7(1)', $this->context('LHR', 'AMS', delay: 240));

        $this->assertSame(220.0, $comp['amount']);
        $this->assertSame('GBP', $comp['currency']);
    }

    public function test_appr_pays_by_delay_tier(): void
    {
        $this->fakeAirportsOnly();

        $comp = $this->calculate('APPR', 'Section 19(1)(a)', $this->context('FRA', 'YUL', delay: 400));

        $this->assertSame(700.0, $comp['amount']); // 6-9h tier
        $this->assertSame('CAD', $comp['currency']);
    }

    public function test_eu_downgrade_pays_percentage_of_fare(): void
    {
        $this->fakeAirportsOnly();

        $context = $this->context('FRA', 'YUL', delay: 0, reported: 'downgrade');
        $comp    = $this->calculate('EU261', 'Article 10', $context, ticketPrice: 1000.0, ticketCurrency: 'EUR');

        $this->assertSame(750.0, $comp['amount']); // 75% long-haul
    }

    public function test_us_dot_refund_has_no_amount_without_a_fare_on_file(): void
    {
        $this->fakeAirportsOnly();

        $comp = $this->calculate('US_DOT', '14 CFR Part 260', $this->context('JFK', 'JFK', delay: 0, cancelled: true));

        $this->assertNull($comp['amount']);
        $this->assertStringContainsString('refund', strtolower($comp['basis']));
    }

    // ── End-to-end claim evaluation ─────────────────────────

    public function test_verified_long_delay_makes_the_claim_eligible_with_compensation(): void
    {
        $this->fakeFlightAndAirports(arrivalDelaySeconds: 260 * 60);

        $claim = $this->claim(['disruption_type' => 'delayed']);
        app(ClaimEligibilityService::class)->evaluate($claim);
        $claim->refresh();

        $this->assertNotNull($claim->flight_verified_at);
        $this->assertSame(Claim::STATUS_ELIGIBLE, $claim->status);
        $this->assertSame('EU261', $claim->eligibility_regulation);
        $this->assertSame(600.0, (float) $claim->compensation_amount);
        $this->assertSame('EUR', $claim->compensation_currency);
        $this->assertTrue($claim->events()->where('label', 'like', '%Eligible under EU261%')->exists());
    }

    public function test_unverifiable_old_flight_is_judged_on_declared_facts_and_reviewed(): void
    {
        // FlightAware history doesn't reach the flight: empty result.
        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                return $this->airportResponse($m[1]);
            }

            return Http::response(['flights' => []], 200);
        });

        $claim = $this->claim(['disruption_type' => 'cancelled', 'flight_date' => now()->subMonths(3)->toDateString()]);
        app(ClaimEligibilityService::class)->evaluate($claim);
        $claim->refresh();

        $this->assertNull($claim->flight_verified_at);
        $this->assertFalse((bool) data_get($claim->eligibility_details, 'facts_verified'));
        // Declared cancellation at reduced confidence -> below threshold -> human review.
        $this->assertSame(Claim::STATUS_PENDING_ELIGIBILITY, $claim->status);
        $this->assertSame('review', $claim->eligibility_status);
    }

    public function test_manual_claim_creation_evaluates_synchronously(): void
    {
        $this->fakeFlightAndAirports(arrivalDelaySeconds: 260 * 60);
        Role::findOrCreate('user');
        $this->user->assignRole('user');

        $this->actingAs($this->user)->postJson(route('user.itineraries.api.claims.store'), [
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'YUL',
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC845',
            'flight_date'       => now()->subDays(3)->toDateString(),
            'disruption_type'   => 'delayed',
            'passenger_name'    => 'Test Passenger',
            'ticket_price'      => 450,
            'ticket_currency'   => 'eur',
        ])->assertCreated()
            ->assertJsonPath('data.eligibility.regulation', 'EU261')
            ->assertJsonPath('data.compensation.amount', '600.00')
            ->assertJsonPath('data.flight_verified', true);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function context(string $from, string $to, int $delay, bool $cancelled = false, ?string $reported = null): EligibilityContext
    {
        return new EligibilityContext(
            ref: 'claim:test',
            airline: 'Test Air',
            flightNumber: 'TA1',
            flightDate: now()->subDay(),
            departureAirport: $from,
            arrivalAirport: $to,
            originCountry: self::GEO[$from][0],
            destinationCountry: self::GEO[$to][0],
            cancelled: $cancelled,
            arrivalDelayMinutes: $delay,
            delayIsActual: true,
            reportedDisruption: $reported,
        );
    }

    private function calculate(string $regulation, string $article, EligibilityContext $context, ?float $ticketPrice = null, ?string $ticketCurrency = null): ?array
    {
        $verdict = new EligibilityResult($regulation, true, $article, 85, 'Eligible.');

        return app(CompensationCalculator::class)->calculate($verdict, $context, $ticketPrice, $ticketCurrency);
    }

    private function claim(array $attrs = []): Claim
    {
        return Claim::create($attrs + [
            'user_id'           => $this->user->id,
            'status'            => Claim::STATUS_DRAFT,
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC845',
            'departure_airport' => 'FRA',
            'arrival_airport'   => 'YUL',
            'flight_date'       => now()->subDays(3)->toDateString(),
            'passenger_name'    => 'Test Passenger',
        ]);
    }

    private function airportResponse(string $code)
    {
        $geo = self::GEO[$code] ?? null;

        return $geo
            ? Http::response(['code_iata' => $code, 'country_code' => $geo[0], 'latitude' => $geo[1], 'longitude' => $geo[2]], 200)
            : Http::response([], 404);
    }

    private function fakeAirportsOnly(): void
    {
        Http::fake(fn ($request) => preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)
            ? $this->airportResponse($m[1])
            : Http::response([], 200));
    }

    private function fakeFlightAndAirports(int $arrivalDelaySeconds): void
    {
        Http::fake(function ($request) use ($arrivalDelaySeconds) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                return $this->airportResponse($m[1]);
            }

            return Http::response(['flights' => [[
                'fa_flight_id'  => 'ACA845-1700000000-airline-0001',
                'ident_iata'    => 'AC845',
                'origin'        => ['code_iata' => 'FRA'],
                'destination'   => ['code_iata' => 'YUL'],
                'scheduled_out' => now()->subDays(3)->setTime(12, 0)->toIso8601String(),
                'actual_in'     => now()->subDays(3)->setTime(22, 0)->toIso8601String(),
                'arrival_delay' => $arrivalDelaySeconds,
                'cancelled'     => false,
                'status'        => 'Arrived',
            ]]], 200);
        });
    }
}
