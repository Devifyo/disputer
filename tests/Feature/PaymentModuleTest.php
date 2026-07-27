<?php

namespace Tests\Feature;

use App\Jobs\ProcessWisePayout;
use App\Livewire\Admin\FlightClaims\Payments as AdminPayments;
use App\Models\Claim;
use App\Models\ClaimExpense;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\Payout;
use App\Models\PayoutTransaction;
use App\Models\User;
use App\Notifications\PaymentEvent;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WisePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Payments module: airline money in, 25% fee, Wise/manual payout out - every
 * cent in the append-only ledger and the immutable audit log, all money
 * actions individually permissioned.
 */
class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        // The migration attaches payment permissions to the admin role only
        // when it exists - in the test database it does not yet, so attach
        // here exactly as production has them.
        Role::findOrCreate('admin')
            ->givePermissionTo(['payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send']);
        Role::findOrCreate('user');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');
    }

    private function claim(): Claim
    {
        return Claim::create([
            'user_id' => $this->customer->id, 'status' => Claim::STATUS_ELIGIBLE,
            'workflow_state' => 'responded', 'airline' => 'Air Canada', 'flight_number' => 'AC1540',
            'departure_airport' => 'YYZ', 'arrival_airport' => 'IAD', 'flight_date' => '2026-07-10',
            'passenger_name' => 'Tenzin Hagyal',
            'compensation_amount' => '400.00', 'compensation_currency' => 'CAD',
        ]);
    }

    private function payment(array $overrides = []): Payment
    {
        return app(PaymentService::class)->record($this->claim(), array_merge([
            'gross_amount' => 1000, 'currency' => 'CAD', 'payment_date' => now()->toDateString(),
            'reference' => 'AC-REMIT-1',
        ], $overrides), $this->admin);
    }

    // ── Recording + the fee split ───────────────────────────

    public function test_recording_a_payment_splits_fee_and_net_and_writes_the_ledger(): void
    {
        $payment = $this->payment();

        $this->assertSame('250.00', $payment->fee_amount);
        $this->assertSame('750.00', $payment->net_amount);
        $this->assertSame(Payment::STATUS_RECEIVED, $payment->status);

        // Ledger: money in + fee out; audit: created with new values and IP.
        $this->assertSame(['fee_deducted', 'payment_received'], $payment->transactions->pluck('type')->sort()->values()->all());
        $this->assertSame('created', $payment->logs->last()->action);
        $this->assertNotNull($payment->logs->last()->new_values['gross_amount'] ?? null);

        // The customer is told (email template + in-app), admins alerted in-app.
        Notification::assertSentTo($this->customer, PaymentEvent::class);
        Notification::assertSentTo($this->admin, PaymentEvent::class);
    }

    public function test_fee_override_requires_its_own_permission_and_keeps_history(): void
    {
        // Simulate a junior admin: strip the permission from the role.
        Role::findOrCreate('admin')->revokePermissionTo('payments.override_fee');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $this->payment(['fee_percent' => 10]);
            $this->fail('Fee override without permission must be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // With the permission: override works and history is kept.
        Role::findOrCreate('admin')->givePermissionTo('payments.override_fee');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $payment = $this->payment(['fee_percent' => 10]);
        $this->assertSame('100.00', $payment->fee_amount);

        app(PaymentService::class)->overrideFee($payment, 20, $this->admin, 'negotiated');
        $payment->refresh();
        $this->assertSame('200.00', $payment->fee_amount);
        $this->assertSame('800.00', $payment->net_amount);

        // Calculation history: two fee rows in the ledger, old->new in audit.
        $this->assertSame(2, $payment->transactions()->where('type', 'fee_deducted')->count());
        $log = $payment->logs()->where('action', 'fee_changed')->first();
        $this->assertSame('10.00', $log->old_values['fee_percent']);
        $this->assertSame(20.0, (float) $log->new_values['fee_percent']);
    }

    public function test_expense_reimbursements_are_fee_free(): void
    {
        // Gross 1000 of which 200 reimburses receipts: fee is 25% of the
        // 800 compensation portion only.
        $payment = $this->payment(['expenses_amount' => 200]);

        $this->assertSame('200.00', $payment->expenses_amount);
        $this->assertSame('200.00', $payment->fee_amount);
        $this->assertSame('800.00', $payment->net_amount);

        // The ledger says so, out loud.
        $feeRow = $payment->transactions()->where('type', 'fee_deducted')->first();
        $this->assertStringContainsString('fee-free', $feeRow->notes);

        // A fee override keeps the exemption: 10% of 800, never of 1000.
        app(PaymentService::class)->overrideFee($payment, 10, $this->admin);
        $this->assertSame('80.00', $payment->fresh()->fee_amount);
        $this->assertSame('920.00', $payment->fresh()->net_amount);

        // The customer is told their expenses came back in full.
        $expenses = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($payment->claim_id)))
            ->json('data.payments.0.expenses');
        $this->assertSame('CAD 200.00', $expenses);
    }

    /** @return array{0: Claim, 1: ClaimExpense, 2: ClaimExpense} */
    private function claimWithReceipts(): array
    {
        $claim = $this->claim();

        $hotel = $claim->expenses()->create([
            'uploaded_by' => $this->customer->id, 'category' => 'hotel', 'description' => 'Airport hotel',
            'amount' => 180, 'currency' => 'CAD', 'expense_date' => now()->subDays(3)->toDateString(),
            'status' => ClaimExpense::STATUS_APPROVED, 'file_path' => 'receipts/hotel.pdf',
            'original_filename' => 'hotel.pdf', 'mime' => 'application/pdf', 'size_bytes' => 1024,
        ]);
        $meal = $claim->expenses()->create([
            'uploaded_by' => $this->customer->id, 'category' => 'meal', 'description' => 'Dinner',
            'amount' => 35, 'currency' => 'CAD', 'expense_date' => now()->subDays(3)->toDateString(),
            'status' => ClaimExpense::STATUS_APPROVED, 'file_path' => 'receipts/meal.pdf',
            'original_filename' => 'meal.pdf', 'mime' => 'application/pdf', 'size_bytes' => 1024,
        ]);
        // A rejected receipt must never be offered for reimbursement.
        $claim->expenses()->create([
            'uploaded_by' => $this->customer->id, 'category' => 'taxi', 'description' => 'Joyride',
            'amount' => 500, 'currency' => 'CAD', 'expense_date' => now()->subDays(3)->toDateString(),
            'status' => ClaimExpense::STATUS_REJECTED, 'file_path' => 'receipts/taxi.pdf',
            'original_filename' => 'taxi.pdf', 'mime' => 'application/pdf', 'size_bytes' => 1024,
        ]);

        return [$claim, $hotel, $meal];
    }

    public function test_approved_receipts_auto_populate_and_are_marked_reimbursed(): void
    {
        [$claim, $hotel, $meal] = $this->claimWithReceipts();

        $component = Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('openRecord', $claim->id);

        // The toggle opens itself and every APPROVED receipt is pre-ticked -
        // the rejected one is not even listed.
        $component->assertSet('form.has_expenses', true);
        $this->assertSame([$hotel->id => true, $meal->id => true], $component->get('expenseChecks'));
        $this->assertSame(215.0, $component->viewData('expensesTotal'));

        $component->set('form.compensation_amount', 400)->call('saveRecord')->assertHasNoErrors();

        // 400 compensation + 215 receipts, fee only on the 400.
        $payment = Payment::first();
        $this->assertSame('615.00', $payment->gross_amount);
        $this->assertSame('215.00', $payment->expenses_amount);
        $this->assertSame('100.00', $payment->fee_amount);
        $this->assertSame('515.00', $payment->net_amount);

        // The receipts this payment settled are now reimbursed.
        $this->assertSame('180.00', $hotel->fresh()->reimbursed_amount);
        $this->assertNotNull($meal->fresh()->reimbursed_at);
    }

    public function test_receipts_in_another_currency_are_neither_counted_nor_settled(): void
    {
        [$claim, $hotel, $meal] = $this->claimWithReceipts();   // both CAD

        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('openRecord', $claim->id)
            ->set('form.compensation_amount', 400)
            ->set('form.currency', 'EUR')       // the airline paid in euros
            ->call('saveRecord')
            ->assertHasNoErrors();

        // The CAD receipts stay out of a EUR payment - amount AND settlement.
        $payment = Payment::first();
        $this->assertSame('400.00', $payment->gross_amount);
        $this->assertSame('0.00', $payment->expenses_amount);
        $this->assertNull($hotel->fresh()->reimbursed_at);
        $this->assertNull($meal->fresh()->reimbursed_at);
    }

    public function test_admin_can_deselect_a_receipt_and_optionally_charge_an_expense_fee(): void
    {
        [$claim, $hotel, $meal] = $this->claimWithReceipts();

        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('openRecord', $claim->id)
            ->set('form.compensation_amount', 400)
            ->set('expenseChecks.' . $meal->id, false)      // airline did not cover the meal
            ->set('form.charge_expense_fee', true)
            ->set('form.expense_fee_percent', 10)
            ->call('saveRecord')
            ->assertHasNoErrors();

        // Only the hotel counts: 400 + 180 = 580 gross.
        $payment = Payment::first();
        $this->assertSame('580.00', $payment->gross_amount);
        $this->assertSame('180.00', $payment->expenses_amount);
        // 25% of 400 + 10% of 180 = 100 + 18.
        $this->assertSame('118.00', $payment->fee_amount);
        $this->assertSame('462.00', $payment->net_amount);

        // The unticked receipt stays unreimbursed, and the ledger explains both fees.
        $this->assertNull($meal->fresh()->reimbursed_at);
        $this->assertNotNull($hotel->fresh()->reimbursed_at);
        $this->assertStringContainsString('10% fee on the', $payment->transactions()->where('type', 'fee_deducted')->first()->notes);
    }

    public function test_illegal_status_jumps_are_refused(): void
    {
        $payment = $this->payment();

        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->transition($payment, Payment::STATUS_PAID, $this->admin);
    }

    public function test_ledger_and_audit_are_immutable(): void
    {
        $payment = $this->payment();

        $this->expectException(\RuntimeException::class);
        $payment->transactions->first()->update(['amount' => 1]);
    }

    public function test_payment_log_cannot_be_deleted(): void
    {
        $payment = $this->payment();

        $this->expectException(\RuntimeException::class);
        $payment->logs->first()->delete();
    }

    // ── Wise payouts ────────────────────────────────────────

    private function fakeWise(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77']);

        Http::fake([
            '*/v3/profiles/77/quotes'        => Http::response(['id' => 'quote-1', 'rate' => 0.68, 'targetAmount' => 510.00], 200),
            '*/v1/accounts'                  => Http::response(['id' => 9001], 200),
            '*/v1/transfers'                 => Http::response(['id' => 555001, 'status' => 'processing'], 200),
            '*/v3/profiles/77/transfers/*'   => Http::response(['type' => 'BALANCE', 'status' => 'COMPLETED'], 200),
            '*/v1/transfers/555001'          => Http::response(['id' => 555001, 'status' => 'outgoing_payment_sent'], 200),
        ]);
    }

    public function test_wise_payout_flow_quote_convert_transfer_and_complete(): void
    {
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        // Draft -> payment ready_for_payout.
        $payout = $wise->draft($payment, 'EUR', $this->admin);
        $this->assertSame(Payment::STATUS_READY_FOR_PAYOUT, $payment->fresh()->status);

        // Send runs the queued job inline (sync queue in tests).
        $wise->send($payout, $this->admin);
        $payout->refresh();

        $this->assertSame(Payout::STATUS_SENT, $payout->status);
        $this->assertSame('555001', $payout->wise_transfer_id);
        $this->assertSame('0.680000', $payout->exchange_rate);
        $this->assertSame('510.00', $payout->amount);

        // The conversion is a ledger row with the historical rate.
        $conversion = $payment->transactions()->where('type', 'conversion')->first();
        $this->assertSame(0.68, (float) $conversion->meta['rate']);
        $this->assertSame('CAD', $conversion->meta['from']);
        $this->assertSame('EUR', $conversion->meta['to']);

        // Webhook completes it: payout completed, payment paid, customer told.
        $this->postJson('/api/webhooks/wise', [
            'data' => ['resource' => ['id' => 555001], 'current_state' => 'outgoing_payment_sent'],
        ])->assertOk();

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->transactions()->where('type', 'completed')->first());
    }

    public function test_late_webhook_for_a_cancelled_payout_cannot_fail_the_payment(): void
    {
        // Cancelling a payout tells Wise to cancel its transfer; Wise then
        // echoes that back as a webhook. That echo must not fail a payment
        // that already has a replacement payout in flight.
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $old = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($old, $this->admin);
        $old->refresh()->forceFill(['status' => Payout::STATUS_CANCELLED, 'transfer_status' => 'cancelled'])->save();
        $payment->refresh()->forceFill(['status' => Payment::STATUS_PROCESSING])->save();

        // Wise's cancellation echo arrives late.
        $wise->applyTransferState($old->fresh(), 'cancelled');

        $this->assertSame(Payout::STATUS_CANCELLED, $old->fresh()->status, 'The cancelled payout must stay cancelled.');
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status, 'The payment must stay with its live payout.');
    }

    public function test_racing_completions_notify_the_customer_exactly_once(): void
    {
        // The webhook and wise:simulate/refresh can both learn the transfer
        // completed at the same moment. Each holds its own model instance
        // that still says "sent" - without an atomic claim both would email
        // the customer and write a second ledger row (seen in sandbox).
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $payout = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);
        $payout->refresh()->forceFill(['status' => Payout::STATUS_SENT, 'wise_transfer_id' => '555001'])->save();
        $payment->refresh();

        $staleA = Payout::find($payout->id);
        $staleB = Payout::find($payout->id);

        $wise->applyTransferState($staleA, 'outgoing_payment_sent');
        $wise->applyTransferState($staleB, 'outgoing_payment_sent');

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(1, $payment->transactions()->where('type', 'completed')->count(),
            'The ledger must record the completion exactly once.');

        $completedMails = collect(Notification::sent($this->customer, PaymentEvent::class))
            ->filter(fn ($n) => $n->toDatabase($this->customer)['title'] === 'Your payout has completed');
        $this->assertCount(1, $completedMails, 'The customer must get exactly one completion email.');
    }

    public function test_racing_failure_signals_alert_exactly_once(): void
    {
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $payout = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);
        $payout->refresh()->forceFill(['status' => Payout::STATUS_SENT, 'wise_transfer_id' => '555001'])->save();

        $wise->applyTransferState(Payout::find($payout->id), 'funds_refunded');
        $wise->applyTransferState(Payout::find($payout->id), 'funds_refunded');

        $this->assertSame(Payout::STATUS_FAILED, $payout->fresh()->status);
        $this->assertSame(1, $payment->transactions()->where('type', 'failed')->count());

        $failureMails = collect(Notification::sent($this->customer, PaymentEvent::class))
            ->filter(fn ($n) => $n->toDatabase($this->customer)['title'] === 'A problem with your payout');
        $this->assertCount(1, $failureMails);
    }

    public function test_forged_webhook_cannot_inject_a_false_state(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77']);
        Http::fake([
            '*/v3/profiles/77/quotes'      => Http::response(['id' => 'quote-1', 'rate' => 1, 'targetAmount' => 750.00], 200),
            '*/v1/accounts'                => Http::response(['id' => 9001], 200),
            '*/v3/profiles/77/transfers/*' => Http::response(['status' => 'COMPLETED'], 200),
            // Wise's API - the source of truth - says the transfer is still processing.
            '*/v1/transfers/555001'        => Http::response(['id' => 555001, 'status' => 'processing'], 200),
            '*/v1/transfers'               => Http::response(['id' => 555001, 'status' => 'processing'], 200),
        ]);

        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'CAD', $this->admin);
        $wise->send($payout, $this->admin);

        // An attacker posts "outgoing_payment_sent" - the endpoint re-checks
        // Wise instead of believing the payload, so nothing completes.
        $this->postJson('/api/webhooks/wise', [
            'data' => ['resource' => ['id' => 555001], 'current_state' => 'outgoing_payment_sent'],
        ])->assertOk();

        $this->assertNotSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
        $this->assertNotSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_job_rerun_resumes_instead_of_creating_a_second_transfer(): void
    {
        // Queue delivery is at-least-once: the same job re-running (worker
        // killed mid-ack, redelivery) must NEVER pay the passenger twice.
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'EUR', $this->admin);

        $wise->send($payout, $this->admin);       // run 1 - creates the transfer
        $wise->executeTransfer($payout->fresh()); // redelivered run 2 - must resume

        $creations = collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/v1/transfers') && $pair[0]->method() === 'POST');

        $this->assertCount(1, $creations, 'A job re-run created a second Wise transfer - double payment.');
        $this->assertSame(2, $payout->fresh()->attempts);
    }

    public function test_conversion_history_is_never_overwritten(): void
    {
        $this->fakeWise();
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $payout = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);

        $original = $payment->transactions()->where('type', 'conversion')->first();

        // A retry RESUMES the existing transfer (rate locked at Wise): the
        // original conversion row is neither duplicated nor overwritten.
        $payout->refresh()->forceFill(['status' => Payout::STATUS_FAILED])->save();
        $payment->refresh()->forceFill(['status' => Payment::STATUS_FAILED])->save();
        $wise->retry($payout->fresh(), $this->admin);

        $this->assertSame(1, $payment->transactions()->where('type', 'conversion')->count());
        $this->assertSame($original->meta, $payment->transactions()->where('type', 'conversion')->first()->meta);

        // A genuinely NEW payout (old one cancelled) re-quotes: a SECOND
        // conversion row appends - history accumulates, never mutates.
        $payout->fresh()->forceFill(['status' => Payout::STATUS_CANCELLED])->save();
        $payment->refresh()->forceFill(['status' => Payment::STATUS_READY_FOR_PAYOUT])->save();
        $second = $wise->draft($payment->fresh(), 'EUR', $this->admin);
        $wise->send($second, $this->admin);

        $this->assertSame(2, $payment->transactions()->where('type', 'conversion')->count());
        $this->assertNotNull($original->fresh()); // first row untouched
    }

    public function test_failed_wise_transfer_marks_everything_and_alerts(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77']);
        Http::fake(['*' => Http::response(['error' => 'insufficient balance'], 500)]);

        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'CAD', $this->admin);

        // Sync queue rethrows the job's exception after running failed().
        try {
            $wise->send($payout, $this->admin);
        } catch (\Throwable) {
            // expected - Wise refused every attempt
        }
        $payout->refresh();

        $this->assertSame(Payout::STATUS_FAILED, $payout->status);
        $this->assertNotNull($payout->error_message);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertTrue($payout->isRetryable());
        Notification::assertSentTo($this->customer, PaymentEvent::class);
    }

    public function test_sending_requires_the_payouts_send_permission(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77']);
        $payment = $this->payment();
        $payout  = app(WisePayoutService::class)->draft($payment, 'CAD', $this->admin);

        Role::findOrCreate('admin')->revokePermissionTo('payouts.send');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Queue::fake();
        try {
            app(WisePayoutService::class)->send($payout, $this->admin->fresh());
            $this->fail('Sending without payouts.send must be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        Queue::assertNotPushed(ProcessWisePayout::class);
    }

    public function test_manual_payout_settles_the_payment_with_conversion_history(): void
    {
        $payment = $this->payment();

        app(WisePayoutService::class)->recordManual($payment, [
            'amount' => 552.30, 'currency' => 'USD', 'exchange_rate' => 0.7364, 'reference' => 'ETRANSFER-9',
        ], $this->admin);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $conversion = $payment->transactions()->where('type', 'conversion')->first();
        $this->assertSame(0.7364, (float) $conversion->meta['rate']);
    }

    // ── UI, security, export ────────────────────────────────

    public function test_admin_records_a_payment_through_the_ui(): void
    {
        $claim = $this->claim();

        // The admin enters compensation and expenses separately - the gross
        // is their sum, the fee bites only the compensation.
        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('openRecord', $claim->id)
            ->set('form.compensation_amount', 800)
            ->set('form.currency', 'CAD')
            ->set('form.has_expenses', true)
            ->set('form.extra_expenses', 150)
            ->call('saveRecord')
            ->assertHasNoErrors();

        $payment = Payment::first();
        $this->assertSame('950.00', $payment->gross_amount);
        $this->assertSame('150.00', $payment->expenses_amount);
        $this->assertSame('200.00', $payment->fee_amount);
        $this->assertSame('750.00', $payment->net_amount);
        $this->assertSame($this->admin->id, $payment->created_by);
    }

    public function test_one_click_send_drafts_and_queues_the_payout_in_a_single_action(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77', 'services.wise.sandbox' => false]);
        Queue::fake();

        $payment = $this->payment();

        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('open', $payment->id)
            ->call('sendPayoutNow')
            ->assertHasNoErrors();

        // Exactly one payout, already past draft and queued for transfer.
        $payout = $payment->payouts()->sole();
        $this->assertSame(Payout::STATUS_PROCESSING, $payout->status);
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        Queue::assertPushed(ProcessWisePayout::class, 1);
    }

    public function test_dashboard_totals_convert_to_one_base_currency(): void
    {
        config(['services.wise.token' => 'wise-test', 'services.wise.profile_id' => '77', 'services.wise.sandbox' => false,
                'services.wise.dashboard_currency' => 'CAD']);
        Http::fake(['*/v1/rates*' => Http::response([['rate' => 1.5]], 200)]);

        $this->payment();                                          // CAD 1000
        $this->payment(['gross_amount' => 200, 'currency' => 'EUR']);

        $stats = Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->viewData('stats');

        // CAD 1000 + EUR 200 * 1.5 = ~CAD 1300, with the true figures below.
        $this->assertSame('≈ CAD 1,300.00', $stats['collected']['headline']);
        $this->assertSame('CAD 1,000.00 + EUR 200.00', $stats['collected']['breakdown']);

        // The popup's per-currency rows carry count, rate and converted value.
        $eur = $stats['collected']['details']->firstWhere('currency', 'EUR');
        $this->assertSame(1, $eur['count']);
        $this->assertSame(1.5, $eur['rate']);
        $this->assertSame(300.0, $eur['converted']);
        $this->assertSame(1300.0, $stats['collected']['total']);

        // Single-currency totals are exact - no approximation marker.
        Cache::flush();
        Payment::where('currency', 'EUR')->delete();
        $stats = Livewire::actingAs($this->admin)->test(AdminPayments::class)->viewData('stats');
        $this->assertSame('CAD 1,000.00', $stats['collected']['headline']);
        $this->assertNull($stats['collected']['breakdown']);
    }

    public function test_dashboard_totals_fall_back_to_the_breakdown_without_rates(): void
    {
        config(['services.wise.token' => null]);

        $this->payment();
        $this->payment(['gross_amount' => 200, 'currency' => 'EUR']);

        $stats = Livewire::actingAs($this->admin)->test(AdminPayments::class)->viewData('stats');

        $this->assertSame('CAD 1,000.00 + EUR 200.00', $stats['collected']['headline']);
        $this->assertNull($stats['collected']['breakdown']);
    }

    public function test_pdf_receipt_downloads_and_is_permission_gated(): void
    {
        $payment = $this->payment();

        $response = $this->actingAs($this->admin)->get(route('admin.flight-claims.payments.receipt', $payment));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('RCPT-' . $payment->claim->number, (string) $response->headers->get('content-disposition'));

        // Without payments.view the receipt is refused.
        Role::findOrCreate('admin')->revokePermissionTo('payments.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->admin)->get(route('admin.flight-claims.payments.receipt', $payment))->assertStatus(403);
    }

    public function test_switching_tabs_resets_pagination(): void
    {
        $payment = $this->payment();

        // Deep in the transactions pages, then back to payments - the list
        // must show page 1, not a page that only existed on the other tab.
        Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->set('tab', 'transactions')
            ->call('setPage', 5)
            ->set('tab', 'payments')
            ->assertSee('#' . $payment->claim->number)
            ->assertDontSee('No payments yet');
    }

    public function test_the_payments_page_is_permission_gated(): void
    {
        $this->customer->assignRole('user');

        // Non-admins are turned away by the role middleware (redirect).
        $this->actingAs($this->customer)->get(route('admin.flight-claims.payments'))->assertStatus(302);

        // An admin stripped of payments.view is refused by the module itself.
        $junior = User::factory()->create();
        $junior->assignRole('admin');
        $junior->revokePermissionTo('payments.view');
        Role::findOrCreate('admin')->revokePermissionTo('payments.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($junior)->get(route('admin.flight-claims.payments'))->assertForbidden();
        Role::findOrCreate('admin')->givePermissionTo('payments.view');
    }

    public function test_csv_export_streams_the_filtered_ledger(): void
    {
        $this->payment();

        $response = Livewire::actingAs($this->admin)->test(AdminPayments::class)
            ->call('exportCsv');

        $response->assertFileDownloaded();
    }

    public function test_customer_sees_every_payout_but_never_internal_notes(): void
    {
        $payment = $this->payment(['notes' => 'INTERNAL: negotiated settlement, do not disclose']);

        // A second instalment on the SAME claim - both must reach the customer.
        app(PaymentService::class)->record($payment->claim, [
            'gross_amount' => 200, 'currency' => 'EUR',
            'payment_date' => now()->addDay()->toDateString(), 'reference' => 'AC-REMIT-2',
        ], $this->admin);

        $payload = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($payment->claim_id)))
            ->assertOk()
            ->json('data.payments');

        $this->assertCount(2, $payload);

        // Newest first: the EUR instalment leads, the original follows.
        $this->assertSame('EUR 200.00', $payload[0]['gross']);
        $this->assertSame('CAD 1,000.00', $payload[1]['gross']);
        $this->assertSame('CAD 250.00', $payload[1]['fee']);
        $this->assertSame('CAD 750.00', $payload[1]['net']);
        $this->assertSame('Payment received', $payload[1]['status_label']);

        $raw = json_encode($payload);
        $this->assertStringNotContainsString('INTERNAL', $raw);
        $this->assertStringNotContainsString('do not disclose', $raw);
    }

    public function test_customer_downloads_their_own_receipt_but_not_anothers(): void
    {
        $payment = $this->payment();

        $response = $this->actingAs($this->customer)->get(route('user.itineraries.payments.receipt', $payment));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $intruder = User::factory()->create();
        $intruder->assignRole('user');
        $this->actingAs($intruder)->get(route('user.itineraries.payments.receipt', $payment))->assertStatus(403);
    }

    public function test_customer_timeline_hides_internal_retry_churn(): void
    {
        $payment  = $this->payment();
        $payments = app(PaymentService::class);

        // A retry storm: two dead attempts before the transfer that stuck.
        $payments->ledger($payment, 'wise_transfer', 100, 'EUR');
        $payments->ledger($payment, 'failed', null, 'EUR', null, null, 'attempt 1 died');
        $payments->ledger($payment, 'wise_transfer', 100, 'EUR');
        $payments->ledger($payment, 'cancelled', null, 'EUR');
        $payments->ledger($payment, 'wise_transfer', 101, 'EUR');
        $payments->ledger($payment, 'completed', 101, 'EUR');

        $timeline = collect($this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($payment->claim_id)))
            ->json('data.payments.0.timeline'));

        $labels = $timeline->pluck('label');
        $this->assertFalse($labels->contains('Failed'));
        $this->assertFalse($labels->contains('Cancelled'));

        // Retries collapse to the attempt that counted (the EUR 101 one).
        $this->assertSame(1, $labels->filter(fn ($l) => $l === 'Wise transfer')->count());
        $this->assertSame('EUR 101.00', $timeline->firstWhere('label', 'Wise transfer')['amount']);
    }

    public function test_another_customer_cannot_see_the_payment(): void
    {
        $payment  = $this->payment();
        $intruder = User::factory()->create();
        $intruder->assignRole('user');

        $this->actingAs($intruder)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($payment->claim_id)))
            ->assertForbidden();
    }

    public function test_notification_endpoints_serve_and_mark_read(): void
    {
        // Store a database notification directly - the channel fake in setUp
        // intercepts notify(), and this test is about the endpoints.
        $this->customer->notifications()->create([
            'id'   => (string) \Illuminate\Support\Str::uuid(),
            'type' => PaymentEvent::class,
            'data' => ['title' => 'Your payout has completed', 'description' => 'CAD 750.00 paid out.', 'claim_url' => '/x'],
        ]);

        $data = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.notifications'))
            ->assertOk()
            ->json('data');

        $this->assertGreaterThan(0, $data['unread']);
        $this->assertNotEmpty($data['notifications'][0]['title']);

        $this->actingAs($this->customer)
            ->postJson(route('user.itineraries.api.notifications.read'))
            ->assertOk();

        $this->assertSame(0, $this->customer->fresh()->unreadNotifications()->count());
    }
}
