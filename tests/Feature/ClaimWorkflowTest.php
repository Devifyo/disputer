<?php

namespace Tests\Feature;

use App\Mail\GenericEmail;
use App\Models\Airline;
use App\Models\Claim;
use App\Models\ClaimLifecycleStage;
use App\Models\ClaimWorkflow;
use App\Models\ClaimWorkflowTimer;
use App\Models\User;
use App\Services\Claims\ClaimWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Claim workflow engine: configurable lifecycle stages, enforced
 * transitions, timers, audit trail and admin notifications.
 */
class ClaimWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ClaimWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.gemini.api_key' => null]); // deterministic template drafts
        Role::findOrCreate('admin')->givePermissionTo(
            \Spatie\Permission\Models\Permission::whereIn('name', [
                'airlines.manage', 'claim_templates.manage', 'claim_templates.delete',
                'claim_drafts.generate', 'claim_emails.send',
            ])->get()
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('user');
        $this->workflow = app(ClaimWorkflowService::class);
    }

    private function claim(string $state = 'draft'): Claim
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return Claim::create([
            'user_id'           => $user->id,
            'status'            => Claim::STATUS_ELIGIBLE,
            'workflow_state'    => $state,
            'airline'           => 'Air Canada',
            'flight_number'     => 'AC1540',
            'departure_airport' => 'YYZ',
            'arrival_airport'   => 'IAD',
            'flight_date'       => '2026-07-10',
            'passenger_name'    => 'Tenzin Hagyal',
        ]);
    }

    public function test_happy_path_walks_the_configured_lifecycle_with_a_full_audit_trail(): void
    {
        $claim = $this->claim();

        $claim = $this->workflow->transition($claim, 'awaiting_signature', 'customer');
        $claim = $this->workflow->transition($claim, 'ready_to_file', 'system');
        $claim = $this->workflow->transition($claim, 'filed', 'admin', null, 'Filed by admin.', [
            'filed_at' => now(),
            'filing'   => ['recipient' => 'claims@aircanada.ca', 'attachments' => ['assignment']],
        ]);

        // Filing auto-chains into awaiting response and starts the 30-day timer.
        $this->assertSame('awaiting_response', $claim->workflow_state);
        $timer = $claim->workflowTimers()->where('status', ClaimWorkflowTimer::STATUS_PENDING)->first();
        $this->assertNotNull($timer);
        $this->assertSame('awaiting_escalation', $timer->meta['to_stage']);
        $this->assertTrue($timer->due_at->between(now()->addDays(29), now()->addDays(31)));

        $claim = $this->workflow->transition($claim, 'responded', 'admin', null, 'Airline replied.');
        $claim = $this->workflow->transition($claim, 'paid', 'admin');
        $claim = $this->workflow->transition($claim, 'closed', 'admin');

        $this->assertSame('closed', $claim->workflow_state);
        // Leaving awaiting_response cancelled its timer.
        $this->assertSame(ClaimWorkflowTimer::STATUS_CANCELLED, $timer->fresh()->status);

        $audit = $claim->auditLogs()->reorder('id')->get();
        $this->assertSame(
            ['draft', 'awaiting_signature', 'ready_to_file', 'filed', 'awaiting_response', 'responded', 'paid'],
            $audit->whereNotNull('to_state')->pluck('from_state')->values()->all()
        );
        $this->assertSame('admin', $audit->last()->via);

        // Final state: no further moves.
        $this->assertFalse($this->workflow->can($claim, 'litigation', 'admin'));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $claim = $this->claim();

        $this->expectException(RuntimeException::class);
        $this->workflow->transition($claim, 'filed', 'admin');
    }

    public function test_auto_only_stages_cannot_be_entered_manually(): void
    {
        // ready_to_file is event-driven (signatures completed), never manual.
        $this->assertFalse($this->workflow->can($this->claim('awaiting_signature'), 'ready_to_file', 'admin'));
        $this->assertTrue($this->workflow->can($this->claim('awaiting_signature'), 'ready_to_file', 'system'));
    }

    public function test_expired_response_timer_moves_the_claim_to_escalation_and_alerts_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $claim = $this->claim('awaiting_response');
        $claim->workflowTimers()->create([
            'purpose' => 'stage_auto',
            'due_at'  => now()->subDay(),
            'meta'    => ['from_stage' => 'awaiting_response', 'to_stage' => 'awaiting_escalation'],
        ]);

        $this->artisan('claims:evaluate-workflow-timers')->assertSuccessful();

        $claim->refresh();
        $this->assertSame('awaiting_escalation', $claim->workflow_state);
        $this->assertSame(ClaimWorkflowTimer::STATUS_COMPLETED, $claim->workflowTimers()->first()->status);
        // Escalation is never automatic - only the notification goes out.
        Mail::assertSent(GenericEmail::class, fn (GenericEmail $mail) => str_contains($mail->htmlBody, 'Awaiting Admin Escalation'));
        $this->assertTrue($claim->auditLogs()->where('via', 'system')->where('action', 'like', '%Awaiting Admin Escalation%')->exists());
    }

    public function test_custom_stage_slots_into_the_flow_without_code_changes(): void
    {
        // Insert "Legal Review" between Ready To File and Filed - config only.
        ClaimLifecycleStage::create([
            'claim_workflow_id' => ClaimWorkflow::defaultId(),
            'key' => 'legal_review', 'name' => 'Legal Review', 'sort' => 35, 'color' => 'amber', 'icon' => 'scale',
            'is_active' => true, 'allow_manual' => true, 'customer_visible' => false,
            'next_stages' => ['filed'],
        ]);
        ClaimLifecycleStage::where('key', 'ready_to_file')->first()->update(['next_stages' => ['legal_review', 'filed']]);

        $claim = $this->claim('ready_to_file');

        $claim = $this->workflow->transition($claim, 'legal_review', 'admin');
        $this->assertSame('legal_review', $claim->workflow_state);
        // Customer-invisible stage adds no customer timeline entry.
        $this->assertFalse($claim->events()->where('label', 'like', '%Legal Review%')->exists());

        $claim = $this->workflow->transition($claim, 'filed', 'admin', null, null, ['filed_at' => now()]);
        $this->assertSame('awaiting_response', $claim->workflow_state);
    }

    public function test_stage_configuration_triggers_customer_notification_and_ai_draft(): void
    {
        // Configure the escalated stage: notify the customer + auto-draft the complaint.
        ClaimLifecycleStage::where('key', 'awaiting_escalation')->first()
            ->update(['notify_customer' => true, 'customer_visible' => true, 'ai_action' => 'regulator_complaint']);

        $claim = $this->claim('awaiting_response');
        $claim->forceFill([
            'contact_email'          => 'customer@example.com',
            'eligibility_regulation' => 'APPR',
            'eligibility_article'    => 'Section 19',
        ])->save();

        $this->workflow->transition($claim, 'awaiting_escalation', 'system', null, 'Deadline expired.');

        // Customer got the simplified stage email.
        Mail::assertSent(GenericEmail::class, fn (GenericEmail $mail) => str_contains($mail->htmlBody, 'Under review by our team'));

        // A regulator complaint DRAFT was prepared - stored, audited, not sent.
        $draft = $claim->drafts()->where('type', 'regulator_complaint')->first();
        $this->assertNotNull($draft);
        $this->assertStringContainsString('APPR Section 19', $draft->body);
        $this->assertTrue($claim->auditLogs()->where('action', 'like', 'AI draft prepared%')->exists());
    }

    public function test_stage_permissions_gate_manual_entry(): void
    {
        ClaimLifecycleStage::where('key', 'closed')->first()->update(['permissions' => ['superadmin']]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $claim = $this->claim('paid');
        $this->assertFalse($this->workflow->can($claim, 'closed', 'admin'));

        Role::findOrCreate('superadmin');
        $admin->assignRole('superadmin');
        $this->assertTrue($this->workflow->can($claim->fresh(), 'closed', 'admin'));
    }

    public function test_airline_attached_workflow_overrides_the_default(): void
    {
        // Air Canada gets its own workflow with a 60-day response window.
        $custom = ClaimWorkflow::find(ClaimWorkflow::defaultId())->duplicateAs('Air Canada process');
        $custom->stages()->where('key', 'awaiting_response')->first()->update(['auto_delay_days' => 60]);
        Airline::where('iata_code', 'AC')->first()->update(['claim_workflow_id' => $custom->id]);

        // AC claim follows the custom workflow...
        $acClaim = $this->claim('ready_to_file');
        $this->assertSame($custom->id, $acClaim->resolvedWorkflowId());

        $acClaim = $this->workflow->transition($acClaim, 'filed', 'admin', null, null, ['filed_at' => now()]);
        $timer   = $acClaim->workflowTimers()->where('status', ClaimWorkflowTimer::STATUS_PENDING)->first();
        $this->assertTrue($timer->due_at->between(now()->addDays(59), now()->addDays(61)));

        // ...while another carrier stays on the default 30-day workflow.
        $afClaim = $this->claim('ready_to_file');
        $afClaim->forceFill(['airline' => 'Air France', 'flight_number' => 'AF348'])->save();
        $this->assertSame(ClaimWorkflow::defaultId(), $afClaim->fresh()->resolvedWorkflowId());

        $afClaim = $this->workflow->transition($afClaim->fresh(), 'filed', 'admin', null, null, ['filed_at' => now()]);
        $afTimer = $afClaim->workflowTimers()->where('status', ClaimWorkflowTimer::STATUS_PENDING)->first();
        $this->assertTrue($afTimer->due_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_escalation_stage_routes_to_the_airline_escalation_contact(): void
    {
        $stage = ClaimLifecycleStage::byKey('awaiting_escalation');
        $this->assertSame('escalation', $stage->airline_contact_purpose);
    }

    public function test_customer_api_never_exposes_internal_workflow_artifacts(): void
    {
        $claim = $this->claim('awaiting_response');
        $claim->forceFill([
            'filing'         => ['recipient' => 'claims@aircanada.ca'],
            'airline_letter' => ['subject' => 'internal', 'body' => 'internal letter'],
        ])->save();

        $response = $this->actingAs($claim->user)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($claim->id)))
            ->assertOk();

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('airline_letter', $payload);
        $this->assertStringNotContainsString('claims@aircanada.ca', $payload);
        $this->assertStringNotContainsString('internal letter', $payload);
    }

    public function test_audit_entries_can_never_be_edited_or_deleted(): void
    {
        $claim = $this->claim('ready_to_file');
        $this->workflow->audit($claim, 'Claim filed with the airline', 'admin', null, 'original note');

        $entry = $claim->auditLogs()->first();

        try {
            $entry->update(['notes' => 'tampered']);
            $this->fail('Audit entries must not be updatable.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Audit entries must not be deletable.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertSame('original note', $claim->auditLogs()->first()->notes);
    }
}
