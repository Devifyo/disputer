<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Dashboard\FlightDashboard;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The customer dashboard is the flight-dispute product: money, claims,
 * monitored trips - and, first, whatever is waiting on the customer.
 */
class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Role::findOrCreate('user');
        Role::findOrCreate('admin')->givePermissionTo(
            \Spatie\Permission\Models\Permission::whereIn('name', ['payments.view', 'payments.manage'])->get()
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function claim(array $overrides = []): Claim
    {
        return Claim::create(array_merge([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'draft',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
            'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ], $overrides));
    }

    public function test_the_dashboard_totals_money_paid_and_money_still_being_claimed(): void
    {
        $paidClaim = $this->claim(['workflow_state' => 'paid', 'flight_number' => 'AC1']);
        $this->claim(['flight_number' => 'AC2']);   // still open: 400 expected

        $payment = app(PaymentService::class)->record($paidClaim, [
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $this->admin);
        $payment->forceFill(['status' => Payment::STATUS_PAID])->save();

        $data = app(FlightDashboard::class)->for($this->customer);

        // Money comes back as data so the view can lay out two currencies.
        $this->assertSame([['currency' => 'CAD', 'amount' => 750.0]], $data['stats']['recovered'],
            'Paid figure is the net the customer received.');
        $this->assertSame([['currency' => 'CAD', 'amount' => 400.0]], $data['stats']['expected']);
        $this->assertSame(2, $data['stats']['claims_total']);
        $this->assertSame(1, $data['stats']['claims_active'], 'A paid claim is no longer in progress.');
    }

    public function test_unconfirmed_and_unsigned_claims_surface_as_actions_for_the_customer(): void
    {
        $unconfirmed = $this->claim(['flight_number' => 'AC10']);

        $signing = $this->claim(['flight_number' => 'AC11', 'workflow_state' => 'awaiting_signature', 'confirmed_at' => now()]);
        $signing->signers()->create([
            'name' => 'Tenzin Hagyal', 'email' => 't@example.com',
            'role' => ClaimSigner::ROLE_PASSENGER, 'status' => ClaimSigner::STATUS_PENDING,
        ]);

        $labels = collect(app(FlightDashboard::class)->for($this->customer)['actions'])->pluck('label');

        $this->assertTrue($labels->contains('Confirm your claim'));
        $this->assertTrue($labels->contains('Sign your authorisation'));
    }

    public function test_money_waiting_with_no_bank_account_is_the_first_thing_asked_for(): void
    {
        $claim   = $this->claim(['workflow_state' => 'responded', 'confirmed_at' => now()]);
        app(PaymentService::class)->record($claim, [
            'gross_amount' => 800, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $this->admin);

        $actions = app(FlightDashboard::class)->for($this->customer)['actions'];

        $this->assertSame('Add your bank details', $actions[0]['label'], 'Nothing blocks the payout more than this.');
    }

    public function test_trips_are_counted_and_the_page_renders_for_a_brand_new_customer(): void
    {
        Trip::create([
            'user_id' => $this->customer->id, 'airline' => 'Air Canada', 'flight_number' => 'AC900',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'LHR', 'departure_date' => now()->addWeek(),
            'monitoring_status' => Trip::MONITORING_ACTIVE,
        ]);

        $data = app(FlightDashboard::class)->for($this->customer);
        $this->assertSame(1, $data['stats']['trips_watched']);

        // A customer with nothing yet still gets a usable page, not an error.
        $fresh = User::factory()->create();
        $fresh->assignRole('user');
        $this->actingAs($fresh)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('No claims yet')
            ->assertSee('No trips being watched');
    }

    public function test_claims_show_a_customer_state_not_the_internal_workflow_word(): void
    {
        $eligible  = $this->claim(['flight_number' => 'AC20']);                                   // eligible, unconfirmed
        $reviewing = $this->claim(['flight_number' => 'AC21', 'status' => Claim::STATUS_PENDING_ELIGIBILITY]);
        $signing   = $this->claim(['flight_number' => 'AC22', 'workflow_state' => 'awaiting_signature', 'confirmed_at' => now()]);
        $paid      = $this->claim(['flight_number' => 'AC23', 'workflow_state' => 'paid', 'confirmed_at' => now()]);

        $this->assertSame('Confirm to continue', $eligible->customerStage()[0]);
        $this->assertSame('In review', $reviewing->customerStage()[0]);
        $this->assertSame('Signature needed', $signing->customerStage()[0]);
        $this->assertSame('Paid', $paid->customerStage()[0]);

        // The dashboard renders those words - never the raw workflow state.
        $this->actingAs($this->customer)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Confirm to continue')
            ->assertDontSee('>DRAFT<', false);
    }

    public function test_a_paid_claim_never_still_reads_eligible_to_the_customer(): void
    {
        $claim = $this->claim(['flight_number' => 'AC77']);

        // Eligibility says "eligible" forever - but the money has landed, and
        // that is what the customer should see in their list.
        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 600, 'currency' => 'EUR', 'payment_date' => now()->toDateString(),
        ], $this->admin);
        $payment->forceFill(['status' => Payment::STATUS_PAID])->save();

        [$label, , $tone] = $claim->fresh()->customerStage();
        $this->assertSame('Paid out', $label);
        $this->assertSame('success', $tone);

        // A second instalment still awaiting payout must not read "Paid out" -
        // one payout landing does not mean the rest has.
        app(PaymentService::class)->record($claim, [
            'gross_amount' => 200, 'currency' => 'EUR', 'payment_date' => now()->toDateString(),
        ], $this->admin);

        $this->assertSame('Partly paid', $claim->fresh()->customerStage()[0]);

        // Money in, nothing paid out yet: the customer is told it is coming.
        $waiting = $this->claim(['flight_number' => 'AC78']);
        app(PaymentService::class)->record($waiting, [
            'gross_amount' => 300, 'currency' => 'EUR', 'payment_date' => now()->toDateString(),
        ], $this->admin);

        $this->assertSame('Payout on the way', $waiting->fresh()->customerStage()[0]);

        // The API sends it, so the SPA badge and filters follow the journey.
        $payload = collect($this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.index'))
            ->json('data'))->firstWhere('number', $claim->number);

        $this->assertSame('Partly paid', $payload['stage_label']);
        $this->assertSame('Eligible for Compensation', $payload['status_label'], 'The verdict itself is unchanged.');
    }
}
