<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Trip;
use App\Models\User;
use App\Services\Eligibility\ClaimEligibilityService;
use App\Services\Eligibility\CompensationCalculator;
use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityEngine;
use App\Services\Eligibility\EligibilityResult;
use App\Services\Eligibility\RegulationCitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Eligibility Engine validation pass: the boundaries and invariants the
 * main engine/calculator suites do not pin down explicitly - the exact
 * delay threshold edges (2:59 / 3:00 / 3:01), statutory amounts being
 * immune to ticket price, every EU/UK distance band, cancellation being
 * judged separately from delay, fail-safe behaviour on missing data,
 * deterministic re-evaluation, and the closed door on non-eligible claims.
 *
 * Validation only - no business rules are changed here. The configured
 * ruleset (config/eligibility.php + Rules/*) is the source of truth.
 */
class EligibilityValidationTest extends TestCase
{
    use RefreshDatabase;

    /** code => [country, lat, lng] - drives both jurisdiction and distance. */
    private const GEO = [
        'FRA' => ['DE', 50.03, 8.54],   // Frankfurt
        'YUL' => ['CA', 45.47, -73.74], // Montreal  (FRA-YUL  ~5,850 km: long band)
        'LIS' => ['PT', 38.77, -9.13],  // Lisbon    (FRA-LIS  ~1,890 km: middle band)
        'LHR' => ['GB', 51.47, -0.46],  // London
        'AMS' => ['NL', 52.31, 4.76],   // Amsterdam (LHR-AMS  ~370 km: short band)
    ];

    private User $user;

    private ?int $flightDelaySeconds = null;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'services.flightaware.api_key' => 'test-key',
            'eligibility.evaluator'        => 'rules',
        ]);

        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $geo = self::GEO[$m[1]] ?? null;

                return $geo
                    ? Http::response(['code_iata' => $m[1], 'country_code' => $geo[0], 'latitude' => $geo[1], 'longitude' => $geo[2]], 200)
                    : Http::response(['detail' => 'Unknown airport'], 404);
            }

            // Flight verification: serves a verified flight when a test
            // arms it via fakeVerifiedFlight(), otherwise nothing found.
            if ($this->flightDelaySeconds !== null) {
                return Http::response(['flights' => [[
                    'fa_flight_id'  => 'ACA845-1700000000-airline-0001',
                    'ident_iata'    => 'AC845',
                    'origin'        => ['code_iata' => 'FRA'],
                    'destination'   => ['code_iata' => 'YUL'],
                    'scheduled_out' => now()->subDays(3)->setTime(12, 0)->toIso8601String(),
                    'actual_in'     => now()->subDays(3)->setTime(22, 0)->toIso8601String(),
                    'arrival_delay' => $this->flightDelaySeconds,
                    'cancelled'     => false,
                    'status'        => 'Arrived',
                ]]], 200);
            }

            return Http::response([], 200);
        });

        $this->user = User::factory()->create();
    }

    // ── 7. Delay threshold boundaries ───────────────────────

    public function test_the_three_hour_threshold_is_exact_at_179_180_and_181_minutes(): void
    {
        // The configured threshold is 3 hours (180 min) for every regime.
        foreach (['eu261', 'uk261', 'appr'] as $regime) {
            $this->assertSame(180, (int) config("eligibility.delay_thresholds.{$regime}", 180));
        }

        foreach ([0 => false, 20 => false, 179 => false, 180 => true, 181 => true, 400 => true] as $delay => $expected) {
            $result = $this->evaluateTrip('FRA', 'YUL', delay: $delay);

            $this->assertSame($expected, $result->eligible,
                "A {$delay}-minute arrival delay must be " . ($expected ? 'eligible' : 'refused'));

            if (!$expected && $delay > 0) {
                $this->assertStringContainsString('below the 3-hour threshold', $result->reason);
            }
        }
    }

    public function test_the_threshold_comes_from_configuration_not_a_hardcoded_number(): void
    {
        config(['eligibility.delay_thresholds.eu261' => 240]);

        // Intra-EU route: EU261 is the only applicable regime, so the raised
        // threshold decides. (On an EU-Canada route APPR, still at 180, would
        // rightly win instead - regimes are independent.)
        $this->assertFalse($this->evaluateTrip('FRA', 'LIS', delay: 200)->eligible,
            'With a 4-hour configured threshold, 200 minutes must be refused');
        $this->assertTrue($this->evaluateTrip('FRA', 'LIS', delay: 240)->eligible);
    }

    // ── 9. Ticket price never changes statutory amounts ─────

    public function test_statutory_compensation_is_immune_to_the_ticket_price(): void
    {
        foreach ([100.0, 1000.0, 5000.0] as $fare) {
            $comp = $this->calculate('EU261', 'Article 7(1)', $this->context('FRA', 'YUL', delay: 300), $fare, 'EUR');

            $this->assertSame(600.0, $comp['amount'],
                "EUR {$fare} ticket must not move the fixed EUR 600 long-haul amount");
            $this->assertSame('EUR', $comp['currency']);
        }

        // Fare-BASED remedies are the explicit exception the law defines:
        // an EU downgrade pays a percentage of what was actually paid.
        $downgrade = $this->context('FRA', 'YUL', delay: 0, reported: 'downgrade');
        $this->assertSame(75.0, $this->calculate('EU261', 'Article 10', $downgrade, 100.0, 'EUR')['amount']);
        $this->assertSame(3750.0, $this->calculate('EU261', 'Article 10', $downgrade, 5000.0, 'EUR')['amount']);
    }

    // ── 4 + 5. Every distance band, EU and UK ───────────────

    public function test_every_eu_and_uk_distance_band_pays_its_own_fixed_amount(): void
    {
        // EU261: 250 / 400 / 600 EUR by great-circle distance.
        $this->assertSame(250.0, $this->calculate('EU261', 'Article 7(1)', $this->context('LHR', 'AMS', delay: 240))['amount']);
        $this->assertSame(400.0, $this->calculate('EU261', 'Article 7(1)', $this->context('FRA', 'LIS', delay: 240))['amount']);
        $this->assertSame(600.0, $this->calculate('EU261', 'Article 7(1)', $this->context('FRA', 'YUL', delay: 300))['amount']);

        // UK261 is its own regime with its own GBP amounts: 220 / 350 / 520.
        $this->assertSame(220.0, $this->calculate('UK261', 'Article 7(1)', $this->context('LHR', 'AMS', delay: 240))['amount']);
        $this->assertSame(350.0, $this->calculate('UK261', 'Article 7(1)', $this->context('FRA', 'LIS', delay: 240))['amount']);
        $this->assertSame(520.0, $this->calculate('UK261', 'Article 7(1)', $this->context('FRA', 'YUL', delay: 300))['amount']);
        $this->assertSame('GBP', $this->calculate('UK261', 'Article 7(1)', $this->context('LHR', 'AMS', delay: 240))['currency']);
    }

    // ── 8. Cancellation is judged separately from delay ─────

    public function test_cancellation_is_not_treated_as_a_very_large_delay(): void
    {
        // Zero delay + cancellation: the delay threshold must not block it.
        $result = $this->evaluateTrip('FRA', 'YUL', delay: 0, cancelled: true);

        $this->assertTrue($result->eligible);
        $this->assertSame('EU261', $result->regulation);
        // The rule says "Articles 5 & 7", the canonical table "Articles 5
        // and 7" - the citation guard parses both to the same provisions.
        $this->assertMatchesRegularExpression('/5\s*(?:and|&)\s*7/', $result->article);
        $this->assertMatchesRegularExpression('/5\s*(?:and|&)\s*7/', RegulationCitation::article('EU261', 'cancelled'));
        $this->assertStringNotContainsString('below the 3-hour threshold', (string) $result->reason);

        // And its money comes from the distance band - not from delay tiers.
        $comp = $this->calculate('EU261', $result->article, $this->context('FRA', 'YUL', delay: 0, cancelled: true));
        $this->assertSame(600.0, $comp['amount']);
    }

    // ── 16. Citations come from the canonical table ─────────

    public function test_appr_compensation_cites_section_19_never_section_17(): void
    {
        foreach (['delayed', 'cancelled', 'schedule_change', 'missed_connection'] as $scenario) {
            $article = RegulationCitation::article('APPR', $scenario);

            $this->assertStringContainsString('Section 19', $article);
            $this->assertStringNotContainsString('17', $article,
                'APPR compensation flows from s.19 - s.17 is the wrong citation');
        }

        // The canonical table is also the only source the drafts may use.
        $this->assertNotEmpty(RegulationCitation::allowed('APPR'));
    }

    // ── 20. Missing / invalid data fails safe ───────────────

    public function test_missing_or_invalid_data_is_never_silently_eligible(): void
    {
        // Unknown airports: no jurisdiction, no verdict - and the trip is
        // stored as rejected, never as eligible.
        $trip = $this->disruptedTrip('QQQ', 'ZZZ', delay: 400);
        $this->assertNull(app(EligibilityEngine::class)->evaluate($trip));
        $trip->refresh();
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertStringContainsString('No air passenger rights regulation covers this route', $trip->eligibility_reason);

        // A nonsense negative delay: refused, not crashed.
        $negative = $this->evaluateTrip('FRA', 'YUL', delay: -30);
        $this->assertFalse($negative->eligible);

        // Unverified facts cap confidence so a human reviews the claim
        // rather than the engine auto-approving on hearsay.
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 400, actualTimes: false);
        $trip->forceFill(['reported_disruption' => 'delayed'])->save();
        $result = app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();
        $this->assertLessThanOrEqual(75, $result->confidence, 'Passenger-reported facts cap confidence at 75');
    }

    // ── 12 + 19. Direct claims evaluate deterministically ───

    public function test_direct_claim_reevaluation_is_deterministic_and_never_duplicates(): void
    {
        Mail::fake();
        $this->fakeVerifiedFlight(arrivalDelaySeconds: 260 * 60);

        $claim = $this->claim(['disruption_type' => 'delayed']);
        app(ClaimEligibilityService::class)->evaluate($claim);
        $first = $claim->fresh()->only(['status', 'eligibility_regulation', 'eligibility_article', 'compensation_amount', 'compensation_currency', 'eligibility_confidence']);

        app(ClaimEligibilityService::class)->evaluate($claim->fresh());
        $second = $claim->fresh()->only(['status', 'eligibility_regulation', 'eligibility_article', 'compensation_amount', 'compensation_currency', 'eligibility_confidence']);

        $this->assertSame($first, $second, 'Identical input must produce the identical verdict');
        $this->assertSame(Claim::STATUS_ELIGIBLE, $second['status']);
        $this->assertSame('600.00', $second['compensation_amount']);
        $this->assertSame(1, Claim::count(), 'Re-evaluation must never mint another claim');
    }

    // ── 18. Negative cases stay closed ──────────────────────

    public function test_a_rejected_claim_cannot_be_confirmed_and_creates_nothing(): void
    {
        Mail::fake();
        Role::findOrCreate('user');
        $this->user->assignRole('user');
        $this->fakeVerifiedFlight(arrivalDelaySeconds: 60 * 60); // 1h: below every threshold

        $claim = $this->claim(['disruption_type' => 'delayed']);
        app(ClaimEligibilityService::class)->evaluate($claim);
        $claim->refresh();

        $this->assertSame(Claim::STATUS_REJECTED, $claim->status);
        $this->assertNotNull($claim->eligibility_reason);
        $this->assertNull($claim->compensation_amount, 'A refused claim carries no compensation promise');

        // The confirmation door is closed - no consents, no signers, no POA.
        $this->actingAs($this->user)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($claim->id)), [
                'consents' => ['accuracy' => true, 'authorization' => true, 'terms' => true, 'privacy' => true],
            ])->assertStatus(422);

        $this->assertSame(0, $claim->signers()->count());
        $this->assertNull($claim->fresh()->confirmed_at);

        // And nobody was told they are owed money.
        Mail::assertNotSent(\App\Mail\GenericEmail::class, fn ($mail) => str_contains($mail->htmlBody ?? '', 'Claim it now'));
    }

    // ── 22. The AI is never consulted on the law ────────────

    public function test_rules_evaluation_never_calls_the_ai(): void
    {
        Mail::fake();
        $this->fakeVerifiedFlight(arrivalDelaySeconds: 260 * 60);

        $claim = $this->claim(['disruption_type' => 'delayed']);
        app(ClaimEligibilityService::class)->evaluate($claim);

        $this->assertSame(Claim::STATUS_ELIGIBLE, $claim->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'),
            'Eligibility, regulation and citation are the engine\'s alone - no AI call may occur');
    }

    // ── Fixtures ────────────────────────────────────────────

    private function evaluateTrip(string $from, string $to, int $delay, bool $cancelled = false): EligibilityResult
    {
        $result = app(EligibilityEngine::class)->evaluate($this->disruptedTrip($from, $to, $delay, $cancelled));

        $this->assertNotNull($result, 'The engine must always return a verdict');

        return $result;
    }

    private function disruptedTrip(string $from, string $to, int $delay, bool $cancelled = false, bool $actualTimes = true): Trip
    {
        return Trip::create([
            'user_id'               => $this->user->id,
            'source'                => Trip::SOURCE_MANUAL,
            'status'                => Trip::STATUS_PROTECTED,
            'airline'               => 'Test Air',
            'flight_number'         => 'TA123',
            'departure_airport'     => $from,
            'arrival_airport'       => $to,
            'departure_date'        => now()->subDay()->toDateString(),
            'flight_status'         => $cancelled ? Trip::FLIGHT_CANCELLED : Trip::FLIGHT_COMPLETED,
            'monitoring_status'     => Trip::MONITORING_COMPLETED,
            'potentially_eligible'  => true,
            'arrival_delay_minutes' => $delay,
            'actual_arrival'        => $actualTimes && !$cancelled ? now()->subHours(2) : null,
        ]);
    }

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

    /** Arm the shared HTTP fake to verify the claim's flight. */
    private function fakeVerifiedFlight(int $arrivalDelaySeconds): void
    {
        $this->flightDelaySeconds = $arrivalDelaySeconds;
    }
}
