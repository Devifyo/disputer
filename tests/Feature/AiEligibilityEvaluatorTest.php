<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripEligibleForCompensation;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * AI-powered eligibility evaluation: Gemini verdicts are used when valid,
 * and anything malformed silently falls back to the rule-based evaluator.
 */
class AiEligibilityEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'services.flightaware.api_key' => 'test-key',
            'services.gemini.api_key'      => 'test-gemini-key',
            'eligibility.evaluator'        => 'ai',
        ]);

        $this->user = User::factory()->create();
    }

    public function test_valid_ai_verdict_is_used_and_recorded_as_ai(): void
    {
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [
            [
                'regulation' => 'EU261',
                'eligible'   => true,
                'article'    => 'Article 7(1)(c)',
                'confidence' => 88,
                'reason'     => 'The flight arrived over four hours late on a departure from an EU member state.',
                'factors'    => ['Delay confirmed from actual arrival time.'],
            ],
            [
                'regulation' => 'APPR',
                'eligible'   => true,
                'article'    => 'Section 19(1)(a)',
                'confidence' => 75,
                'reason'     => 'Arrival into Canada more than three hours late.',
            ],
        ]]));

        $trip   = $this->disruptedTrip();
        $result = app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame('EU261', $result->regulation);
        // The model's proposed citation is advisory - the canonical table
        // decides, so the stored article is the vetted one for a delay.
        $this->assertSame('Article 7', $trip->eligibility_article);
        $this->assertSame(88, $trip->eligibility_confidence);
        $this->assertSame('ai', $trip->eligibility_details['evaluated_by']);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
        Notification::assertSentTo($this->user, TripEligibleForCompensation::class);
    }

    public function test_malformed_ai_response_falls_back_to_rules(): void
    {
        $this->fakeApis(geminiJson: 'this is not json at all');

        $trip = $this->disruptedTrip();
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame('rules', $trip->eligibility_details['evaluated_by']);
        $this->assertSame('EU261', $trip->eligibility_regulation);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
    }

    public function test_invalid_ai_outcome_values_fall_back_to_rules(): void
    {
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [[
            'regulation' => 'EU999', // hallucinated regulation
            'eligible'   => true,
            'article'    => 'Article 7',
            'confidence' => 150,
            'reason'     => 'Nonsense.',
        ]]]));

        $trip = $this->disruptedTrip();
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame('rules', $trip->eligibility_details['evaluated_by']);
        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
    }

    public function test_ai_outcome_for_wrong_jurisdiction_is_dropped(): void
    {
        // US domestic flight — the AI hallucinates EU261 coverage.
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [[
            'regulation' => 'EU261',
            'eligible'   => true,
            'article'    => 'Article 7(1)',
            'confidence' => 95,
            'reason'     => 'Hallucinated EU coverage.',
        ]]]));

        $trip = $this->disruptedTrip(from: 'JFK', to: 'LAX');
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame('US_DOT', $trip->eligibility_regulation);
        $this->assertSame(EligibilityEngine::STATUS_REJECTED, $trip->eligibility_status);
        $this->assertSame('ai+rules', $trip->eligibility_details['evaluated_by']);
        Notification::assertNothingSent();
    }

    public function test_regulation_omitted_by_ai_is_backfilled_from_rules(): void
    {
        // FRA→YUL is covered by EU261 and APPR; the AI only reports APPR.
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [[
            'regulation' => 'APPR',
            'eligible'   => true,
            'article'    => 'Section 19(1)(a)',
            'confidence' => 70,
            'reason'     => 'Late arrival into Canada.',
        ]]]));

        $trip = $this->disruptedTrip();
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame('EU261', $trip->eligibility_regulation); // stronger regime restored
        $this->assertSame('ai+rules', $trip->eligibility_details['evaluated_by']);
    }

    public function test_empty_ai_outcomes_do_not_reject_a_covered_route(): void
    {
        $this->fakeApis(geminiJson: json_encode(['outcomes' => []]));

        $trip = $this->disruptedTrip();
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame(EligibilityEngine::STATUS_ELIGIBLE, $trip->eligibility_status);
        $this->assertSame('EU261', $trip->eligibility_regulation);
        $this->assertSame('ai+rules', $trip->eligibility_details['evaluated_by']);
    }

    public function test_duplicate_ai_outcomes_collapse_to_the_most_confident(): void
    {
        $outcome = fn (int $confidence) => [
            'regulation' => 'EU261', 'eligible' => true, 'article' => 'Article 7(1)',
            'confidence' => $confidence, 'reason' => 'Long delay on EU departure.',
        ];
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [$outcome(60), $outcome(90)]]));

        $trip = $this->disruptedTrip();
        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $eu = collect($trip->eligibility_details['outcomes'])->where('regulation', 'EU261');
        $this->assertCount(1, $eu);
        $this->assertSame(90, $eu->first()['confidence']);
    }

    public function test_reported_disruption_confidence_is_capped_at_75(): void
    {
        // The AI is overconfident about facts only the passenger attests to.
        $this->fakeApis(geminiJson: json_encode(['outcomes' => [[
            'regulation' => 'EU261',
            'eligible'   => true,
            'article'    => 'Articles 4 & 7',
            'confidence' => 95,
            'reason'     => 'Clear involuntary denied boarding.',
        ]]]));

        $trip = $this->disruptedTrip();
        $trip->forceFill(['reported_disruption' => 'denied_boarding'])->save();

        app(EligibilityEngine::class)->evaluate($trip);
        $trip->refresh();

        $this->assertSame(75, $trip->eligibility_confidence);
        $outcome = collect($trip->eligibility_details['outcomes'])->firstWhere('regulation', 'EU261');
        $this->assertContains('Confidence capped: the decisive facts cannot be fully verified automatically.', $outcome['factors']);
    }

    public function test_rules_mode_never_calls_gemini(): void
    {
        config(['eligibility.evaluator' => 'rules']);
        $this->fakeApis(geminiJson: '{}');

        app(EligibilityEngine::class)->evaluate($this->disruptedTrip());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage'));
    }

    // ── Helpers ─────────────────────────────────────────────

    private function fakeApis(string $geminiJson): void
    {
        Http::fake(function ($request) use ($geminiJson) {
            if (str_contains($request->url(), 'generativelanguage')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $geminiJson]]]]],
                ], 200);
            }

            if (preg_match('#/airports/([A-Z0-9]+)#', $request->url(), $m)) {
                $country = ['FRA' => 'DE', 'YUL' => 'CA', 'JFK' => 'US', 'LAX' => 'US'][$m[1]] ?? null;

                return Http::response(['code_iata' => $m[1], 'country_code' => $country], $country ? 200 : 404);
            }

            return Http::response([], 200);
        });
    }

    private function disruptedTrip(string $from = 'FRA', string $to = 'YUL'): Trip
    {
        return Trip::create([
            'user_id'               => $this->user->id,
            'source'                => Trip::SOURCE_MANUAL,
            'status'                => Trip::STATUS_PROTECTED,
            'airline'               => 'Air Canada',
            'flight_number'         => 'AC845',
            'departure_airport'     => $from,
            'arrival_airport'       => $to,
            'departure_date'        => now()->subDay()->toDateString(),
            'fa_flight_id'          => 'ACA845-1700000000-airline-0001',
            'flight_status'         => Trip::FLIGHT_COMPLETED,
            'monitoring_status'     => Trip::MONITORING_COMPLETED,
            'potentially_eligible'  => true,
            'arrival_delay_minutes' => 250,
            'actual_arrival'        => now()->subHours(2),
        ]);
    }
}
