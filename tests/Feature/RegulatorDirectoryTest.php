<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use App\Services\Claims\RegulatorDirectory;
use App\Services\Eligibility\EligibilityEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The competent regulator is resolved from the route, not guessed: APPR ->
 * CTA, UK261 -> CAA, US DOT -> DOT, and EU261 -> the National Enforcement
 * Body of the member state where the disruption happened.
 */
class RegulatorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('user');
        config(['services.gemini.api_key' => null]); // deterministic templates
    }

    private function claim(string $regulation, string $from, string $to): Claim
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return Claim::create([
            'user_id'                => $user->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'workflow_state'         => 'awaiting_escalation',
            'airline'                => 'Air France',
            'flight_number'          => 'AF1234',
            'departure_airport'      => $from,
            'arrival_airport'        => $to,
            'flight_date'            => '2026-07-10',
            'passenger_name'         => 'Tenzin Hagyal',
            'disruption_type'        => 'delayed',
            'eligibility_regulation' => $regulation,
            'eligibility_article'    => 'Article 7',
            'compensation_amount'    => '600.00',
            'compensation_currency'  => 'EUR',
        ]);
    }

    /** Airport -> country without hitting FlightAware. */
    private function fakeCountries(array $map): void
    {
        $engine = Mockery::mock(EligibilityEngine::class)->makePartial();
        $engine->shouldReceive('countryOf')->andReturnUsing(fn ($code) => $map[$code] ?? null);
        $this->app->instance(EligibilityEngine::class, $engine);
    }

    public function test_single_body_regimes_map_to_their_national_regulator(): void
    {
        $this->assertSame('CTA', RegulatorDirectory::for($this->claim('APPR', 'YYZ', 'IAD'))['code']);
        $this->assertSame('CAA', RegulatorDirectory::for($this->claim('UK261', 'LHR', 'JFK'))['code']);
        $this->assertSame('US DOT', RegulatorDirectory::for($this->claim('US_DOT', 'JFK', 'LAX'))['code']);
    }

    public function test_eu261_resolves_to_the_departure_states_enforcement_body(): void
    {
        $this->fakeCountries(['FRA' => 'DE', 'JFK' => 'US']);

        // An Air France flight out of Frankfurt is Germany's LBA, not the DGAC.
        $regulator = RegulatorDirectory::for($this->claim('EU261', 'FRA', 'JFK'));

        $this->assertSame('LBA', $regulator['code']);
        $this->assertSame('DE', $regulator['country']);
        $this->assertTrue($regulator['confident']);
        $this->assertStringContainsString('departed FRA', $regulator['reason']);
    }

    public function test_eu261_inbound_flight_falls_to_the_arrival_states_body(): void
    {
        $this->fakeCountries(['JFK' => 'US', 'MAD' => 'ES']);

        $regulator = RegulatorDirectory::for($this->claim('EU261', 'JFK', 'MAD'));

        $this->assertSame('AESA', $regulator['code']);
        $this->assertSame('ES', $regulator['country']);
    }

    public function test_route_outside_the_eu_is_flagged_for_the_admin_rather_than_guessed(): void
    {
        $this->fakeCountries(['JFK' => 'US', 'YYZ' => 'CA']);

        $regulator = RegulatorDirectory::for($this->claim('EU261', 'JFK', 'YYZ'));

        $this->assertFalse($regulator['confident']);
        $this->assertSame('', $regulator['code']);
        $this->assertStringContainsString('confirm which state is competent', $regulator['reason']);
    }

    public function test_complaint_draft_is_addressed_to_the_resolved_body(): void
    {
        $this->fakeCountries(['FRA' => 'DE', 'JFK' => 'US']);

        $draft = app(ClaimLetterService::class)->generate(
            $this->claim('EU261', 'FRA', 'JFK'),
            ClaimDraft::TYPE_REGULATOR
        );

        $this->assertStringContainsString('Luftfahrt-Bundesamt', $draft['body']);
        $this->assertStringContainsString('LBA complaint', $draft['subject']);
    }

    public function test_unresolved_regulator_leaves_the_draft_generic(): void
    {
        $this->fakeCountries(['JFK' => 'US', 'YYZ' => 'CA']);

        $draft = app(ClaimLetterService::class)->generate(
            $this->claim('EU261', 'JFK', 'YYZ'),
            ClaimDraft::TYPE_REGULATOR
        );

        $this->assertStringContainsString('Dear Sir or Madam', $draft['body']);
    }

    public function test_every_covered_country_resolves_to_a_named_body(): void
    {
        // No EU261 state may fall through to "confirm the authority" - the
        // config list and the NEB table must stay in step.
        foreach ((array) config('eligibility.eu261_countries') as $country) {
            $this->fakeCountries(['XXX' => $country, 'JFK' => 'US']);

            $regulator = RegulatorDirectory::for($this->claim('EU261', 'XXX', 'JFK'));

            $this->assertTrue($regulator['confident'], "No NEB resolved for {$country}");
            $this->assertNotSame('', $regulator['code'], "Empty regulator code for {$country}");
            $this->assertStringStartsWith('https://', $regulator['url'], "Missing portal URL for {$country}");
            $this->assertSame($country, $regulator['country']);
        }
    }

    public function test_unknown_airport_country_is_flagged_not_guessed(): void
    {
        // FlightAware lookup failed - both countries come back null.
        $this->fakeCountries([]);

        $regulator = RegulatorDirectory::for($this->claim('EU261', 'CDG', 'JFK'));

        $this->assertFalse($regulator['confident']);
        $this->assertStringContainsString('confirm which state is competent', $regulator['reason']);
    }

    public function test_claim_without_a_verdict_yet_has_no_regulator(): void
    {
        $claim = $this->claim('EU261', 'CDG', 'JFK');
        $claim->forceFill(['eligibility_regulation' => null])->save();

        $regulator = RegulatorDirectory::for($claim);

        $this->assertFalse($regulator['confident']);
        $this->assertStringContainsString('has not decided it', $regulator['reason']);
    }

    public function test_ai_prompt_pins_the_complaint_to_the_resolved_body(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        $this->fakeCountries(['FRA' => 'DE', 'JFK' => 'US']);

        Http::fake(['*generativelanguage*' => Http::response(['candidates' => [['content' => ['parts' => [[
            'text' => json_encode([
                'subject' => 'Complaint against Air France - AF1234',
                'body'    => "To the Luftfahrt-Bundesamt,\n\nWe submit a complaint under Article 7 of Regulation (EC) No 261/2004.\n\nUnjamm Claims Team",
            ]),
        ]]]]]], 200)]);

        $draft = app(ClaimLetterService::class)->generate($this->claim('EU261', 'FRA', 'JFK'), ClaimDraft::TYPE_REGULATOR);

        $this->assertSame('ai', $draft['generated_by']);

        // The prompt named the resolved body and forbade substituting another.
        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'Luftfahrt-Bundesamt')
                && str_contains($prompt, 'COMPETENT AUTHORITY')
                && str_contains($prompt, 'never another');
        });
    }
}
