<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail;
use App\Livewire\Admin\FlightClaims\Claims;
use App\Mail\GenericEmail;
use App\Models\Claim;
use App\Models\User;
use App\Services\Claims\ClaimLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin Flight Claims Management: lists, detail page, claim letter drafts. */
class AdminFlightClaimsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Role::findOrCreate('admin')->givePermissionTo(
            \Spatie\Permission\Models\Permission::whereIn('name', [
                'airlines.manage', 'claim_templates.manage', 'claim_templates.delete',
                'claim_drafts.generate', 'claim_emails.send',
            ])->get()
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function claim(): Claim
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        return Claim::create([
            'user_id'                => $owner->id,
            'status'                 => Claim::STATUS_ELIGIBLE,
            'airline'                => 'Air Canada',
            'flight_number'          => 'AC1540',
            'departure_airport'      => 'YYZ',
            'arrival_airport'        => 'IAD',
            'flight_date'            => '2026-07-10',
            'disruption_type'        => 'cancelled',
            'passenger_name'         => 'Tenzin Hagyal',
            'flight_cancelled'       => true,
            'flight_verified_at'     => now(),
            'eligibility_regulation' => 'APPR',
            'eligibility_article'    => 'Section 19',
            'eligibility_confidence' => 85,
            'eligibility_reason'     => 'Cancelled with short notice.',
            'compensation_amount'    => 400,
            'compensation_currency'  => 'CAD',
        ]);
    }

    public function test_admin_can_open_lists_and_claim_detail_page(): void
    {
        $claim = $this->claim();

        $this->actingAs($this->admin)->get(route('admin.flight-claims.trips'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.flight-claims.lifecycle'))->assertOk()->assertSee('Lifecycle Management');
        $this->actingAs($this->admin)->get(route('admin.flight-claims.claims'))->assertOk()->assertSee($claim->number);
        $this->actingAs($this->admin)->get(route('admin.flight-claims.claims.show', $claim))
            ->assertOk()
            ->assertSee('Claim email to the airline')
            ->assertSee('AC1540');
    }

    public function test_non_admins_cannot_open_the_claims_management_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('admin.flight-claims.claims'))->assertStatus(302);
    }

    public function test_admin_can_approve_a_claim_in_review(): void
    {
        Mail::fake();
        $claim = $this->claim();
        $claim->forceFill(['status' => Claim::STATUS_PENDING_ELIGIBILITY, 'eligibility_status' => 'review', 'compensation_amount' => null])->save();
        $claim->recordEvent('Our team is reviewing your eligibility', 'pending', now(), 2);

        Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->call('approve');

        $claim->refresh();
        $this->assertSame(Claim::STATUS_ELIGIBLE, $claim->status);
        $this->assertSame('admin', $claim->eligibility_decision_source);
        $this->assertNotNull($claim->compensation_amount);
        $this->assertTrue($claim->events()->where('label', 'like', 'Our team confirmed your eligibility%')->exists());
        Mail::assertSent(GenericEmail::class);
    }

    public function test_rejecting_a_claim_requires_a_reason_and_notifies_the_customer(): void
    {
        Mail::fake();
        $claim = $this->claim();
        $claim->forceFill(['status' => Claim::STATUS_PENDING_ELIGIBILITY, 'eligibility_status' => 'review'])->save();

        Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->call('reject')
            ->assertHasErrors(['rejection_reason']);

        Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->set('rejection_reason', 'The declared delay is below the compensation threshold.')
            ->call('reject');

        $claim->refresh();
        $this->assertSame(Claim::STATUS_REJECTED, $claim->status);
        $this->assertNull($claim->compensation_amount);
        $this->assertSame('The declared delay is below the compensation threshold.', $claim->eligibility_reason);
        Mail::assertSent(GenericEmail::class, fn (GenericEmail $mail) => str_contains($mail->htmlBody, 'below the compensation threshold'));
    }

    public function test_claims_list_searches_by_customer_name_flight_number_and_claim_id(): void
    {
        $claim = $this->claim();
        $claim->user->forceFill(['name' => 'Sunil Kumar'])->save();
        $other = $this->claim();
        $other->forceFill(['flight_number' => 'LH999', 'number' => '1111111'])->save();

        // By customer name
        Livewire::actingAs($this->admin)->test(Claims::class)
            ->set('search', 'Sunil Kumar')
            ->assertSee($claim->number)
            ->assertDontSee($other->number);

        // By flight number
        Livewire::actingAs($this->admin)->test(Claims::class)
            ->set('search', 'LH999')
            ->assertSee($other->number)
            ->assertDontSee($claim->number);

        // By claim id / number
        Livewire::actingAs($this->admin)->test(Claims::class)
            ->set('search', $claim->number)
            ->assertSee($claim->reference)
            ->assertDontSee($other->reference);
    }

    public function test_follow_up_and_regulator_drafts_are_generated_and_versioned(): void
    {
        config(['services.gemini.api_key' => null]);
        $claim = $this->claim();

        $component = Livewire::actingAs($this->admin)->test(ClaimDetail::class, ['claim' => $claim]);

        // v1 initial claim, v1 follow-up (no response), v1 regulator complaint
        $component->call('generate')
            ->call('generateFollowUp', 'no_response')
            ->call('generateRegulator');

        $drafts = $claim->drafts()->get();
        $this->assertCount(3, $drafts);
        $this->assertEqualsCanonicalizing(
            ['airline_claim', 'follow_up', 'regulator_complaint'],
            $drafts->pluck('type')->all()
        );

        $followUp = $drafts->firstWhere('type', 'follow_up');
        $this->assertStringContainsString('escalate this claim to the Canadian Transportation Agency', $followUp->body);
        $this->assertSame('no_response', $followUp->context['reason']);

        $regulator = $drafts->firstWhere('type', 'regulator_complaint');
        $this->assertStringContainsString('complaint against Air Canada', $regulator->body);
        $this->assertStringContainsString('APPR Section 19', $regulator->body);

        // Regenerating bumps the version; history is preserved.
        $component->call('generate');
        $this->assertSame(2, $claim->drafts()->where('type', 'airline_claim')->max('version'));
        $this->assertCount(4, $claim->drafts()->get());
    }

    public function test_admin_edits_and_approval_are_tracked_as_versions(): void
    {
        config(['services.gemini.api_key' => null]);
        $claim = $this->claim();

        $component = Livewire::actingAs($this->admin)
            ->test(ClaimDetail::class, ['claim' => $claim])
            ->call('generate')
            ->set('body', 'Edited body with our own wording for the airline claim.')
            ->call('saveDraft');

        $drafts = $claim->drafts()->where('type', 'airline_claim')->reorder('version')->get();
        $this->assertCount(2, $drafts);
        $this->assertSame('admin', $drafts->last()->generated_by);

        $component->call('approveDraft', $drafts->last()->id);
        $this->assertNotNull($drafts->last()->fresh()->approved_at);
        $this->assertNull($drafts->first()->fresh()->approved_at);
    }

    public function test_letter_template_fallback_is_jurisdiction_specific(): void
    {
        config(['services.gemini.api_key' => null]);

        // Canadian claim (APPR): APPR framing, s.19(4) 30-day deadline, CTA escalation.
        $draft = app(ClaimLetterService::class)->generate($this->claim());

        $this->assertSame('template', $draft['generated_by']);
        $this->assertStringContainsString('AC1540', $draft['subject']);
        $this->assertStringContainsString('Tenzin Hagyal', $draft['body']);
        $this->assertStringContainsString('APPR Section 19', $draft['body']);
        $this->assertStringContainsString('Air Passenger Protection Regulations', $draft['body']);
        $this->assertStringContainsString('CAD 400.00', $draft['body']);
        $this->assertStringContainsString('30 days', $draft['body']);
        $this->assertStringContainsString('Canadian Transportation Agency', $draft['body']);

        // EU claim (EU261): Regulation 261/2004 framing, 14-day demand, NEB escalation.
        $eu = $this->claim();
        $eu->forceFill([
            'airline' => 'Air France', 'flight_number' => 'AF348',
            'departure_airport' => 'CDG', 'arrival_airport' => 'YUL',
            'eligibility_regulation' => 'EU261', 'eligibility_article' => 'Article 7(1)',
            'compensation_amount' => 600, 'compensation_currency' => 'EUR',
            'flight_cancelled' => false, 'flight_arrival_delay_minutes' => 212,
        ])->save();

        $draft = app(ClaimLetterService::class)->generate($eu->fresh());

        $this->assertStringContainsString('Regulation (EC) No 261/2004', $draft['body']);
        $this->assertStringContainsString('3h 32m late', $draft['body']);
        $this->assertStringContainsString('14 days', $draft['body']);
        $this->assertStringContainsString('National Enforcement Body', $draft['body']);
    }

    public function test_the_plus_toggle_narrows_any_tab_to_members_and_priority_ordering_still_applies(): void
    {
        $member = \App\Models\User::factory()->create();
        $member->assignRole('user');
        $member->subscriptions()->create([
            'subscription_plan_id'   => \App\Models\SubscriptionPlan::query()->value('id'),
            'stripe_customer_id'     => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'stripe_price_id'        => 'price_test',
            'interval'               => 'month',
            'status'                 => 'active',
            'current_period_end'     => now()->addMonth(),
        ]);

        $plusClaim = \App\Models\Claim::create([
            'user_id' => $member->id, 'status' => \App\Models\Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC900', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'LHR', 'flight_date' => '2026-07-10', 'passenger_name' => 'Plus Member',
        ]);
        $free = \App\Models\User::factory()->create();
        $free->assignRole('user');

        $freeClaim = \App\Models\Claim::create([
            'user_id' => $free->id, 'status' => \App\Models\Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC901', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'LHR', 'flight_date' => '2026-07-10', 'passenger_name' => 'Free Customer',
        ]);

        // Membership is orthogonal to lifecycle: it narrows whichever tab is open.
        Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\FlightClaims\Claims::class)
            ->call('setStatus', 'confirmation')
            ->set('plusOnly', true)
            ->assertSee('AC900')
            ->assertDontSee('AC901');

        // Off again, both are listed.
        Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\FlightClaims\Claims::class)
            ->call('setStatus', 'confirmation')
            ->assertSee('AC900')
            ->assertSee('AC901');
    }
}
