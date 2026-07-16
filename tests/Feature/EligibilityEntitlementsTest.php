<?php

namespace Tests\Feature;

use App\Services\Eligibility\CompensationCalculator;
use App\Services\Eligibility\EligibilityContext;
use App\Services\Eligibility\EligibilityEngine;
use App\Services\Eligibility\EligibilityEvaluator;
use App\Services\Eligibility\EligibilityResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Configurable stand-in for an external eligibility provider. */
class FakeEligibilityProvider implements EligibilityEvaluator
{
    public static array $flags = [];
    public static bool $broken = false;
    public static ?array $results = null;

    public function name(): string
    {
        return 'fake';
    }

    public function evaluate(EligibilityContext $context): array
    {
        if (self::$broken) {
            throw new RuntimeException('Provider unavailable.');
        }

        return self::$results
            ?? [new EligibilityResult('EU261', true, 'Article 7(1)', 95, 'Eligible for compensation.', [], self::$flags)];
    }
}

/**
 * Provider-based engine plumbing, evaluator advisory flags, separate
 * entitlements (compensation / refund / re-routing / expenses) and the
 * cancellation-equivalent disruption types.
 */
class EligibilityEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private const COUNTRIES = ['FRA' => 'DE', 'YUL' => 'CA', 'YYZ' => 'CA', 'JFK' => 'US', 'LHR' => 'GB'];

    protected function setUp(): void
    {
        parent::setUp();

        FakeEligibilityProvider::$flags   = [];
        FakeEligibilityProvider::$broken  = false;
        FakeEligibilityProvider::$results = null;

        config([
            'services.flightaware.api_key'      => 'test-key',
            'eligibility.evaluator'             => 'fake',
            'eligibility.providers.fake'        => FakeEligibilityProvider::class,
        ]);

        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $country = self::COUNTRIES[$m[1]] ?? null;

                return $country
                    ? Http::response(['code_iata' => $m[1], 'country_code' => $country], 200)
                    : Http::response(['detail' => 'Unknown airport'], 404);
            }

            return Http::response([], 200);
        });
    }

    private function context(array $overrides = []): EligibilityContext
    {
        return new EligibilityContext(...array_merge([
            'ref'                 => 'test:1',
            'airline'             => 'Test Air',
            'flightNumber'        => 'TA100',
            'flightDate'          => now()->subDays(2),
            'departureAirport'    => 'FRA',
            'arrivalAirport'      => 'YUL',
            'originCountry'       => 'DE',
            'destinationCountry'  => 'CA',
            'cancelled'           => false,
            'arrivalDelayMinutes' => 0,
            'delayIsActual'       => true,
        ], $overrides));
    }

    // ── Provider plumbing ───────────────────────────────────

    public function test_configured_provider_evaluates_and_is_recorded(): void
    {
        $decision = app(EligibilityEngine::class)->decide($this->context(['cancelled' => true]));

        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $decision['status']);
        $this->assertStringContainsString('fake', $decision['details']['evaluated_by']);
    }

    public function test_broken_provider_falls_back_to_rules(): void
    {
        FakeEligibilityProvider::$broken = true;

        $decision = app(EligibilityEngine::class)->decide($this->context(['cancelled' => true]));

        $this->assertSame('rules', $decision['details']['evaluated_by']);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $decision['status']);
        $this->assertSame('EU261', $decision['best']->regulation);
    }

    // ── Evaluator advisory flags ────────────────────────────

    public function test_manual_review_recommendation_forces_human_review_despite_high_confidence(): void
    {
        FakeEligibilityProvider::$flags = ['manual_review_recommended' => true];

        $decision = app(EligibilityEngine::class)->decide($this->context(['cancelled' => true]));

        $this->assertSame(EligibilityEngine::STATUS_REVIEW, $decision['status']);
        $this->assertStringContainsString('recommended a manual check', $decision['details']['auto_review']);
    }

    public function test_flags_are_kept_in_the_audit_trail(): void
    {
        FakeEligibilityProvider::$flags = ['refund_recommended' => true, 'expenses_recommended' => true];

        $decision = app(EligibilityEngine::class)->decide($this->context(['cancelled' => true]));

        $outcome = collect($decision['details']['outcomes'])->firstWhere('regulation', 'EU261');
        $this->assertTrue($outcome['flags']['refund_recommended']);
        $this->assertTrue($outcome['flags']['expenses_recommended']);
    }

    public function test_low_confidence_rejection_is_flagged_for_review_not_final(): void
    {
        FakeEligibilityProvider::$results = [
            new EligibilityResult('EU261', false, 'Article 7', 40, 'Probably not eligible - cause unknown.'),
        ];

        $decision = app(EligibilityEngine::class)->decide($this->context([
            'factsVerified' => false, 'reportedDisruption' => 'returned_to_origin',
        ]));

        $this->assertSame(EligibilityEngine::STATUS_REVIEW, $decision['status']);
    }

    public function test_confident_rejection_stays_rejected(): void
    {
        FakeEligibilityProvider::$results = [
            new EligibilityResult('EU261', false, 'Article 7', 90, 'Delay below the 3-hour threshold.'),
        ];

        $decision = app(EligibilityEngine::class)->decide($this->context());

        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $decision['status']);
    }

    // ── Separate entitlements ───────────────────────────────

    private function entitlement(array $result, string $key): array
    {
        return collect($result['breakdown']['entitlements'])->firstWhere('key', $key);
    }

    public function test_appr_refund_chosen_lists_refund_on_top_of_compensation(): void
    {
        $verdict = new EligibilityResult('APPR', true, 's.19(2)', 80, 'Eligible.');
        $context = $this->context([
            'departureAirport' => 'YYZ', 'arrivalAirport' => 'JFK',
            'originCountry' => 'CA', 'destinationCountry' => 'US',
            'cancelled' => true, 'didNotTravel' => true,
        ]);

        $result = app(CompensationCalculator::class)->calculate($verdict, $context, 310.0, 'CAD');

        $this->assertSame(400.0, $result['amount']);
        $this->assertSame('included', $this->entitlement($result, 'compensation')['state']);
        $this->assertSame('included', $this->entitlement($result, 'refund')['state']);
        $this->assertStringContainsString('310.00', $this->entitlement($result, 'refund')['value']);
        $this->assertSame('none', $this->entitlement($result, 'rerouting')['state']);
        $this->assertSame('included', $this->entitlement($result, 'expenses')['state']);
    }

    public function test_eu_cancellation_where_passenger_travelled_offers_rerouting_not_refund(): void
    {
        $verdict = new EligibilityResult('EU261', true, 'Articles 5 & 7', 85, 'Eligible.');
        $context = $this->context(['cancelled' => true]);

        $result = app(CompensationCalculator::class)->calculate($verdict, $context);

        $this->assertSame('none', $this->entitlement($result, 'refund')['state']);
        $this->assertSame('included', $this->entitlement($result, 'rerouting')['state']);
        $this->assertSame('included', $this->entitlement($result, 'expenses')['state']);
    }

    public function test_us_dot_cancellation_mandates_refund_not_compensation(): void
    {
        $verdict = new EligibilityResult('US_DOT', true, '14 CFR Part 260', 85, 'Refund due.');
        $context = $this->context([
            'departureAirport' => 'JFK', 'arrivalAirport' => 'YYZ',
            'originCountry' => 'US', 'destinationCountry' => 'CA',
            'cancelled' => true, 'didNotTravel' => true,
        ]);

        $result = app(CompensationCalculator::class)->calculate($verdict, $context, 500.0, 'USD');

        $this->assertSame('none', $this->entitlement($result, 'compensation')['state']);
        $this->assertSame('included', $this->entitlement($result, 'refund')['state']);
    }

    // ── Cancellation-equivalent disruption types ────────────

    public function test_schedule_change_is_eligible_under_eu_rules(): void
    {
        config(['eligibility.evaluator' => 'rules']);

        $decision = app(EligibilityEngine::class)->decide($this->context([
            'factsVerified' => false, 'reportedDisruption' => 'schedule_change',
        ]));

        $this->assertTrue($decision['best']->eligible);
        $this->assertSame('EU261', $decision['best']->regulation);
        $this->assertSame('Articles 5 & 7', $decision['best']->article);
    }

    public function test_flight_returned_to_origin_is_treated_as_a_cancellation(): void
    {
        config(['eligibility.evaluator' => 'rules']);

        $decision = app(EligibilityEngine::class)->decide($this->context([
            'factsVerified' => false, 'reportedDisruption' => 'returned_to_origin', 'didNotTravel' => true,
        ]));

        $this->assertTrue($decision['best']->eligible);

        $result = app(CompensationCalculator::class)->calculate(
            $decision['best'],
            $this->context(['factsVerified' => false, 'reportedDisruption' => 'returned_to_origin', 'didNotTravel' => true]),
            800.0,
            'EUR',
        );

        // Priced as a cancellation: refund owed because the journey was abandoned.
        $this->assertSame('included', $this->entitlement($result, 'refund')['state']);
        $this->assertStringContainsString('800.00', $this->entitlement($result, 'refund')['value']);
    }
}
