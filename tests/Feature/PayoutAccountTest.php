<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WisePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Customer payout bank accounts: full numbers in, encrypted at rest, masked
 * tails out - and the Wise recipient uses the customer's own account.
 */
class PayoutAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Role::findOrCreate('admin');
        Role::findOrCreate('user');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    public function test_each_currency_validates_its_own_fields(): void
    {
        // Garbage IBAN refused, valid one accepted.
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'EUR', 'account_holder_name' => 'Tenzin Hagyal', 'iban' => 'not-an-iban',
            ])->assertJsonValidationErrors('iban');

        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'EUR', 'account_holder_name' => 'Tenzin Hagyal', 'iban' => 'DE89 3704 0044 0532 0130 00',
            ])->assertOk();

        // GBP: dashes are tolerated (normalised away)...
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'GBP', 'account_holder_name' => 'T', 'sort_code' => '23-14-70', 'account_number' => '28821822',
            ])->assertOk();

        // ...but a genuinely wrong sort code is refused.
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'GBP', 'account_holder_name' => 'T', 'sort_code' => '12345', 'account_number' => '28821822',
            ])->assertJsonValidationErrors('sort_code');

        // CAD needs institution + transit + account.
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'CAD', 'account_holder_name' => 'T', 'institution_number' => '004', 'transit_number' => '00022', 'account_number' => '1234567',
            ])->assertOk();
    }

    public function test_account_numbers_are_encrypted_at_rest_and_masked_on_the_way_out(): void
    {
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.store'), [
                'currency' => 'EUR', 'account_holder_name' => 'Tenzin Hagyal', 'iban' => 'DE89370400440532013000',
            ])->assertOk();

        // Raw database row never contains the IBAN.
        $raw = DB::table('user_payout_accounts')->first();
        $this->assertStringNotContainsString('DE89370400440532013000', $raw->details);
        $this->assertSame('····3000', $raw->masked);

        // The API returns only the masked tail - even to the owner.
        $payload = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.payout-accounts'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('DE89370400440532013000', $payload);
        $this->assertStringContainsString('3000', json_decode($payload, true)['data']['accounts'][0]['masked']);

        // The model decrypts for the payout pipeline.
        $this->assertSame('DE89370400440532013000', UserPayoutAccount::first()->details['iban']);
    }

    public function test_a_customer_cannot_touch_anothers_accounts(): void
    {
        $other = User::factory()->create();
        $other->assignRole('user');
        $other->payoutAccounts()->create([
            'currency' => 'EUR', 'account_holder_name' => 'Other', 'type' => 'iban',
            'details' => ['iban' => 'DE89370400440532013000'], 'masked' => '····3000',
        ]);

        $payload = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.payout-accounts'))
            ->assertOk()->json('data.accounts');

        $this->assertCount(0, $payload);

        // Deleting by currency only ever touches your own rows.
        $this->actingAs($this->customer)
            ->deleteJson(route('user.itineraries.api.payout-accounts.destroy', 'EUR'))
            ->assertOk();
        $this->assertSame(1, $other->payoutAccounts()->count());
    }

    public function test_wise_recipient_uses_the_customers_saved_account(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77', 'services.wise.sandbox' => false]);
        Role::findOrCreate('admin')->givePermissionTo(['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->customer->payoutAccounts()->create([
            'currency' => 'EUR', 'account_holder_name' => 'Tenzin Hagyal', 'type' => 'iban',
            'details' => ['iban' => 'DE89370400440532013000'], 'masked' => '····3000',
        ]);

        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'responded',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'Tenzin Hagyal',
        ]);

        Http::fake([
            '*/v3/profiles/77/quotes'      => Http::response(['id' => 'q1', 'rate' => 0.68, 'targetAmount' => 510.00], 200),
            '*/v1/accounts'                => Http::response(['id' => 9001], 200),
            '*/v1/transfers'               => Http::response(['id' => 555001, 'status' => 'processing'], 200),
            '*/v3/profiles/77/transfers/*' => Http::response(['status' => 'COMPLETED'], 200),
        ]);

        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $admin);
        $wise   = app(WisePayoutService::class);
        $payout = $wise->draft($payment, 'EUR', $admin);
        $wise->send($payout, $admin);

        // The recipient created at Wise is the customer's own IBAN account -
        // not an email recipient.
        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/v1/accounts')) {
                return false;
            }

            return $request['type'] === 'iban'
                && $request['details']['IBAN'] === 'DE89370400440532013000'
                && $request['details']['legalType'] === 'PRIVATE';
        });

        // The payout shows only the masked tail.
        $this->assertSame('····3000', $payout->fresh()->recipient_iban);
    }

    public function test_the_first_account_becomes_the_default_and_the_customer_can_switch(): void
    {
        $this->actingAs($this->customer)->postJson(route('user.itineraries.api.payout-accounts.store'), [
            'currency' => 'EUR', 'account_holder_name' => 'T', 'iban' => 'DE89370400440532013000',
        ])->assertOk()->assertJsonPath('data.accounts.0.is_default', true);

        // A second account does not steal the default.
        $this->actingAs($this->customer)->postJson(route('user.itineraries.api.payout-accounts.store'), [
            'currency' => 'GBP', 'account_holder_name' => 'T', 'sort_code' => '231470', 'account_number' => '28821822',
        ])->assertOk();
        $this->assertTrue($this->customer->payoutAccounts()->where('currency', 'EUR')->value('is_default'));

        // The customer switches payouts to GBP - exactly one default remains.
        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.payout-accounts.default', 'GBP'))
            ->assertOk();
        $this->assertTrue($this->customer->payoutAccounts()->where('currency', 'GBP')->value('is_default'));
        $this->assertSame(1, $this->customer->payoutAccounts()->where('is_default', true)->count());

        // Deleting the default promotes the remaining account.
        $this->actingAs($this->customer)
            ->deleteJson(route('user.itineraries.api.payout-accounts.destroy', 'GBP'))
            ->assertOk();
        $this->assertTrue($this->customer->payoutAccounts()->where('currency', 'EUR')->value('is_default'));
    }

    public function test_the_payout_destination_is_the_customers_default_account_not_an_admin_choice(): void
    {
        Role::findOrCreate('admin')->givePermissionTo(['payments.view', 'payments.manage', 'payouts.send']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->customer->payoutAccounts()->create([
            'currency' => 'EUR', 'account_holder_name' => 'T', 'type' => 'iban',
            'details' => ['iban' => 'DE89370400440532013000'], 'masked' => '····3000',
        ]);
        $gbp = $this->customer->payoutAccounts()->create([
            'currency' => 'GBP', 'account_holder_name' => 'T', 'type' => 'sort_code',
            'details' => ['sort_code' => '231470', 'account_number' => '28821822'], 'masked' => '····1822', 'is_default' => true,
        ]);

        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'responded',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'T',
        ]);
        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $admin);

        // The admin asked for CAD - the customer's default GBP account wins.
        $payout = app(WisePayoutService::class)->draft($payment, 'CAD', $admin);

        $this->assertSame('GBP', $payout->currency);
        $this->assertSame($gbp->id, $payout->user_payout_account_id);
        $this->assertSame('····1822', $payout->recipient_iban);
    }

    public function test_admin_can_request_bank_details_from_the_customer(): void
    {
        Role::findOrCreate('admin')->givePermissionTo(['payments.view', 'payments.manage']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'responded',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'T',
        ]);
        $payments = app(PaymentService::class);
        $payment  = $payments->record($claim, [
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $admin);

        $payments->requestBankDetails($payment, $admin);

        Notification::assertSentTo($this->customer, \App\Notifications\PaymentEvent::class,
            fn ($n) => str_contains($n->toDatabase($this->customer)['title'], 'bank details'));
        $this->assertDatabaseHas('payment_logs', ['payment_id' => $payment->id, 'action' => 'bank_details_requested']);
    }

    public function test_without_a_saved_account_live_falls_back_to_email_recipient(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77', 'services.wise.sandbox' => false]);
        Role::findOrCreate('admin')->givePermissionTo(['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $claim = Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'responded',
            'airline' => 'Air Canada', 'flight_number' => 'AC1540', 'departure_airport' => 'YYZ',
            'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10', 'passenger_name' => 'T',
        ]);

        Http::fake([
            '*/v3/profiles/77/quotes'      => Http::response(['id' => 'q1', 'rate' => 1, 'targetAmount' => 750.00], 200),
            '*/v1/accounts'                => Http::response(['id' => 9001], 200),
            '*/v1/transfers'               => Http::response(['id' => 555001, 'status' => 'processing'], 200),
            '*/v3/profiles/77/transfers/*' => Http::response(['status' => 'COMPLETED'], 200),
        ]);

        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
        ], $admin);
        $wise   = app(WisePayoutService::class);
        $payout = $wise->draft($payment, 'CAD', $admin);
        $wise->send($payout, $admin);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/accounts')
            && $request['type'] === 'email'
            && $request['details']['email'] === $this->customer->email);
    }
}
