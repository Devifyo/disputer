<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Eligibility Engine — automatic APPR / EU261 / UK261 / US DOT evaluation
 * of disrupted monitored trips, with confidence scoring and the
 * admin-configurable auto-reject threshold.
 */
class EligibilityEngineTest extends TestCase
{
    use RefreshDatabase;

    private const COUNTRIES = [
        'FRA' => 'DE', 'CDG' => 'FR', 'LHR' => 'GB', 'YUL' => 'CA',
        'YYZ' => 'CA', 'JFK' => 'US', 'LAX' => 'US', 'DXB' => 'AE',
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'services.flightaware.api_key' => 'test-key',
            'eligibility.evaluator'        => 'rules', // pin the deterministic path; AI has its own test
        ]);

        // Airport lookups resolve from the static map above.
        Http::fake(function ($request) {
            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $country = self::COUNTRIES[$m[1]] ?? null;

                return $country
                    ? Http::response(['code_iata' => $m[1], 'country_code' => $country], 200)
                    : Http::response(['detail' => 'Unknown airport'], 404);
            }

            return Http::response([], 200);
        });

        $this->user = User::factory()->create();
    }

    // ── Regulation & article detection ──────────────────────

    public function test_eu_departure_long_delay_is_eligible_under_eu261_article_7(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 240);

        $result = $this->evaluate($trip);

        $this->assertSame('EU261', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('Article 7(1)', $result->article);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
        $this->assertSame('eligible', $trip->displayStatus());
        Notification::assertSentTo($this->user, TripEligibleForCompensation::class);
    }

    public function test_uk_departure_long_delay_is_eligible_under_uk261(): void
    {
        $trip = $this->disruptedTrip('LHR', 'DXB', delay: 200);

        $result = $this->evaluate($trip);

        $this->assertSame('UK261', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
    }

    public function test_canadian_route_long_delay_is_eligible_under_appr_section_19(): void
    {
        $trip = $this->disruptedTrip('YYZ', 'DXB', delay: 400); // 6–9h tier

        $result = $this->evaluate($trip);

        $this->assertSame('APPR', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('Section 19(1)(a)', $result->article);
        $this->assertStringContainsString('6–9 hours', $result->reason);
    }

    public function test_us_domestic_delay_is_rejected_because_dot_mandates_no_compensation(): void
    {
        $trip = $this->disruptedTrip('JFK', 'LAX', delay: 300);

        $result = $this->evaluate($trip);

        $this->assertSame('US_DOT', $result->regulation);
        $this->assertFalse($result->eligible);
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertSame('not_eligible', $trip->displayStatus());
        $this->assertStringContainsString('do not mandate cash compensation', $trip->eligibility_reason);
        Notification::assertNothingSent();
    }

    public function test_us_cancellation_is_eligible_for_a_refund(): void
    {
        $trip = $this->disruptedTrip('JFK', 'LAX', delay: 0, cancelled: true);

        $result = $this->evaluate($trip);

        $this->assertSame('US_DOT', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('14 CFR Part 260', $result->article);
        $this->assertStringContainsString('refund', $result->reason);
    }

    public function test_cancellation_from_eu_airport_cites_articles_5_and_7(): void
    {
        $trip = $this->disruptedTrip('CDG', 'JFK', delay: 0, cancelled: true);

        $result = $this->evaluate($trip);

        $this->assertSame('EU261', $result->regulation);
        $this->assertSame('Articles 5 & 7', $result->article);
        $this->assertTrue($result->eligible);
    }

    public function test_short_delay_is_rejected_with_threshold_explanation(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 120);

        $result = $this->evaluate($trip);

        $this->assertFalse($result->eligible);
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertStringContainsString('below the 3-hour threshold', $trip->eligibility_reason);
    }

    public function test_uncovered_route_is_rejected_as_out_of_scope(): void
    {
        $trip = $this->disruptedTrip('DXB', 'DXB', delay: 400);

        $this->assertNull($this->evaluate($trip));
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertStringContainsString('No air passenger rights regulation', $trip->eligibility_reason);
    }

    // ── Diversions & passenger-reported disruptions ─────────

    public function test_diverted_flight_is_eligible_under_eu261_articles_8_and_7(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 0);
        $trip->forceFill(['diverted' => true, 'flight_status' => Trip::FLIGHT_DIVERTED])->save();

        $result = $this->evaluate($trip);

        $this->assertSame('EU261', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('Articles 8 & 7', $result->article);
    }

    public function test_denied_boarding_cites_article_4_on_eu_routes(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 0);
        $trip->forceFill(['reported_disruption' => 'denied_boarding'])->save();

        $result = $this->evaluate($trip);

        $this->assertSame('EU261', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('Articles 4 & 7', $result->article);
        $this->assertStringContainsString('denied boarding', strtolower($result->reason));
    }

    public function test_denied_boarding_on_us_route_is_compensable_under_part_250(): void
    {
        $trip = $this->disruptedTrip('JFK', 'LAX', delay: 0);
        $trip->forceFill(['reported_disruption' => 'denied_boarding'])->save();

        $result = $this->evaluate($trip);

        $this->assertSame('US_DOT', $result->regulation);
        $this->assertTrue($result->eligible);
        $this->assertSame('14 CFR Part 250', $result->article);
    }

    public function test_downgrade_cites_article_10(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 0);
        $trip->forceFill(['reported_disruption' => 'downgrade'])->save();

        $result = $this->evaluate($trip);

        $this->assertSame('EU261', $result->regulation);
        $this->assertSame('Article 10', $result->article);
        $this->assertStringContainsString('30-75%', $result->reason);
    }

    public function test_missed_connection_cites_the_folkerts_doctrine(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 0);
        $trip->forceFill(['reported_disruption' => 'missed_connection'])->save();

        $result = $this->evaluate($trip);

        $this->assertTrue($result->eligible);
        $this->assertStringContainsString('Folkerts', $result->article);
    }

    public function test_other_reports_always_reach_the_review_queue(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 0);
        $trip->forceFill(['reported_disruption' => 'other'])->save();

        $this->evaluate($trip);

        $this->assertSame(EligibilityEngine::STATUS_REVIEW, $trip->eligibility_status);
        $this->assertSame('eligibility_review_pending', $trip->displayStatus());
    }

    // ── Confidence scoring & threshold ──────────────────────

    public function test_estimated_delay_scores_lower_confidence_than_actual(): void
    {
        $actual    = $this->evaluate($this->disruptedTrip('FRA', 'YUL', delay: 240));
        $estimated = $this->evaluate($this->disruptedTrip('FRA', 'YUL', delay: 240, actualTimes: false));

        $this->assertGreaterThan($estimated->confidence, $actual->confidence);
    }

    public function test_eligible_verdict_below_admin_threshold_goes_to_manual_review(): void
    {
        Setting::set(EligibilityEngine::SETTING_THRESHOLD, 95);

        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 185, actualTimes: false); // borderline + estimated

        $result = $this->evaluate($trip);

        $this->assertTrue($result->eligible); // the rule says eligible…
        $this->assertSame(EligibilityEngine::STATUS_REVIEW, $trip->eligibility_status); // …confidence routes it to a human
        $this->assertSame('eligibility_review_pending', $trip->displayStatus());

        // The customer sees a friendly explanation; thresholds stay internal.
        $this->assertStringContainsString('Our team is verifying the details', $trip->eligibility_reason);
        $this->assertStringNotContainsString('threshold', $trip->eligibility_reason);
        $this->assertStringContainsString('below the 95% threshold', $trip->eligibility_details['auto_review']);

        Notification::assertNothingSent(); // "eligible" mail only goes out once confirmed
    }

    public function test_admin_threshold_is_read_from_settings(): void
    {
        $this->assertSame(config('eligibility.default_confidence_threshold'), EligibilityEngine::confidenceThreshold());

        Setting::set(EligibilityEngine::SETTING_THRESHOLD, 42);
        $this->assertSame(42, EligibilityEngine::confidenceThreshold());
    }

    public function test_evaluation_records_an_eligibility_event_with_all_outcomes(): void
    {
        $trip = $this->disruptedTrip('FRA', 'YUL', delay: 240); // EU261 + APPR both apply

        $this->evaluate($trip);

        $event = $trip->events()->where('type', TripEvent::TYPE_ELIGIBILITY)->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->qualifying);

        $outcomes = collect($trip->eligibility_details['outcomes'])->pluck('regulation');
        $this->assertContains('EU261', $outcomes);
        $this->assertContains('APPR', $outcomes);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function evaluate(Trip $trip)
    {
        $result = app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

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
}
