<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeBillingService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The unified Stripe webhook: one verified pipeline feeding BOTH billing
 * products - the legacy case plans (/admin/plans) and Unjamm Plus - with
 * neither able to claim the other's events.
 */
class StripeWebhookPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('user');
        config(['services.stripe.webhook_secret' => null]);
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('user');

        return $user;
    }

    /** A Plus subscription event payload, marked with our product metadata. */
    private function plusSubscriptionEvent(User $user, string $type, array $overrides = []): array
    {
        $plan = SubscriptionPlan::first();

        return ['type' => $type, 'data' => ['object' => array_merge([
            'id'       => 'sub_plus1',
            'object'   => 'subscription',
            'customer' => 'cus_plus1',
            'status'   => 'active',
            'metadata' => ['product' => 'unjamm_plus', 'user_id' => $user->id, 'plan_id' => $plan->id],
            'items'    => ['data' => [[
                'price' => ['id' => 'price_any', 'recurring' => ['interval' => 'month']],
                'current_period_start' => now()->timestamp,
                'current_period_end'   => now()->addMonth()->timestamp,
            ]]],
            'cancel_at_period_end' => false,
        ], $overrides)]];
    }

    // ── Both endpoints, one pipeline ────────────────────────

    public function test_the_dashboard_registered_legacy_url_processes_plus_events(): void
    {
        $user = $this->user();

        // /stripe/webhook is what the Stripe dashboard points at today.
        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($user, 'customer.subscription.created'))
            ->assertOk();

        $this->assertTrue($user->fresh()->load('subscriptions')->hasActiveSubscription());
    }

    public function test_the_api_url_feeds_the_same_pipeline(): void
    {
        $user = $this->user();

        $this->postJson('/api/webhooks/stripe', $this->plusSubscriptionEvent($user, 'customer.subscription.created'))
            ->assertOk()
            ->assertJsonPath('type', 'customer.subscription.created');

        $this->assertSame(1, Subscription::count());
    }

    // ── Product separation ──────────────────────────────────

    public function test_legacy_checkout_still_reaches_the_legacy_service(): void
    {
        $legacy = Mockery::mock(SubscriptionService::class);
        $legacy->shouldReceive('handleSuccessfulPayment')->once();
        $this->app->instance(SubscriptionService::class, $legacy);

        // A legacy purchase: client_reference_id "user_plan", no product marker.
        $this->postJson('/stripe/webhook', ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_legacy', 'object' => 'checkout.session', 'mode' => 'payment',
            'client_reference_id' => '7_3', 'metadata' => [],
        ]]])->assertOk();

        // And it never lands in the Plus table.
        $this->assertSame(0, Subscription::count());
    }

    public function test_a_plus_checkout_never_reaches_the_legacy_service(): void
    {
        $legacy = Mockery::mock(SubscriptionService::class);
        $legacy->shouldNotReceive('handleSuccessfulPayment');
        $this->app->instance(SubscriptionService::class, $legacy);

        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('retrieveAndSync')->once()->with('sub_plus9')->andReturn(null);
        $this->app->instance(StripeBillingService::class, $billing);

        $this->postJson('/stripe/webhook', ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_plus', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_plus9', 'metadata' => ['product' => 'unjamm_plus'],
        ]]])->assertOk();
    }

    public function test_a_legacy_subscription_object_is_never_mirrored_into_the_plus_table(): void
    {
        // No product marker, no matching plan price, not previously known:
        // this is the old product's subscription - the Plus sync must refuse it.
        $this->user(); // ensure users exist so a false match would be possible

        $this->postJson('/stripe/webhook', ['type' => 'customer.subscription.updated', 'data' => ['object' => [
            'id' => 'sub_legacy1', 'object' => 'subscription', 'customer' => 'cus_legacy',
            'status' => 'active', 'metadata' => [],
            'items' => ['data' => [['price' => ['id' => 'price_legacy', 'recurring' => ['interval' => 'year']]]]],
        ]]])->assertOk();

        $this->assertSame(0, Subscription::count());
    }

    public function test_a_plus_subscription_matched_by_plan_price_syncs_even_without_the_marker(): void
    {
        // Subscriptions created straight from the Stripe dashboard carry no
        // metadata - the plan's own price ID identifies them as ours.
        $user = $this->user(['stripe_customer_id' => 'cus_direct1']);
        SubscriptionPlan::first()->update(['stripe_monthly_price_id' => 'price_direct1']);

        $event = $this->plusSubscriptionEvent($user, 'customer.subscription.created', [
            'id' => 'sub_direct1', 'customer' => 'cus_direct1', 'metadata' => [],
        ]);
        $event['data']['object']['items']['data'][0]['price']['id'] = 'price_direct1';

        $this->postJson('/stripe/webhook', $event)->assertOk();

        $subscription = Subscription::where('stripe_subscription_id', 'sub_direct1')->first();
        $this->assertNotNull($subscription);
        // Resolved through the customer ID, not metadata.
        $this->assertSame($user->id, $subscription->user_id);
    }

    // ── Delivery semantics ──────────────────────────────────

    public function test_replayed_events_are_idempotent(): void
    {
        $user  = $this->user();
        $event = $this->plusSubscriptionEvent($user, 'customer.subscription.created');

        $this->postJson('/stripe/webhook', $event)->assertOk();
        $this->postJson('/stripe/webhook', $event)->assertOk();
        $this->postJson('/stripe/webhook', $event)->assertOk();

        $this->assertSame(1, Subscription::count());
    }

    public function test_out_of_order_delivery_converges_on_the_latest_state(): void
    {
        $user = $this->user();

        // The "deleted" event arrives first, then a stale "created" replay -
        // each sync mirrors the event's own payload, so last write wins.
        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($user, 'customer.subscription.deleted', ['status' => 'canceled']))->assertOk();
        $this->assertSame('canceled', Subscription::first()->status);

        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($user, 'customer.subscription.updated', ['status' => 'active']))->assertOk();
        $this->assertSame('active', Subscription::first()->status);
        $this->assertSame(1, Subscription::count());
    }

    public function test_a_handler_failure_returns_500_so_stripe_retries(): void
    {
        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('syncFromStripe')->andThrow(new \RuntimeException('db gone'));
        $this->app->instance(StripeBillingService::class, $billing);

        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($this->user(), 'customer.subscription.created'))
            ->assertStatus(500);
    }

    public function test_one_products_failure_does_not_stop_the_other(): void
    {
        // An unmarked subscription.deleted that matches a Plus plan price:
        // both products handle it. Legacy explodes; the Plus sync must still
        // run, and the response is 500 so Stripe retries.
        $legacy = Mockery::mock(SubscriptionService::class);
        $legacy->shouldReceive('handleCancellation')->once()->andThrow(new \RuntimeException('legacy boom'));
        $this->app->instance(SubscriptionService::class, $legacy);

        $user = $this->user(['stripe_customer_id' => 'cus_both1']);
        SubscriptionPlan::first()->update(['stripe_monthly_price_id' => 'price_both1']);

        $event = $this->plusSubscriptionEvent($user, 'customer.subscription.deleted', [
            'id' => 'sub_both1', 'customer' => 'cus_both1', 'status' => 'canceled', 'metadata' => [],
        ]);
        $event['data']['object']['items']['data'][0]['price']['id'] = 'price_both1';

        $this->postJson('/stripe/webhook', $event)->assertStatus(500);

        // The Plus side of the work still happened despite the legacy crash.
        $this->assertSame('canceled', Subscription::first()->status);
    }

    public function test_invoice_events_resync_the_subscription(): void
    {
        $billing = Mockery::mock(StripeBillingService::class);
        $billing->shouldReceive('retrieveAndSync')->twice()->with('sub_inv1')->andReturn(null);
        $this->app->instance(StripeBillingService::class, $billing);

        foreach (['invoice.paid', 'invoice.payment_failed'] as $type) {
            $this->postJson('/stripe/webhook', ['type' => $type, 'data' => ['object' => [
                'id' => 'in_1', 'object' => 'invoice', 'subscription' => 'sub_inv1',
                'metadata' => ['product' => 'unjamm_plus'], 'billing_reason' => 'subscription_cycle',
            ]]])->assertOk();
        }
    }

    public function test_unknown_and_malformed_payloads_are_handled(): void
    {
        // Unknown event type: acknowledged, nothing breaks.
        $this->postJson('/stripe/webhook', ['type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1']]])
            ->assertOk();

        // Not a Stripe event at all.
        $this->postJson('/stripe/webhook', ['hello' => 'world'])->assertStatus(400);
    }

    public function test_signature_is_mandatory_whenever_a_secret_is_configured(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        // Missing header.
        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($this->user(), 'customer.subscription.created'))
            ->assertStatus(400);

        // Forged header.
        $this->postJson('/stripe/webhook', $this->plusSubscriptionEvent($this->user(), 'customer.subscription.created'), ['Stripe-Signature' => 't=1,v1=forged'])
            ->assertStatus(400);

        $this->assertSame(0, Subscription::count());
    }

    public function test_a_correctly_signed_event_passes_verification(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $payload   = json_encode($this->plusSubscriptionEvent($this->user(), 'customer.subscription.created'));
        $timestamp = time();
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_test');

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        $this->assertSame(1, Subscription::count());
    }
}
