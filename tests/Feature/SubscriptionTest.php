<?php

namespace Tests\Feature;

use App\Livewire\Admin\FlightClaims\Claims as AdminClaims;
use App\Livewire\Admin\FlightClaims\Subscriptions;
use App\Models\Claim;
use App\Models\Itinerary;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Trip;
use App\Models\User;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\SubscriptionGate;
use App\Services\Claims\ClaimLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unjamm Plus subscriptions: the master switch, per-feature gating, plan
 * management and the webhook-synced local state. Fully independent of claim
 * compensation and success fees.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('user');
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function plusMember(): User
    {
        $user = $this->customer();

        Subscription::create([
            'user_id'                => $user->id,
            'subscription_plan_id'   => SubscriptionPlan::first()->id,
            'stripe_subscription_id' => 'sub_' . $user->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'current_period_end'     => now()->addMonth(),
        ]);

        return $user;
    }

    private function gateOn(array $features = ['flight_claims']): void
    {
        Setting::set('subscriptions.enabled', 1);
        Setting::set('subscriptions.features', json_encode(array_fill_keys($features, true)));
    }

    // ── The master switch ───────────────────────────────────

    public function test_everything_is_free_while_the_system_is_disabled(): void
    {
        // Even with every feature marked premium, the switch wins.
        Setting::set('subscriptions.enabled', 0);
        Setting::set('subscriptions.features', json_encode(array_fill_keys(array_keys(SubscriptionGate::FEATURES), true)));

        $this->assertTrue(SubscriptionGate::allows($this->customer(), 'flight_claims'));
        $this->assertTrue(SubscriptionGate::allows(null, 'flight_monitoring'));
        $this->assertSame([], SubscriptionGate::lockedFor($this->customer()));
    }

    public function test_enabling_the_system_gates_only_the_ticked_features(): void
    {
        $this->gateOn(['flight_claims']);
        $free = $this->customer();

        $this->assertFalse(SubscriptionGate::allows($free, 'flight_claims'));
        $this->assertTrue(SubscriptionGate::allows($free, 'flight_monitoring'));
        $this->assertTrue(SubscriptionGate::allows($this->plusMember(), 'flight_claims'));
    }

    public function test_stored_subscriptions_survive_a_disable_and_count_again_on_enable(): void
    {
        $member = $this->plusMember();

        Setting::set('subscriptions.enabled', 0);
        $this->assertTrue(SubscriptionGate::allows($member, 'flight_claims'));
        $this->assertSame(1, Subscription::count());

        $this->gateOn();
        $this->assertTrue($member->fresh()->load('subscriptions')->hasActiveSubscription());
    }

    // ── Access semantics ────────────────────────────────────

    public function test_access_follows_stripe_status(): void
    {
        $member       = $this->plusMember();
        $subscription = $member->subscriptions()->first();

        foreach (['active' => true, 'trialing' => true, 'past_due' => true, 'canceled' => false, 'unpaid' => false] as $status => $expected) {
            $subscription->forceFill(['status' => $status])->save();
            $this->assertSame($expected, $member->fresh()->load('subscriptions')->hasActiveSubscription(), "status {$status}");
        }
    }

    public function test_cancel_at_period_end_keeps_access_until_the_period_ends(): void
    {
        $member       = $this->plusMember();
        $subscription = $member->subscriptions()->first();

        $subscription->forceFill(['cancel_at_period_end' => true, 'current_period_end' => now()->addWeek()])->save();
        $this->assertTrue($member->fresh()->load('subscriptions')->hasActiveSubscription());

        $subscription->forceFill(['current_period_end' => now()->subDay()])->save();
        $this->assertFalse($member->fresh()->load('subscriptions')->hasActiveSubscription());
    }

    // ── Endpoint enforcement ────────────────────────────────

    public function test_gated_claim_creation_returns_402_with_an_upgrade_pointer(): void
    {
        $this->gateOn(['flight_claims']);

        $this->actingAs($this->customer())
            ->postJson(route('user.itineraries.api.claims.store'), [])
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required')
            ->assertJsonPath('upgrade_url', '/flight-disputes/plus');

        // A Plus member gets past the gate (into normal validation).
        $this->actingAs($this->plusMember())
            ->postJson(route('user.itineraries.api.claims.store'), [])
            ->assertStatus(422);
    }

    public function test_every_claim_creation_path_is_gated_not_just_the_manual_funnel(): void
    {
        // Regression: a free user created a claim by UPLOADING A TICKET while
        // "flight claims" was Plus-only - only the manual funnel was gated.
        $this->gateOn(['flight_claims']);
        $free = $this->customer();

        // Path 1: ticket upload (the hole that was found).
        $this->actingAs($free)
            ->postJson(route('user.itineraries.api.store'), [
                'file' => UploadedFile::fake()->create('ticket.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');

        // Path 2: converting an eligible monitored trip into a claim.
        $trip = Trip::create([
            'user_id' => $free->id, 'source' => 'manual', 'status' => 'disrupted',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'LHR', 'flight_number' => 'AC856',
            'departure_date' => now()->subDays(3)->toDateString(), 'eligibility_status' => 'eligible',
        ]);
        $this->actingAs($free)
            ->postJson(route('user.itineraries.api.trips.claim', $trip))
            ->assertStatus(402);

        // Path 3: the central chokepoint (also covers inbound email) refuses
        // to create claims for a gated free user...
        $itinerary = Itinerary::create([
            'user_id' => $free->id, 'original_filename' => 't.pdf', 'file_path' => 'x/t.pdf',
            'file_size' => 1, 'mime_type' => 'application/pdf', 'status' => Itinerary::STATUS_PARSED,
        ]);
        Claim::ensureForItinerary($itinerary);
        $this->assertSame(0, Claim::where('user_id', $free->id)->count());

        // ...but creates them for a Plus member.
        $member = $this->plusMember();
        $memberItinerary = Itinerary::create([
            'user_id' => $member->id, 'original_filename' => 't.pdf', 'file_path' => 'x/t2.pdf',
            'file_size' => 1, 'mime_type' => 'application/pdf', 'status' => Itinerary::STATUS_PARSED,
        ]);
        Claim::ensureForItinerary($memberItinerary);
        $this->assertSame(1, Claim::where('user_id', $member->id)->count());
    }

    public function test_multi_passenger_confirmation_requires_plus_when_gated(): void
    {
        $this->gateOn(['multi_passenger']);
        $consents = ['consents' => ['accuracy' => 1, 'authorization' => 1, 'terms' => 1, 'privacy' => 1]];

        $family = function (User $user) {
            $itinerary = Itinerary::create([
                'user_id' => $user->id, 'original_filename' => 'family.pdf', 'file_path' => 'x/f.pdf',
                'file_size' => 1, 'mime_type' => 'application/pdf', 'status' => Itinerary::STATUS_PARSED,
            ]);
            $itinerary->passengers()->create(['full_name' => 'Parent One', 'type' => 'ADT']);
            $itinerary->passengers()->create(['full_name' => 'Child One', 'type' => 'CHD']);

            return Claim::create([
                'user_id' => $user->id, 'itinerary_id' => $itinerary->id, 'status' => Claim::STATUS_ELIGIBLE,
                'workflow_state' => 'draft', 'airline' => 'Air Canada', 'flight_number' => 'AC1540',
                'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10',
                'passenger_name' => 'Parent One',
            ]);
        };

        // Free user + 2-passenger booking (uploaded or emailed ticket): 402.
        $free = $this->customer();
        $this->actingAs($free)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($family($free)->id)), $consents)
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');

        // A single-passenger claim confirms fine for the same free user.
        $solo = Claim::create([
            'user_id' => $free->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC1541', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-11', 'passenger_name' => 'Solo Traveller',
        ]);
        $this->actingAs($free)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($solo->id)), $consents)
            ->assertOk();

        // A Plus member confirms the whole family.
        $member = $this->plusMember();
        $this->actingAs($member)
            ->postJson(route('user.itineraries.api.claims.confirm', encrypt_id($family($member)->id)), $consents)
            ->assertOk();
    }

    public function test_priority_queue_floats_plus_claims_when_the_admin_gates_it(): void
    {
        $free   = $this->customer();
        $member = $this->plusMember();

        $make = fn (User $user, string $flight, $when) => Claim::forceCreate([
            'user_id' => $user->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => $flight, 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'P',
            'created_at' => $when, 'updated_at' => $when,
        ]);

        // The free user's claim is NEWER - normally it lists first.
        $make($member, 'AC100', now()->subDay());
        $make($free, 'AC200', now());

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Not gated: date order wins, no reordering.
        $this->gateOn(['flight_claims']);
        $first = Livewire::actingAs($admin)->test(AdminClaims::class)
            ->viewData('claims')->first();
        $this->assertSame('AC200', $first->flight_number);

        // Gated as a Plus perk: the member's older claim jumps the queue.
        $this->gateOn(['priority_processing']);
        $first = Livewire::actingAs($admin)->test(AdminClaims::class)
            ->viewData('claims')->first();
        $this->assertSame('AC100', $first->flight_number);
        $this->assertNotNull($first->is_plus_member);
    }

    public function test_gated_ai_drafting_falls_back_to_the_template_without_calling_gemini(): void
    {
        $this->gateOn(['ai_claim_drafting']);
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake();

        $claim = Claim::create([
            'user_id' => $this->customer()->id, 'status' => Claim::STATUS_ELIGIBLE,
            'workflow_state' => 'ready_to_file', 'airline' => 'Air Canada', 'flight_number' => 'AC1540',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10',
            'passenger_name' => 'T', 'flight_cancelled' => true, 'disruption_type' => 'cancelled',
            'eligibility_regulation' => 'APPR', 'eligibility_article' => 'Section 19',
            'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ]);

        $draft = app(ClaimLetterService::class)->generate($claim);

        // Free customer's letter still goes out - via the template, no AI spend.
        $this->assertSame('template', $draft['generated_by']);
        Http::assertNothingSent();
    }

    public function test_trip_monitoring_gate_is_independent_of_the_claims_gate(): void
    {
        $this->gateOn(['flight_monitoring']);

        $this->actingAs($this->customer())
            ->postJson(route('user.itineraries.api.trips.store'), [])
            ->assertStatus(402);

        // Claims stay free - only monitoring was ticked.
        $this->actingAs($this->customer())
            ->postJson(route('user.itineraries.api.claims.store'), [])
            ->assertStatus(422);
    }

    // ── Admin module ────────────────────────────────────────

    public function test_admin_creates_and_edits_plans_and_stripe_syncs_automatically(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Stripe is managed for the admin - saving a plan syncs it.
        $billing = \Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('configured')->andReturn(true);
        $billing->shouldReceive('syncPlan')->once()
            ->withArgs(fn (SubscriptionPlan $plan) => $plan->key === 'business');
        $this->app->instance(StripeBillingService::class, $billing);

        Livewire::actingAs($admin)->test(Subscriptions::class)
            ->call('editPlan')
            ->set('plan.key', 'business')
            ->set('plan.name', 'Unjamm Business')
            ->set('plan.monthly_price', 19.99)
            ->set('plan.annual_price', 199)
            ->set('plan.currency', 'cad')
            ->set('plan.trial_days', 14)
            ->set('plan.sort', 2)
            ->set('plan.perks_text', "Everything in Plus\nTeam accounts")
            ->call('savePlan')
            ->assertHasNoErrors();

        $plan = SubscriptionPlan::where('key', 'business')->first();
        $this->assertSame('CAD', $plan->currency);
        $this->assertSame(['Everything in Plus', 'Team accounts'], $plan->perks);
        $this->assertSame(14, $plan->trial_days);

        // Duplicate keys are rejected.
        Livewire::actingAs($admin)->test(Subscriptions::class)
            ->call('editPlan')
            ->set('plan.key', 'business')
            ->set('plan.name', 'Copy')
            ->set('plan.currency', 'EUR')
            ->set('plan.trial_days', 0)
            ->set('plan.sort', 3)
            ->call('savePlan')
            ->assertHasErrors('plan.key');
    }

    public function test_admin_toggles_the_system_and_feature_gates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Subscriptions::class)
            ->set('systemEnabled', true)
            ->set('features.flight_claims', true);

        $this->assertTrue(SubscriptionGate::enabled());
        $this->assertTrue(SubscriptionGate::requiresSubscription('flight_claims'));
        $this->assertFalse(SubscriptionGate::requiresSubscription('flight_monitoring'));
    }

    public function test_deactivated_plan_disappears_from_the_customer_offer(): void
    {
        $plan = SubscriptionPlan::first();
        $plan->update(['is_active' => false]);
        Setting::set('subscriptions.enabled', 1);

        $this->actingAs($this->customer())
            ->getJson(route('user.itineraries.api.billing.overview'))
            ->assertOk()
            ->assertJsonCount(0, 'data.plans');
    }

    // ── Webhook sync ────────────────────────────────────────

    public function test_webhook_syncs_a_subscription_lifecycle(): void
    {
        config(['services.stripe.webhook_secret' => null]); // signature covered separately

        $user = $this->customer();
        $user->forceFill(['stripe_customer_id' => 'cus_123'])->save();
        $plan = SubscriptionPlan::first();
        $plan->update(['stripe_monthly_price_id' => 'price_m1']);

        $payload = fn (string $type, array $sub) => [
            'type' => $type,
            'data' => ['object' => array_merge([
                'id'       => 'sub_wh1',
                'object'   => 'subscription',
                'customer' => 'cus_123',
                'status'   => 'active',
                'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id],
                'items'    => ['data' => [[
                    'price' => ['id' => 'price_m1', 'recurring' => ['interval' => 'month']],
                    'current_period_start' => now()->timestamp,
                    'current_period_end'   => now()->addMonth()->timestamp,
                ]]],
                'cancel_at_period_end' => false,
            ], $sub)],
        ];

        // Created -> active member.
        $this->postJson('/api/webhooks/stripe', $payload('customer.subscription.created', []))->assertOk();
        $subscription = Subscription::where('stripe_subscription_id', 'sub_wh1')->first();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertSame('monthly', $subscription->interval);
        $this->assertTrue($user->fresh()->load('subscriptions')->hasActiveSubscription());

        // Updated -> cancel at period end.
        $this->postJson('/api/webhooks/stripe', $payload('customer.subscription.updated', ['cancel_at_period_end' => true]))->assertOk();
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);

        // Deleted -> access gone.
        $this->postJson('/api/webhooks/stripe', $payload('customer.subscription.deleted', ['status' => 'canceled', 'canceled_at' => now()->timestamp]))->assertOk();
        $this->assertSame('canceled', $subscription->fresh()->status);
        $this->assertFalse($user->fresh()->load('subscriptions')->hasActiveSubscription());
    }

    public function test_webhook_with_a_bad_signature_is_rejected(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->postJson('/api/webhooks/stripe', ['type' => 'customer.subscription.created'], ['Stripe-Signature' => 't=1,v1=bad'])
            ->assertStatus(400);

        $this->assertSame(0, Subscription::count());
    }

    // ── Customer API ────────────────────────────────────────

    public function test_billing_overview_reports_plans_and_membership_state(): void
    {
        Setting::set('subscriptions.enabled', 1);

        $data = $this->actingAs($this->plusMember())
            ->getJson(route('user.itineraries.api.billing.overview'))
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['enabled']);
        $this->assertTrue($data['is_plus']);
        $this->assertSame('Unjamm Plus', $data['subscription']['plan']);
        // Pricing is in Canadian dollars.
        $this->assertSame('CAD', $data['plans'][0]['currency'] ?? null);
    }

    public function test_checkout_refuses_while_the_system_is_off_or_already_subscribed(): void
    {
        $plan = SubscriptionPlan::first();

        // System off.
        $this->actingAs($this->customer())
            ->postJson(route('user.itineraries.api.billing.checkout'), ['plan' => $plan->id, 'interval' => 'monthly'])
            ->assertStatus(422);

        // Already a member.
        Setting::set('subscriptions.enabled', 1);
        config(['services.stripe.secret' => 'sk_test_x']);
        $this->actingAs($this->plusMember())
            ->postJson(route('user.itineraries.api.billing.checkout'), ['plan' => $plan->id, 'interval' => 'monthly'])
            ->assertStatus(422);
    }

    public function test_subscription_module_never_touches_claim_compensation(): void
    {
        // The gate blocks claim CREATION only - existing claims, their
        // amounts and fees are untouched by subscription state.
        $this->gateOn(array_keys(SubscriptionGate::FEATURES));
        $member = $this->plusMember();

        $claim = Claim::create([
            'user_id' => $member->id, 'status' => Claim::STATUS_ELIGIBLE,
            'workflow_state' => 'ready_to_file', 'airline' => 'Air Canada', 'flight_number' => 'AC1540',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10',
            'passenger_name' => 'T', 'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ]);

        $member->subscriptions()->first()->forceFill(['status' => 'canceled'])->save();

        $this->assertSame('400.00', $claim->fresh()->compensation_amount);
        $this->assertSame('ready_to_file', $claim->fresh()->workflow_state);
    }

    public function test_members_can_manage_their_membership_from_the_profile(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_profile']);
        $user->assignRole('user');

        $subscription = $user->subscriptions()->create([
            'subscription_plan_id'   => SubscriptionPlan::query()->value('id'),
            'stripe_customer_id'     => 'cus_profile',
            'stripe_subscription_id' => 'sub_profile',
            'stripe_price_id'        => 'price_x',
            'interval'               => 'month',
            'status'                 => 'active',
            'current_period_end'     => now()->addMonth(),
        ]);

        $component = Livewire::actingAs($user)->test(\App\Livewire\User\PlusMembership::class);

        // The panel shows the live membership, not the legacy product.
        $component->assertSee('Renews')
            ->assertSee($subscription->current_period_end->format('d M Y'))
            ->assertSee('Pause billing');

        // Pausing validates its date before it ever reaches Stripe.
        $component->set('pauseUntil', now()->subDay()->toDateString())
            ->call('pause')
            ->assertHasErrors('pauseUntil');

        // Cancelling asks first - the confirmation is part of the flow.
        $component->call('$set', 'confirmingCancel', true)
            ->assertSee('Cancel Unjamm Plus?')
            ->assertSee('Keep my membership')
            // The gentler option is offered before the destructive one.
            ->assertSee('Pause billing');
    }

    public function test_a_free_user_sees_an_invitation_not_billing_controls(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(\App\Livewire\User\PlusMembership::class)
            ->assertSee('Unjamm Plus')
            ->assertDontSee('Cancel membership')
            ->assertDontSee('Pause billing');
    }
}
