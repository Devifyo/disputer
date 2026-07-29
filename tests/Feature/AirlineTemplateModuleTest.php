<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\ClaimDetail as AdminClaimDetail;
use App\Livewire\Admin\FlightClaims\ClaimTemplates as AdminTemplates;
use App\Mail\AirlineClaimMail;
use App\Models\Airline;
use App\Models\AirlineEmailTemplate;
use App\Models\Claim;
use App\Models\ClaimCorrespondence;
use App\Models\User;
use App\Services\Claims\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Airline templates and the hybrid composer: the AI is the default route,
 * a saved template is the manual one, and both end up in the same audited
 * history with their provenance recorded.
 */
class AirlineTemplateModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private Airline $airline;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::findOrCreate('user');
        Role::findOrCreate('admin')->givePermissionTo(
            Permission::whereIn('name', [
                'airlines.manage', 'claim_templates.manage', 'claim_templates.delete',
                'claim_drafts.generate', 'claim_emails.send',
            ])->get()
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');

        // The directory is seeded by a migration - work with the real row.
        $this->airline = Airline::firstOrCreate(
            ['iata_code' => 'AC'],
            ['name' => 'Air Canada', 'country' => 'Canada', 'is_active' => true],
        );
        $this->airline->contacts()->updateOrCreate(['purpose' => 'claims'], ['email' => 'claims@aircanada.ca']);
        $this->airline->contacts()->updateOrCreate(['purpose' => 'escalation'], ['email' => 'escalations@aircanada.ca']);

        config(['services.inbound.reply_domain' => 'claims.unjamm.com', 'services.inbound.claims_display' => 'claims@unjamm.com']);
    }

    private function claim(): Claim
    {
        return Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'ready_to_file',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
            'booking_reference' => 'UNJ3PX', 'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
            'eligibility_regulation' => 'APPR', 'eligibility_article' => 'Section 19',
        ]);
    }

    private function template(array $overrides = [], ?array $airlineIds = null): AirlineEmailTemplate
    {
        $template = AirlineEmailTemplate::create(array_merge([
            'name'       => 'Air Canada - initial claim',
            'type'       => AirlineEmailTemplate::TYPE_INITIAL,
            'subject'    => 'Claim {{claim_reference}} - flight {{flight_number}}',
            'body'       => str_repeat('Dear {{airline_name}}, we act for {{passenger_name}} on {{flight_number}} ({{departure_airport}} to {{arrival_airport}}). We claim {{compensation_amount}} under {{regulation}} {{article}} on {{today_date}}. ', 2),
            'is_default' => true,
            'is_active'  => true,
        ], $overrides));

        // null = this airline; [] = every airline (house template).
        $template->airlines()->sync($airlineIds ?? [$this->airline->id]);

        return $template->load('airlines');
    }

    // ── Rendering ───────────────────────────────────────────

    public function test_every_variable_resolves_from_the_claim(): void
    {
        $claim  = $this->claim();
        $values = app(TemplateRenderer::class)->values($claim);

        $this->assertSame('Tenzin Hagyal', $values['passenger_name']);
        $this->assertSame('AC1540', $values['flight_number']);
        $this->assertSame('UNJ3PX', $values['booking_reference']);
        $this->assertSame('CAD 400.00', $values['compensation_amount']);
        $this->assertSame('APPR', $values['regulation']);
        $this->assertSame('Section 19', $values['article']);
        $this->assertSame(now()->format('d F Y'), $values['today_date']);

        // Every documented variable has a value - no silent gaps.
        $this->assertSame([], array_diff(array_keys(TemplateRenderer::VARIABLES), array_keys($values)));
    }

    public function test_unknown_placeholders_survive_visibly_rather_than_vanishing(): void
    {
        $renderer = app(TemplateRenderer::class);
        $rendered = $renderer->render('Hello {{passenger_name}} - {{not_a_variable}}', $this->claim());

        $this->assertStringContainsString('Tenzin Hagyal', $rendered);
        $this->assertStringContainsString('{{not_a_variable}}', $rendered);
        $this->assertSame(['not_a_variable'], $renderer->unknownVariables('{{passenger_name}} {{not_a_variable}}'));
    }

    // ── Template management ─────────────────────────────────

    public function test_admin_creates_a_template_and_it_is_audited(): void
    {
        Livewire::actingAs($this->admin)->test(AdminTemplates::class)
            ->call('create')
            ->set('form.all', false)
            ->set('form.airlines', [$this->airline->id])
            ->set('form.name', 'Air Canada - follow up')
            ->set('form.type', AirlineEmailTemplate::TYPE_FOLLOW_UP)
            ->set('form.subject', 'Following up on {{claim_reference}}')
            ->set('form.body', str_repeat('Following up on our claim for {{passenger_name}}. ', 3))
            ->call('save')
            ->assertHasNoErrors();

        $template = AirlineEmailTemplate::firstWhere('name', 'Air Canada - follow up');
        $this->assertSame($this->admin->id, $template->created_by);
        $this->assertDatabaseHas('admin_activity_logs', [
            'subject_type' => AirlineEmailTemplate::class,
            'subject_id'   => $template->id,
            'action'       => 'template_created',
            'actor_id'     => $this->admin->id,
        ]);
    }

    public function test_only_one_template_can_be_the_default_per_airline_and_type(): void
    {
        $first  = $this->template();
        $second = $this->template(['name' => 'Air Canada - initial v2', 'is_default' => false]);

        Livewire::actingAs($this->admin)->test(AdminTemplates::class)->call('setDefault', $second->id);

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, AirlineEmailTemplate::where('type', AirlineEmailTemplate::TYPE_INITIAL)
            ->where('is_default', true)->count());
    }

    public function test_duplicating_produces_an_inactive_non_default_copy(): void
    {
        $source = $this->template();

        Livewire::actingAs($this->admin)->test(AdminTemplates::class)->call('duplicate', $source->id);

        $copy = AirlineEmailTemplate::where('name', 'like', '%(copy)')->first();
        $this->assertNotNull($copy);
        $this->assertSame($source->body, $copy->body);
        $this->assertFalse($copy->is_default, 'A copy must never steal the default.');
        $this->assertFalse($copy->is_active, 'A copy starts disabled so it cannot be sent by accident.');
    }

    public function test_deleting_a_template_needs_its_own_permission_and_keeps_sent_history(): void
    {
        $claim    = $this->claim();
        $template = $this->template();

        // An email already went out on this template.
        $record = $claim->correspondence()->create([
            'direction' => ClaimCorrespondence::DIRECTION_OUTBOUND, 'to_email' => 'claims@aircanada.ca',
            'from_email' => 'claims@unjamm.com', 'from_name' => 'Unjamm Claims',
            'subject' => 'Claim', 'body' => 'Body', 'template_id' => $template->id, 'sent_by' => $this->admin->id,
        ]);

        Role::findOrCreate('admin')->revokePermissionTo('claim_templates.delete');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($this->admin)->test(AdminTemplates::class)
            ->call('delete', $template->id)
            ->assertForbidden();
        $this->assertNotNull(AirlineEmailTemplate::find($template->id), 'The template must survive a refused delete.');

        Role::findOrCreate('admin')->givePermissionTo('claim_templates.delete');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($this->admin)->test(AdminTemplates::class)->call('delete', $template->id);

        $this->assertNull(AirlineEmailTemplate::find($template->id));
        // The email that was sent stays in history, just without its template.
        $this->assertNotNull($record->fresh());
        $this->assertNull($record->fresh()->template_id);
    }

    // ── Composer: the two routes ────────────────────────────

    public function test_loading_a_template_fills_variables_and_addresses_the_right_contact(): void
    {
        $claim    = $this->claim();
        $template = $this->template();

        $component = Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('composeMode', 'template')
            ->set('templateId', $template->id)
            ->call('useTemplate');

        $this->assertStringContainsString('CLM-', $component->get('subject'));
        $this->assertStringContainsString('Tenzin Hagyal', $component->get('body'));
        $this->assertStringNotContainsString('{{', $component->get('body'), 'Every variable must be substituted.');
        // Initial-claim templates go to the claims desk.
        $this->assertSame('claims@aircanada.ca', $component->get('to'));
        $this->assertFalse($component->get('aiGenerated'));
    }

    public function test_an_escalation_template_is_addressed_to_the_escalation_desk(): void
    {
        $claim    = $this->claim();
        $template = $this->template(['name' => 'Escalation', 'type' => AirlineEmailTemplate::TYPE_ESCALATION]);

        $component = Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('templateId', $template->id)
            ->call('useTemplate');

        $this->assertSame('escalations@aircanada.ca', $component->get('to'));
    }

    public function test_sending_records_which_template_was_used_and_that_no_ai_was_involved(): void
    {
        $claim    = $this->claim();
        $template = $this->template();

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('composeMode', 'template')
            ->set('templateId', $template->id)
            ->call('useTemplate')
            ->set('cc', 'ops@unjamm.com')
            ->set('bcc', 'archive@unjamm.com, not-an-email')
            ->call('send')
            ->assertHasNoErrors();

        $record = $claim->correspondence()->latest('id')->first();
        $this->assertSame($template->id, $record->template_id);
        $this->assertFalse($record->ai_generated);
        $this->assertSame('Template', $record->originLabel());
        $this->assertSame(['ops@unjamm.com'], $record->cc);
        // The malformed address is dropped rather than breaking the send.
        $this->assertSame(['archive@unjamm.com'], $record->bcc);

        Mail::assertSent(AirlineClaimMail::class, fn ($mail) => $mail->hasTo('claims@aircanada.ca') && $mail->hasCc('ops@unjamm.com'));
    }

    public function test_the_ai_route_uses_the_airlines_default_template_as_its_base(): void
    {
        $claim = $this->claim();
        $this->template();   // default initial-claim template for Air Canada

        // No AI key configured, so the deterministic template is produced -
        // what matters here is that the base template was resolved and the
        // draft is recorded as an AI-route draft with its provenance audited.
        config(['services.gemini.api_key' => null]);

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertTrue($claim->auditLogs()->where('action', 'AI draft generated')->exists());
        $this->assertTrue($claim->drafts()->exists());
    }

    public function test_drafting_and_sending_are_separately_permissioned(): void
    {
        $claim = $this->claim();

        Role::findOrCreate('admin')->revokePermissionTo('claim_emails.send');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Claim')
            ->set('body', str_repeat('Body of the claim letter. ', 5))
            ->call('send')
            ->assertForbidden();

        Mail::assertNothingSent();
        $this->assertSame(0, $claim->correspondence()->count());
    }

    public function test_an_email_can_be_scheduled_and_appears_in_history_before_it_goes(): void
    {
        Queue::fake();
        $claim    = $this->claim();
        $template = $this->template();

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('templateId', $template->id)
            ->call('useTemplate')
            ->set('scheduleAt', now()->addHours(3)->format('Y-m-d\TH:i'))
            ->call('send')
            ->assertHasNoErrors();

        $record = $claim->correspondence()->latest('id')->first();
        $this->assertSame(ClaimCorrespondence::STATUS_SCHEDULED, $record->status);
        $this->assertNotNull($record->scheduled_at);
        Mail::assertNothingSent();
        Queue::assertPushed(\App\Jobs\SendScheduledClaimEmail::class);
    }

    public function test_a_scheduled_email_delivers_when_its_job_runs(): void
    {
        $claim    = $this->claim();
        $template = $this->template();

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('templateId', $template->id)
            ->call('useTemplate')
            ->set('scheduleAt', now()->addMinutes(30)->format('Y-m-d\TH:i'))
            ->call('send');

        $record = $claim->correspondence()->latest('id')->first();

        // The queue runs the job (sync driver in tests dispatches immediately
        // after the delay is ignored) - deliver it explicitly.
        app(\App\Jobs\SendScheduledClaimEmail::class, ['correspondenceId' => $record->id, 'attachmentKeys' => []])
            ->handle(app(\App\Services\Claims\ClaimCorrespondenceService::class));

        $this->assertSame(ClaimCorrespondence::STATUS_SENT, $record->fresh()->status);
        Mail::assertSent(AirlineClaimMail::class);
    }

    public function test_the_airlines_reply_is_fetched_automatically_for_follow_up_drafts(): void
    {
        $claim = $this->claim();

        // The airline replied - it is already on the claim, so nobody should
        // have to paste it into the composer by hand.
        $claim->correspondence()->create([
            'direction'  => ClaimCorrespondence::DIRECTION_INBOUND,
            'from_email' => 'claims@aircanada.ca', 'from_name' => 'Air Canada Claims',
            'to_email'   => 'claims@unjamm.com',
            'subject'    => 'Re: Compensation claim',
            'body'       => "We require the boarding passes before we can proceed.\n\nOn Mon, Unjamm wrote:\n> our original demand",
        ]);

        $component = Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim]);

        $loaded = $component->get('airline_response');
        $this->assertStringContainsString('boarding passes', $loaded);
        // Only their new text - our quoted letter is stripped.
        $this->assertStringNotContainsString('our original demand', $loaded);

        // And the drafting context carries the thread, not just our letters.
        $history = (fn () => $this->correspondenceHistory())->call($component->instance());
        $this->assertNotEmpty(collect($history)->firstWhere('label', 'Airline reply - Air Canada Claims'));
    }

    public function test_a_template_can_cover_several_airlines_or_all_of_them(): void
    {
        $lufthansa = Airline::firstOrCreate(['iata_code' => 'LH'], ['name' => 'Lufthansa', 'is_active' => true]);

        $shared = $this->template(['name' => 'EU carriers - initial', 'is_default' => false], [$this->airline->id, $lufthansa->id]);
        $house  = $this->template(['name' => 'House - initial', 'is_default' => false], []);

        $this->assertSame('Air Canada, Lufthansa', $shared->reachLabel());
        $this->assertTrue($house->appliesToAll());
        $this->assertSame('All airlines', $house->reachLabel());

        // Both are offered for either airline...
        $forAC = AirlineEmailTemplate::forAirline($this->airline)->pluck('id');
        $this->assertTrue($forAC->contains($shared->id));
        $this->assertTrue($forAC->contains($house->id));

        // ...and a house template also covers an airline nobody targeted.
        $other = Airline::firstOrCreate(['iata_code' => 'BA'], ['name' => 'British Airways', 'is_active' => true]);
        $forBA = AirlineEmailTemplate::forAirline($other)->pluck('id');
        $this->assertFalse($forBA->contains($shared->id));
        $this->assertTrue($forBA->contains($house->id));
    }

    public function test_an_airline_specific_template_beats_a_house_one(): void
    {
        $this->template(['name' => 'House - initial', 'is_default' => true], []);
        $specific = $this->template(['name' => 'Air Canada - initial', 'is_default' => false], [$this->airline->id]);

        $chosen = AirlineEmailTemplate::defaultFor($this->airline, AirlineEmailTemplate::TYPE_INITIAL);

        $this->assertSame($specific->id, $chosen->id, 'The airline\'s own wording wins over the house letter.');
    }

    public function test_nothing_can_be_sent_to_an_airline_before_the_customer_authorises_it(): void
    {
        // Eligible, but the customer has not confirmed and nothing is signed -
        // our letters assert a signed authority is attached, so sending would
        // be a false statement to the airline.
        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
            'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ]);

        [$allowed, $reason] = $claim->canContactAirline();
        $this->assertFalse($allowed);
        $this->assertStringContainsString('not confirmed', $reason);

        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim])
            ->set('to', 'claims@aircanada.ca')
            ->set('subject', 'Compensation claim')
            ->set('body', str_repeat('The body of the claim letter. ', 5))
            ->call('send');

        Mail::assertNothingSent();
        $this->assertSame(0, $claim->correspondence()->count());

        // Confirmed but still unsigned: still blocked, and the reason says so.
        $claim->forceFill(['confirmed_at' => now()])->save();
        $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 't@example.com',
            'role' => \App\Models\ClaimSigner::ROLE_PASSENGER, 'status' => \App\Models\ClaimSigner::STATUS_PENDING,
        ]);

        [$allowed, $reason] = $claim->fresh()->canContactAirline();
        $this->assertFalse($allowed);
        $this->assertStringContainsString('0 of 1 signatures', $reason);

        // Everyone has signed - now it may go.
        $claim->signers()->update(['status' => \App\Models\ClaimSigner::STATUS_SIGNED, 'signed_at' => now()]);
        [$allowed] = $claim->fresh()->load('signers')->canContactAirline();
        $this->assertTrue($allowed);
    }

    public function test_the_admin_can_nudge_the_customer_by_email_and_in_app_once_a_day(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
            'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ]);

        $component = Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim]);

        // Unconfirmed claim: the nudge asks them to confirm.
        $this->assertSame(\App\Notifications\ClaimActionNeeded::ACTION_CONFIRM, $component->instance()->customerAction()[0]);

        $component->call('remindCustomer');

        \Illuminate\Support\Facades\Notification::assertSentTo($this->customer, \App\Notifications\ClaimActionNeeded::class,
            function ($notification) {
                $payload = $notification->toDatabase($this->customer);

                // Both channels, one message.
                return in_array('mail', $notification->via($this->customer), true)
                    && in_array('database', $notification->via($this->customer), true)
                    && str_contains($payload['title'], 'Confirm');
            });

        $this->assertNotNull($claim->fresh()->reminded_at);
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'Customer reminded%')->exists());

        // A second nudge the same day is refused - a repeat reads as a fault.
        \Illuminate\Support\Facades\Notification::fake();
        Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim->fresh()])->call('remindCustomer');
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        // Once confirmed but unsigned, the nudge switches to signing.
        $claim->forceFill(['confirmed_at' => now(), 'reminded_at' => null])->save();
        $claim->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 't@example.com',
            'role' => \App\Models\ClaimSigner::ROLE_PASSENGER, 'status' => \App\Models\ClaimSigner::STATUS_PENDING,
        ]);

        $fresh = Livewire::actingAs($this->admin)->test(AdminClaimDetail::class, ['claim' => $claim->fresh()]);
        $this->assertSame(\App\Notifications\ClaimActionNeeded::ACTION_SIGN, $fresh->instance()->customerAction()[0]);
    }
}
