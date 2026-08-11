<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Mail\AirlineClaimMail;
use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\Itinerary;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use App\Services\Eligibility\RegulationCitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AI drafting validation: the model writes the letter, never the law.
 *
 * These tests assert the deterministic contract rather than AI prose - what
 * the engine puts INTO the prompt, what the guard refuses to let OUT, and
 * that a draft is only ever a draft until an admin sends it. Every Gemini
 * call is faked; no live AI traffic.
 */
class AiDraftingValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Prompts captured from the faked Gemini calls, in order. */
    private array $prompts = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.gemini.api_key' => 'gemini-secret-key']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('user');
        Role::findOrCreate('admin')->givePermissionTo(
            Permission::whereIn('name', [
                'claim_drafts.generate', 'claim_emails.send',
                'claim_templates.manage', 'airlines.manage',
            ])->get()
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    // ── 5. The structured legal basis reaches the model ─────

    public function test_the_prompt_carries_the_engine_verdict_and_forbids_the_model_deciding_law(): void
    {
        $claim = $this->claim();
        $this->fakeGemini($this->letter('Compensation claim - AC1540',
            "Dear Air Canada Claims Department,\n\nWe claim CAD 400.00 under APPR Section 19.\n\nSincerely,\nUnjamm Claims Team"));

        app(ClaimLetterService::class)->generate($claim);

        $prompt = $this->lastPrompt();

        // The engine's decision travels as authoritative structured data.
        $this->assertStringContainsString('"regulation": "APPR"', $prompt);
        $this->assertStringContainsString('"article": "Section 19"', $prompt);
        $this->assertStringContainsString('CAD 400.00', $prompt);
        $this->assertStringContainsString('LEGAL BASIS', $prompt);
        $this->assertStringContainsString('decided by the Eligibility Engine', $prompt);

        // The claim's own facts are supplied - never left to the model.
        $this->assertStringContainsString('AC1540', $prompt);
        $this->assertStringContainsString('Tenzin Hagyal', $prompt);

        // The model is explicitly told it does not decide the law, and is
        // handed a closed list of permitted citations.
        $this->assertStringContainsString('the Eligibility Engine decides the law; you only write the letter', $prompt);
        foreach (RegulationCitation::allowed('APPR') as $allowed) {
            $this->assertStringContainsString($allowed, $prompt);
        }

        // Nothing in the prompt invites the model to pick a regime.
        $this->assertStringNotContainsString('determine the applicable regulation', $prompt);
        $this->assertStringNotContainsString('decide which regulation', $prompt);
    }

    // ── 9. Multi-passenger arithmetic is computed, not delegated ──

    public function test_multi_passenger_totals_are_calculated_before_the_model_sees_them(): void
    {
        $claim = $this->multiPassengerClaim(); // 5 pax x EUR 600
        $this->fakeGemini($this->letter('Compensation claim - LH123',
            "Dear Lufthansa Claims Department,\n\nWe claim EUR 3,000.00 under EU261 Article 7 for five passengers.\n\nSincerely,\nUnjamm Claims Team"));

        app(ClaimLetterService::class)->generate($claim);
        $prompt = $this->lastPrompt();

        $this->assertStringContainsString('"passengers": 5', $prompt);
        $this->assertStringContainsString('EUR 600.00', $prompt);   // per passenger
        $this->assertStringContainsString('EUR 3,000.00', $prompt); // engine-computed total

        // The roster names all five exactly once - nobody dropped, nobody
        // listed twice, nobody borrowed from another claim.
        preg_match('/"passengers": "([^"]+)"/', $prompt, $roster);
        $this->assertSame(
            ['Alpha Lead', 'Bravo Adult', 'Charlie Adult', 'Delta Adult', 'Echo Child'],
            array_map('trim', explode(',', $roster[1] ?? ''))
        );

        // The minor is additionally flagged as a minor, so the letter knows
        // a guardian authorised the claim on their behalf.
        $this->assertStringContainsString('"minors": [', $prompt);
        $this->assertMatchesRegularExpression('/"minors": \[\s*"Echo Child"\s*\]/', $prompt);
    }

    // ── 6 + 7. The guard refuses foreign and invented law ───

    public function test_a_draft_citing_another_regimes_provisions_never_reaches_the_airline(): void
    {
        $claim   = $this->claim(); // APPR / Section 19 / CAD 400
        $letters = app(ClaimLetterService::class);

        // The model swaps in EU261's Article 7 - a real provision, but not
        // one this claim's regime authorises.
        $this->assertNotEmpty($letters->inventedCitations($claim, 'We claim under EU261 Article 7 and Article 5.'));

        // End to end: the conflicting draft is rejected and the
        // deterministic template - canonical by construction - goes instead.
        $this->fakeGemini($this->letter('Compensation claim - AC1540',
            'We claim EUR 600 under EU261 Article 7 for this cancelled flight.'));

        $draft = $letters->generate($claim);

        $this->assertSame('template', $draft['generated_by']);
        $this->assertStringNotContainsString('Article 7', $draft['body']);
        $this->assertSame([], $letters->inventedCitations($claim, $draft['body']));
        // The fallback still states the engine's own verdict and money.
        $this->assertStringContainsString('Section 19', $draft['body']);
        $this->assertStringContainsString('400.00', $draft['body']);
    }

    public function test_appr_section_17_survives_as_the_refund_provision_it_is(): void
    {
        $claim   = $this->claim();
        $letters = app(ClaimLetterService::class);

        // s.19 compensation is the claim's basis; s.17 remains legitimate as
        // the refund provision the canonical table authorises alongside it.
        $this->assertSame('Section 19', RegulationCitation::article('APPR', 'cancelled'));
        $this->assertSame('Section 17', RegulationCitation::supporting('APPR', 'refund'));
        $this->assertSame([], $letters->inventedCitations($claim,
            'Compensation is owed under Section 19 and the fare refunded under Section 17.'));

        // But a neighbouring section nobody authorised is still refused -
        // this is provision-level validation, not a string swap.
        $this->assertSame(['Section 18'], $letters->inventedCitations($claim, 'We rely on Section 18.'));
    }

    public function test_a_draft_with_no_citation_at_all_is_accepted_rather_than_faked(): void
    {
        $claim = $this->claim();

        $this->fakeGemini($this->letter('Compensation claim - AC1540',
            "Dear Air Canada Claims Department,\n\nWe claim CAD 400.00 for the cancellation of AC1540.\n\nSincerely,\nUnjamm Claims Team"));

        $draft = app(ClaimLetterService::class)->generate($claim);

        // The prompt tells the model to omit rather than invent - an
        // uncited but truthful letter is valid output, not an error.
        $this->assertSame('ai', $draft['generated_by']);
        $this->assertSame([], app(ClaimLetterService::class)->inventedCitations($claim, $draft['body']));
    }

    // ── 8. Money the engine never worked out is refused ─────
    //
    // Regression: the citation guard policed the law but not the amounts, so
    // a model that wrote its own figure had it sent to the airline. Amounts
    // are now guarded by the same reject-redraft-fallback mechanism.

    public function test_a_draft_demanding_an_amount_the_engine_never_calculated_is_refused(): void
    {
        $claim   = $this->claim(); // engine says CAD 400.00, one passenger
        $letters = app(ClaimLetterService::class);

        // Inflated, deflated and re-denominated demands are all conflicts.
        $this->assertSame(['9,999.00'], $letters->conflictingAmounts($claim, 'We claim CAD 9,999.00 under Section 19.'));
        $this->assertSame(['50.00'], $letters->conflictingAmounts($claim, 'Please remit 50.00 CAD.'));

        // End to end: the bad draft never surfaces - the template does, and
        // it names the engine's own figure.
        $this->fakeGemini($this->letter('Compensation claim - AC1540',
            "Dear Air Canada Claims Department,\n\nWe claim CAD 9,999.00 under Section 19.\n\nSincerely,\nUnjamm Claims Team"));

        $draft = $letters->generate($claim);

        $this->assertSame('template', $draft['generated_by']);
        $this->assertStringNotContainsString('9,999', $draft['body']);
        $this->assertStringContainsString('400.00', $draft['body']);
    }

    public function test_the_amount_guard_accepts_every_figure_the_engine_authorises(): void
    {
        $letters = app(ClaimLetterService::class);

        // Single passenger: the statutory amount and the fare on file.
        $claim = $this->claim(['ticket_price' => '850.00', 'ticket_currency' => 'CAD']);
        $this->assertSame([], $letters->conflictingAmounts($claim,
            'Compensation of CAD 400.00 is owed, and the fare of CAD 850.00 must be refunded.'));

        // Five passengers: per-head, the booking total, and the partial
        // totals a letter may legitimately break out.
        $family = $this->multiPassengerClaim(); // 5 x EUR 600
        $this->assertSame([], $letters->conflictingAmounts($family,
            'EUR 600.00 per passenger, EUR 1,200.00 for the two adults travelling together, EUR 3,000.00 in total.'));

        // A figure outside that set is still caught.
        $this->assertSame(['4,200.00'], $letters->conflictingAmounts($family, 'We demand EUR 4,200.00.'));

        // Prose that merely contains numbers is never mistaken for money.
        $this->assertSame([], $letters->conflictingAmounts($claim,
            'Flight AC1540 on 10 July 2026 arrived 260 minutes late with 4 passengers aboard.'));
    }

    // ── 16. Every provider failure lands on the template ────

    public function test_every_ai_failure_mode_degrades_to_the_deterministic_template(): void
    {
        $modes = [
            'HTTP 500'        => Http::response([], 500),
            'rate limited'    => Http::response(['error' => 'quota'], 429),
            'provider down'   => Http::response([], 503),
            'empty body'      => Http::response(['candidates' => []], 200),
            'malformed JSON'  => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'not json at all']]]]]], 200),
            'blank subject'   => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['subject' => '', 'body' => 'x'])]]]]]], 200),
        ];

        foreach ($modes as $label => $response) {
            $claim = $this->claim();
            Http::fake(['*generativelanguage*' => $response]);

            $draft = app(ClaimLetterService::class)->generate($claim);

            $this->assertSame('template', $draft['generated_by'], "{$label} must fall back to the template");
            $this->assertNotEmpty(trim($draft['subject']), "{$label} must still yield a usable subject");
            $this->assertNotEmpty(trim($draft['body']), "{$label} must still yield a usable body");
            $this->assertSame([], app(ClaimLetterService::class)->inventedCitations($claim, $draft['body']));
        }
    }

    public function test_a_failed_generation_stores_no_draft_and_sends_no_email(): void
    {
        Http::fake(['*generativelanguage*' => Http::response([], 500)]);
        $claim = $this->claim();

        Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->call('generate');

        $claim->refresh();

        // The template rescued the letter, so exactly one draft exists -
        // never a half-written or empty one - and nothing was sent.
        $draft = $claim->drafts()->sole();
        $this->assertSame('template', $draft->generated_by);
        $this->assertNotEmpty(trim($draft->body));
        Mail::assertNothingSent();

        // Drafting never moves the claim through its lifecycle.
        $this->assertSame('ready_to_file', $claim->workflow_state);
    }

    // ── 18. Credentials never leak ──────────────────────────

    public function test_the_api_key_never_reaches_a_draft_a_log_or_the_prompt(): void
    {
        Log::spy();
        $claim = $this->claim();

        // A failing call is the riskiest path - it is the one that logs.
        Http::fake(['*generativelanguage*' => Http::response(['error' => 'bad key'], 401)]);
        $draft = app(ClaimLetterService::class)->generate($claim);

        $this->assertStringNotContainsString('gemini-secret-key', $draft['subject'] . $draft['body']);

        Log::shouldNotHaveReceived('warning', [\Mockery::any(), \Mockery::on(
            fn ($ctx) => str_contains(json_encode($ctx), 'gemini-secret-key')
        )]);

        // A successful call must not put the key in the prompt body either -
        // it belongs in the query string, nowhere else.
        $this->fakeGemini($this->letter('Compensation claim - AC1540', 'Dear Air Canada, we claim CAD 400.00 under Section 19. Sincerely, Unjamm'));
        app(ClaimLetterService::class)->generate($claim);
        $this->assertStringNotContainsString('gemini-secret-key', $this->lastPrompt());
    }

    // ── 12 + 22 + 23. Draft, review, then - only then - send ──

    public function test_drafting_repeatedly_versions_without_sending_or_advancing_the_claim(): void
    {
        $claim = $this->claim();
        $this->fakeGemini($this->letter('Compensation claim - AC1540',
            "Dear Air Canada Claims Department,\n\nWe claim CAD 400.00 under APPR Section 19.\n\nSincerely,\nUnjamm Claims Team"));

        $component = Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim]);
        $component->call('generate')->call('generate')->call('generate');

        $claim->refresh();
        $drafts = $claim->drafts()->where('type', ClaimDraft::TYPE_CLAIM)->reorder('version')->get();

        // Immutable version history, one claim, no email, no lifecycle move.
        $this->assertSame([1, 2, 3], $drafts->pluck('version')->all());
        $this->assertSame(1, Claim::count());
        $this->assertSame('ready_to_file', $claim->workflow_state);
        Mail::assertNothingSent();

        // Storage carries the full provenance record.
        $latest = $drafts->last();
        $this->assertSame($claim->id, $latest->claim_id);
        $this->assertSame(ClaimDraft::TYPE_CLAIM, $latest->type);
        $this->assertSame('ai', $latest->generated_by);
        $this->assertSame($this->admin->id, $latest->created_by);
        $this->assertNotEmpty($latest->subject);
        $this->assertNotEmpty($latest->body);
        $this->assertNotNull($latest->created_at);

        // Only the admin's explicit send puts it on the wire.
        $component->set('to', 'claims@aircanada.ca')->call('send');

        Mail::assertSent(AirlineClaimMail::class);
        $this->assertContains($claim->fresh()->workflow_state, ['filed', 'awaiting_response']);
    }

    // ── 17. Customers never see internal drafting ───────────

    public function test_customers_cannot_reach_drafts_prompts_or_another_claim(): void
    {
        $claim = $this->claim();
        $this->fakeGemini($this->letter('Compensation claim - AC1540', 'Dear Air Canada, we claim CAD 400.00 under Section 19. Sincerely, Unjamm'));
        app(ClaimLetterService::class)->generate($claim);

        $owner = $claim->user;
        $owner->assignRole('user');

        $payload = $this->actingAs($owner)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($claim->id)))
            ->assertOk()->json('data');

        $serialised = json_encode($payload);
        $this->assertArrayNotHasKey('drafts', $payload);
        $this->assertArrayNotHasKey('correspondence', $payload);
        $this->assertStringNotContainsString('gemini-secret-key', $serialised);
        $this->assertStringNotContainsString('LEGAL BASIS', $serialised, 'Internal prompt text must never be serialised to a customer');

        // A customer cannot open the admin drafting surface at all.
        $this->actingAs($owner)->get(route('admin.flight-claims.claims.show', $claim))->assertRedirect();

        // And drafting itself is permission-gated for staff without the grant.
        $viewer = User::factory()->create();
        $viewer->assignRole('admin');
        Role::findOrCreate('admin')->revokePermissionTo('claim_drafts.generate');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($viewer)->test(ClaimDetail::class, ['claim' => $claim])
            ->call('generate')->assertForbidden();
    }

    // ── 20. Eligibility never depends on the AI ─────────────

    public function test_eligibility_still_decides_the_law_with_the_ai_provider_removed(): void
    {
        // No Gemini key at all, and any AI call would fail loudly.
        config(['services.gemini.api_key' => null, 'eligibility.evaluator' => 'rules']);
        Http::fake(['*generativelanguage*' => Http::response([], 500)]);

        $claim = $this->claim();
        $draft = app(ClaimLetterService::class)->generate($claim);

        // The engine's verdict is intact and the letter still gets written.
        $this->assertSame('APPR', $claim->eligibility_regulation);
        $this->assertSame('Section 19', $claim->eligibility_article);
        $this->assertSame('400.00', $claim->compensation_amount);
        $this->assertSame('template', $draft['generated_by']);
        $this->assertStringContainsString('Section 19', $draft['body']);
        $this->assertStringContainsString('400.00', $draft['body']);

        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), 'generativelanguage'));
    }

    // ── Fixtures ────────────────────────────────────────────

    /** Fake Gemini with a fixed letter, capturing each outbound prompt. */
    private function fakeGemini(array $letter): void
    {
        $this->prompts = [];

        Http::fake(function (ClientRequest $request) use ($letter) {
            if (str_contains($request->url(), 'generativelanguage')) {
                $this->prompts[] = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

                return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($letter)]]]]]], 200);
            }

            return Http::response([], 200);
        });
    }

    private function letter(string $subject, string $body): array
    {
        return ['subject' => $subject, 'body' => $body];
    }

    private function lastPrompt(): string
    {
        $this->assertNotEmpty($this->prompts, 'The AI was never called');

        return end($this->prompts);
    }

    /** APPR cancellation: Section 19, CAD 400, one passenger. */
    private function claim(array $overrides = []): Claim
    {
        $user = User::factory()->create();

        return Claim::create(array_merge([
            'user_id'                => $user->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'workflow_state'         => 'ready_to_file',
            'airline'                => 'Air Canada',
            'flight_number'          => 'AC1540',
            'booking_reference'      => 'AC4WM9T',
            'departure_airport'      => 'YYZ',
            'arrival_airport'        => 'IAD',
            'flight_date'            => '2026-07-10',
            'passenger_name'         => 'Tenzin Hagyal',
            'flight_cancelled'       => true,
            'disruption_type'        => 'cancelled',
            'eligibility_regulation' => 'APPR',
            'eligibility_article'    => 'Section 19',
            'eligibility_reason'     => 'The flight was cancelled within the carrier\'s control.',
            'compensation_amount'    => '400.00',
            'compensation_currency'  => 'CAD',
            'compensation_basis'     => 'APPR s.19(2)',
        ], $overrides));
    }

    /** EU261 delay: Article 7, EUR 600 per passenger, five passengers. */
    private function multiPassengerClaim(): Claim
    {
        $user      = User::factory()->create();
        $itinerary = Itinerary::create([
            'user_id'           => $user->id,
            'original_filename' => 'family.pdf',
            'file_path'         => 'itineraries/family.pdf',
            'status'            => 'parsed',
            'booking_reference' => 'LH8XK2P',
            'primary_airline'   => 'Lufthansa',
        ]);

        $itinerary->passengers()->createMany([
            ['full_name' => 'Alpha Lead', 'type' => 'MR'],
            ['full_name' => 'Bravo Adult', 'type' => 'MRS'],
            ['full_name' => 'Charlie Adult', 'type' => 'MR'],
            ['full_name' => 'Delta Adult', 'type' => 'MS'],
            ['full_name' => 'Echo Child', 'type' => 'CHD'],
        ]);

        return Claim::create([
            'user_id'                => $user->id,
            'itinerary_id'           => $itinerary->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'workflow_state'         => 'ready_to_file',
            'airline'                => 'Lufthansa',
            'flight_number'          => 'LH123',
            'booking_reference'      => 'LH8XK2P',
            'departure_airport'      => 'FRA',
            'arrival_airport'        => 'JFK',
            'flight_date'            => '2026-07-10',
            'passenger_name'         => 'Alpha Lead',
            'disruption_type'        => 'delayed',
            'flight_arrival_delay_minutes' => 260,
            'eligibility_regulation' => 'EU261',
            'eligibility_article'    => 'Article 7',
            'eligibility_reason'     => 'Arrival delay over three hours.',
            'compensation_amount'    => '600.00',
            'compensation_currency'  => 'EUR',
            'compensation_basis'     => 'EU261 Article 7(1)(c)',
        ]);
    }
}
