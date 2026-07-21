<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use App\Services\Eligibility\RegulationCitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Eligibility Engine decides the law; the AI only formats it. Citations
 * come from the canonical table, and a draft that invents a provision is
 * rejected rather than sent to an airline.
 */
class RegulationCitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('user');
        config(['services.gemini.api_key' => 'test-key']);
    }

    private function claim(array $overrides = []): Claim
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return Claim::create(array_merge([
            'user_id'                 => $user->id,
            'status'                  => Claim::STATUS_ELIGIBLE,
            'workflow_state'          => 'ready_to_file',
            'airline'                 => 'Air Canada',
            'flight_number'           => 'AC1540',
            'departure_airport'       => 'YYZ',
            'arrival_airport'         => 'IAD',
            'flight_date'             => '2026-07-10',
            'passenger_name'          => 'Tenzin Hagyal',
            'flight_cancelled'        => true,
            'disruption_type'         => 'cancelled',
            'eligibility_regulation'  => 'APPR',
            'eligibility_article'     => 'Section 19',
            'eligibility_reason'      => 'The flight was cancelled within the carrier\'s control.',
            'compensation_amount'     => '400.00',
            'compensation_currency'   => 'CAD',
            'compensation_basis'      => 'APPR s.19(2)',
        ], $overrides));
    }

    public function test_canonical_table_maps_each_disruption_to_the_right_article(): void
    {
        // A cancellation is compensated under s.19 - never the denied-boarding
        // or claim-deadline provisions the model used to reach for.
        $this->assertSame('Section 19', RegulationCitation::article('APPR', 'cancelled'));
        $this->assertSame('Section 20', RegulationCitation::article('APPR', 'denied_boarding'));
        $this->assertSame('Article 4', RegulationCitation::article('EU261', 'denied_boarding'));
        $this->assertSame('Article 10', RegulationCitation::article('EU261', 'downgrade'));
        $this->assertSame('14 CFR Part 250', RegulationCitation::article('US_DOT', 'denied_boarding'));

        // Unknown scenarios fall back to the regime's principal article.
        $this->assertSame('Article 7', RegulationCitation::article('EU261', 'something_new'));
    }

    public function test_engine_citation_overrides_whatever_the_model_proposed(): void
    {
        $this->assertSame(
            'Section 19',
            RegulationCitation::normalise('APPR', 'cancelled', 'ss. 20-22')
        );
    }

    public function test_draft_citing_an_unauthorised_provision_is_rejected(): void
    {
        $claim   = $this->claim();
        $letters = app(ClaimLetterService::class);

        // Section 22 (the one-year filing deadline) is not a compensation
        // provision - the engine never authorised it.
        $invented = $letters->inventedCitations($claim, 'We claim under APPR Section 22 and Section 19.');
        $this->assertSame(['Section 22'], $invented);

        // Sub-provisions of an authorised article are legitimate.
        $this->assertSame([], $letters->inventedCitations($claim, 'Compensation is due under Section 19(1).'));
        // So are the supporting provisions the table authorises.
        $this->assertSame([], $letters->inventedCitations($claim, 'A refund is owed under Section 17 of the APPR.'));
    }

    public function test_hallucinated_citation_falls_back_to_the_template_instead_of_being_sent(): void
    {
        $claim = $this->claim();

        // Gemini insists on the wrong section on every attempt.
        Http::fake(['*generativelanguage*' => Http::response(['candidates' => [['content' => ['parts' => [[
            'text' => json_encode([
                'subject' => 'Compensation claim - AC1540',
                'body'    => 'We claim compensation under APPR Section 22 for this cancelled flight.',
            ]),
        ]]]]]], 200)]);

        $draft = app(ClaimLetterService::class)->generate($claim);

        // The bad draft never surfaces - the deterministic template does.
        $this->assertSame('template', $draft['generated_by']);
        $this->assertStringNotContainsString('Section 22', $draft['body']);
        $this->assertSame([], app(ClaimLetterService::class)->inventedCitations($claim, $draft['body']));
    }

    public function test_clean_ai_draft_is_accepted(): void
    {
        $claim = $this->claim();

        Http::fake(['*generativelanguage*' => Http::response(['candidates' => [['content' => ['parts' => [[
            'text' => json_encode([
                'subject' => 'Compensation claim - AC1540',
                'body'    => "Dear Air Canada Claims Department,\n\nWe claim CAD 400.00 under APPR Section 19 for the cancellation of flight AC1540.\n\nSincerely,\nUnjamm Claims Team",
            ]),
        ]]]]]], 200)]);

        $draft = app(ClaimLetterService::class)->generate($claim);

        $this->assertSame('ai', $draft['generated_by']);
        $this->assertStringContainsString('Section 19', $draft['body']);
    }
}
