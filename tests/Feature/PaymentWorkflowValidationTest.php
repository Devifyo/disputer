<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\ClaimDraft;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PaymentEvent;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WisePayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Payment workflow validation: the arithmetic, the state machine and the
 * money-safety rules the main PaymentModuleTest does not pin down - the fee
 * across many amounts and all four currencies, multi-passenger totals, the
 * full valid status path and its dead ends, retry after failure never
 * charging twice, refunds not counting as payouts, duplicate and concurrent
 * payout protection, and the AI's total inability to touch a figure.
 *
 * ALL Wise traffic is faked. No live or sandbox transfers.
 */
class PaymentWorkflowValidationTest extends TestCase
{
    use RefreshDatabase;

    private const WISE_TOKEN = 'wise-secret-token';

    private User $admin;
    private User $customer;

    /** Mutable Wise behaviour - the fake is registered once in setUp. */
    private ?int $failStatus   = null;
    private float $quoteRate   = 0.68;
    private string $transferId = '555001';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('admin')->givePermissionTo([
            'payments.view', 'payments.manage', 'payments.override_fee', 'payouts.send',
        ]);
        Role::findOrCreate('user');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create();
        $this->customer->assignRole('user');

        config(['services.wise.token' => self::WISE_TOKEN, 'services.wise.profile_id' => '77']);
        $this->fakeWise();
    }

    // ── 3 + 4. The fee rule across many amounts ─────────────

    public function test_the_success_fee_is_a_quarter_of_every_gross_amount(): void
    {
        // 25% of gross, net is the remaining 75% - the configured rule.
        $expected = [
            '100'  => ['25.00', '75.00'],
            '400'  => ['100.00', '300.00'],
            '600'  => ['150.00', '450.00'],
            '1000' => ['250.00', '750.00'],
            '5000' => ['1250.00', '3750.00'],
        ];

        foreach ($expected as $gross => [$fee, $net]) {
            $payment = $this->payment(['gross_amount' => (float) $gross]);

            $this->assertSame($fee, $payment->fee_amount, "Fee on {$gross}");
            $this->assertSame($net, $payment->net_amount, "Net on {$gross}");
            // Gross, fee and net are stored separately and reconcile exactly.
            $this->assertSame(
                round((float) $gross, 2),
                round((float) $payment->fee_amount + (float) $payment->net_amount, 2),
                "Fee + net must reconstitute gross for {$gross}"
            );
            $this->assertNotSame($payment->gross_amount, $payment->net_amount,
                'The payout must never equal the gross compensation');
        }
    }

    public function test_amounts_that_do_not_divide_cleanly_still_reconcile_to_the_cent(): void
    {
        foreach ([333.33, 0.01, 99.99, 1234.56] as $gross) {
            $payment = $this->payment(['gross_amount' => $gross]);

            $this->assertSame(
                round($gross, 2),
                round((float) $payment->fee_amount + (float) $payment->net_amount, 2),
                "Rounding must not lose or invent money on {$gross}"
            );
        }
    }

    // ── 6. Every supported currency ─────────────────────────

    public function test_the_fee_split_holds_in_every_supported_currency(): void
    {
        foreach (['CAD', 'USD', 'EUR', 'GBP'] as $currency) {
            $payment = $this->payment(['gross_amount' => 600, 'currency' => $currency]);

            $this->assertSame($currency, $payment->currency);
            $this->assertSame('150.00', $payment->fee_amount);
            $this->assertSame('450.00', $payment->net_amount);

            // The ledger records the money in the currency it arrived in.
            $received = $payment->transactions()->where('type', 'payment_received')->sole();
            $this->assertSame($currency, $received->currency);
            $this->assertSame('600.00', $received->amount);
        }
    }

    // ── 5. Multi-passenger booking totals ───────────────────

    public function test_a_five_passenger_booking_is_paid_and_split_as_one_master_payment(): void
    {
        $claim = $this->multiPassengerClaim(); // 5 x EUR 600

        // The airline remits the booking total; the fee applies to the whole.
        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 3000, 'currency' => 'EUR',
            'payment_date' => now()->toDateString(), 'reference' => 'LH-REMIT-1',
        ], $this->admin);

        $this->assertSame('3000.00', $payment->gross_amount);
        $this->assertSame('750.00', $payment->fee_amount);
        $this->assertSame('2250.00', $payment->net_amount);

        // One payment for the booking, owned by the one account holder.
        $this->assertSame(1, $claim->payments()->count());
        $this->assertSame($claim->id, $payment->claim_id);
        $this->assertSame($claim->user_id, $payment->user_id);
        $this->assertSame(5, count($claim->passengerNames()));

        // Per-passenger compensation is unchanged by the payment record -
        // the engine's figure remains the source of truth.
        $this->assertSame('600.00', $claim->fresh()->compensation_amount);
    }

    // ── 12. The status machine, forwards and backwards ──────

    public function test_the_payment_walks_its_configured_path_and_refuses_the_rest(): void
    {
        $payments = app(PaymentService::class);
        $payment  = $this->payment();

        $this->assertSame(Payment::STATUS_RECEIVED, $payment->status);

        foreach ([Payment::STATUS_READY_FOR_PAYOUT, Payment::STATUS_PROCESSING, Payment::STATUS_PAID] as $next) {
            $payment = $payments->transition($payment, $next, $this->admin);
            $this->assertSame($next, $payment->status);
        }

        // Paid is terminal except for a refund.
        foreach ([Payment::STATUS_RECEIVED, Payment::STATUS_PROCESSING, Payment::STATUS_CANCELLED] as $illegal) {
            try {
                $payments->transition($payment, $illegal, $this->admin);
                $this->fail("paid -> {$illegal} must be refused");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('cannot move', $e->getMessage());
            }
        }

        // Every hop is audited with both states.
        // The relation is newest-first, so first() is the latest hop.
        $log = $payment->logs()->where('action', 'status_changed')->get();
        $this->assertGreaterThanOrEqual(3, $log->count());
        $this->assertSame(Payment::STATUS_PROCESSING, $log->first()->old_values['status'] ?? null);
        $this->assertSame(Payment::STATUS_PAID, $log->first()->new_values['status'] ?? null);
    }

    // ── 22. Refunds are not payouts ─────────────────────────

    public function test_a_refund_is_recorded_as_a_refund_and_never_as_a_completed_payout(): void
    {
        $payments = app(PaymentService::class);
        $payment  = $this->payment();

        $refunded = $payments->refund($payment, $this->admin, 'Airline reversed the remittance');

        $this->assertSame(Payment::STATUS_REFUNDED, $refunded->status);
        $this->assertSame(0, $refunded->payouts()->where('status', Payout::STATUS_COMPLETED)->count());

        // The ledger names it a refund, for the full gross, with the reason.
        $row = $refunded->transactions()->where('type', 'refund')->sole();
        $this->assertSame('1000.00', $row->amount);
        $this->assertSame('CAD', $row->currency);
        $this->assertStringContainsString('reversed', (string) $row->notes);

        // The customer is told, and refunded is terminal.
        $this->assertGreaterThanOrEqual(1, Notification::sent($this->customer, PaymentEvent::class)->count());
        try {
            $payments->transition($refunded, Payment::STATUS_PAID, $this->admin);
            $this->fail('A refunded payment must not become paid');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot move', $e->getMessage());
        }
    }

    // ── 20 + 21. Failure then retry, without charging twice ──

    public function test_retrying_a_failed_payout_never_charges_the_fee_twice(): void
    {
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $payout = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);
        $this->assertSame(Payout::STATUS_SENT, $payout->fresh()->status);

        // Wise reports the transfer bounced.
        $wise->markFailed($payout->fresh(), 'Recipient account closed');

        $payout->refresh();
        $payment->refresh();
        $this->assertSame(Payout::STATUS_FAILED, $payout->status);
        $this->assertStringContainsString('Recipient account closed', (string) $payout->error_message);

        // The money is intact: fee and net untouched, one fee row only.
        $this->assertSame('250.00', $payment->fee_amount);
        $this->assertSame('750.00', $payment->net_amount);
        $this->assertSame(1, $payment->transactions()->where('type', 'fee_deducted')->count());

        // The admin retries; the fee is still charged exactly once.
        $wise->retry($payout->fresh(), $this->admin);

        $payment->refresh();
        $this->assertSame('250.00', $payment->fee_amount);
        $this->assertSame('750.00', $payment->net_amount);
        $this->assertSame(1, $payment->transactions()->where('type', 'fee_deducted')->count(),
            'A retry must never deduct the success fee again');
        $this->assertSame(1, $payment->payouts()->count(), 'Retry resumes the payout, it does not clone it');
    }

    // ── 24 + 25. Duplicate and concurrent payouts ───────────

    public function test_a_second_payout_cannot_be_opened_while_one_is_in_flight(): void
    {
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        $first = $wise->draft($payment, 'EUR', $this->admin);

        // A second admin pressing the same button is refused outright.
        foreach ([Payout::STATUS_DRAFT, Payout::STATUS_SENT, Payout::STATUS_PROCESSING] as $inFlight) {
            $first->forceFill(['status' => $inFlight])->save();

            try {
                $wise->draft($payment->fresh(), 'EUR', $this->admin);
                $this->fail("A second payout must be refused while one is {$inFlight}");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }

        $this->assertSame(1, $payment->fresh()->payouts()->count());

        // A funded/in-flight transfer is deliberately NOT cancellable.
        try {
            $wise->cancel($first->fresh(), $this->admin, 'Too late');
            $this->fail('A processing transfer must not be cancellable');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        // Back at draft it can be cancelled, and a replacement is allowed.
        $first->forceFill(['status' => Payout::STATUS_DRAFT])->save();
        $wise->cancel($first->fresh(), $this->admin, 'Wrong currency');
        $replacement = $wise->draft($payment->fresh(), 'EUR', $this->admin);

        $this->assertSame(Payout::STATUS_CANCELLED, $first->fresh()->status);
        $this->assertSame(2, $payment->fresh()->payouts()->count());
        $this->assertNotSame($first->id, $replacement->id);
        // A cancelled payout is not a completed one.
        $this->assertSame(0, $payment->fresh()->payouts()->where('status', Payout::STATUS_COMPLETED)->count());
    }

    public function test_a_payout_cannot_be_drafted_before_the_money_arrives(): void
    {
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();

        // Terminal states have no payout to make.
        foreach ([Payment::STATUS_CANCELLED, Payment::STATUS_REFUNDED, Payment::STATUS_PAID] as $status) {
            $payment->forceFill(['status' => $status])->save();

            try {
                $wise->draft($payment->fresh(), 'EUR', $this->admin);
                $this->fail("A {$status} payment must not open a payout");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }

        $this->assertSame(0, $payment->fresh()->payouts()->count());
    }

    // ── 14. The AI can never move a figure ──────────────────

    public function test_editing_an_ai_draft_cannot_change_a_single_payment_figure(): void
    {
        $claim   = $this->claim();
        $payment = app(PaymentService::class)->record($claim, [
            'gross_amount' => 1000, 'currency' => 'CAD',
            'payment_date' => now()->toDateString(), 'reference' => 'AC-REMIT-1',
        ], $this->admin);

        $before = $payment->only(['gross_amount', 'fee_amount', 'net_amount', 'currency', 'status']);

        // A draft letter claiming a wildly different figure is just text.
        $claim->drafts()->create([
            'type'         => ClaimDraft::TYPE_CLAIM,
            'version'      => 1,
            'subject'      => 'Compensation claim',
            'body'         => 'We demand CAD 9,999,999.00 immediately.',
            'generated_by' => 'ai',
            'created_by'   => $this->admin->id,
        ]);

        $this->assertSame($before, $payment->fresh()->only(array_keys($before)),
            'Drafting must never touch the money');
        $this->assertSame('400.00', $claim->fresh()->compensation_amount,
            "The engine's compensation figure is untouched by drafting");
    }

    // ── 26. Provider failures leave the books clean ─────────

    public function test_every_wise_failure_leaves_the_payment_whole(): void
    {
        foreach ([400, 401, 403, 429, 500] as $status) {
            $wise    = app(WisePayoutService::class);
            $payment = $this->payment(['reference' => "AC-REMIT-{$status}"]);
            $payout  = $wise->draft($payment, 'EUR', $this->admin);

            $this->failStatus = $status;

            try {
                $wise->send($payout->fresh(), $this->admin);
            } catch (\Throwable $e) {
                // Surfacing the failure is fine; corrupting state is not.
            }

            $this->failStatus = null;
            $payment->refresh();
            $payout->refresh();

            // The money is untouched and no transfer was ever recorded.
            $this->assertSame('250.00', $payment->fee_amount, "HTTP {$status}: fee intact");
            $this->assertSame('750.00', $payment->net_amount, "HTTP {$status}: net intact");
            $this->assertNotSame(Payout::STATUS_COMPLETED, $payout->status, "HTTP {$status}: never completed");
            $this->assertNotSame(Payment::STATUS_PAID, $payment->status, "HTTP {$status}: payment not paid");
            $this->assertSame(1, $payment->transactions()->where('type', 'fee_deducted')->count());
        }
    }

    // ── 9 + 11. The webhook is a ping, never a source of truth ──

    public function test_a_replayed_wise_webhook_completes_the_payout_exactly_once(): void
    {
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);

        $body = ['data' => ['resource' => ['id' => (int) $this->transferId], 'current_state' => 'outgoing_payment_sent']];

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/webhooks/wise', $body)->assertOk();
        }

        $payout->refresh();
        $payment->refresh();

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);

        // One completion row, and exactly one "payout completed" notification
        // however many times Wise pings us.
        $this->assertSame(1, $payment->transactions()->where('type', 'completed')->count());

        // "payout-initiated" (sent) and "payout-completed" (settled) are
        // distinct events - only the completion must be one-per-payout.
        $completions = Notification::sent($this->customer, PaymentEvent::class)
            ->filter(fn ($n) => str_contains($n->toDatabase($this->customer)['title'] ?? '', 'has completed'));

        $this->assertCount(1, $completions, 'Replays must never re-notify the customer');

        // An event for a transfer nobody owns is acknowledged and ignored.
        $this->postJson('/api/webhooks/wise', [
            'data' => ['resource' => ['id' => 987654], 'current_state' => 'outgoing_payment_sent'],
        ])->assertOk();
        $this->assertSame(1, Payout::where('status', Payout::STATUS_COMPLETED)->count());
    }

    public function test_a_webhook_with_a_bad_signature_is_rejected_when_a_key_is_configured(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();
        config(['services.wise.webhook_public_key' => $publicKey]);

        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);

        $body = json_encode(['data' => ['resource' => ['id' => (int) $this->transferId], 'current_state' => 'outgoing_payment_sent']]);

        // No signature at all.
        $this->call('POST', '/api/webhooks/wise', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(400);

        // Garbage signature.
        $this->call('POST', '/api/webhooks/wise', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_SHA256' => base64_encode('nonsense'),
        ], $body)->assertStatus(400);

        // A signature over a DIFFERENT payload (tampering).
        openssl_sign('{"data":{"resource":{"id":1}}}', $wrong, $privateKey, OPENSSL_ALGO_SHA256);
        $this->call('POST', '/api/webhooks/wise', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_SHA256' => base64_encode($wrong),
        ], $body)->assertStatus(400);

        $this->assertNotSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);

        // Correctly signed: accepted.
        openssl_sign($body, $good, $privateKey, OPENSSL_ALGO_SHA256);
        $this->call('POST', '/api/webhooks/wise', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE_SHA256' => base64_encode($good),
        ], $body)->assertOk();

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
    }

    // ── 23. Credentials never surface ───────────────────────

    public function test_the_wise_token_never_reaches_a_response_or_a_ledger_row(): void
    {
        $wise    = app(WisePayoutService::class);
        $payment = $this->payment();
        $payout  = $wise->draft($payment, 'EUR', $this->admin);
        $wise->send($payout, $this->admin);

        // Nothing the customer can read carries the credential.
        $response = $this->actingAs($this->customer)
            ->getJson(route('user.itineraries.api.claims.show', encrypt_id($payment->claim_id)));

        $this->assertStringNotContainsString(self::WISE_TOKEN, $response->getContent());

        foreach ($payment->fresh()->transactions as $row) {
            $this->assertStringNotContainsString(self::WISE_TOKEN, json_encode($row->toArray()));
        }
        foreach ($payment->fresh()->logs as $log) {
            $this->assertStringNotContainsString(self::WISE_TOKEN, json_encode($log->toArray()));
        }
    }

    // ── Fixtures ────────────────────────────────────────────

    private function fakeWise(): void
    {
        Http::fake(function (ClientRequest $request) {
            $url = $request->url();

            if (!str_contains($url, 'wise')) {
                return Http::response([], 200);
            }

            if ($this->failStatus !== null) {
                return Http::response(['errors' => [['message' => 'nope']]], $this->failStatus);
            }

            return match (true) {
                str_contains($url, '/quotes')    => Http::response(['id' => 'quote-1', 'rate' => $this->quoteRate, 'targetAmount' => 510.00], 200),
                str_contains($url, '/accounts')  => Http::response(['id' => 9001], 200),
                str_contains($url, "/transfers/{$this->transferId}") => Http::response(['id' => (int) $this->transferId, 'status' => 'outgoing_payment_sent'], 200),
                str_contains($url, '/transfers') => Http::response(['id' => (int) $this->transferId, 'status' => 'processing'], 200),
                default                          => Http::response(['type' => 'BALANCE', 'status' => 'COMPLETED'], 200),
            };
        });
    }

    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} */
    private function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        return [$key, openssl_pkey_get_details($key)['key']];
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

    private function multiPassengerClaim(): Claim
    {
        $itinerary = Itinerary::create([
            'user_id'           => $this->customer->id,
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
            'user_id' => $this->customer->id, 'itinerary_id' => $itinerary->id,
            'status' => Claim::STATUS_ELIGIBLE, 'workflow_state' => 'responded',
            'airline' => 'Lufthansa', 'flight_number' => 'LH123',
            'departure_airport' => 'FRA', 'arrival_airport' => 'JFK', 'flight_date' => '2026-07-10',
            'passenger_name' => 'Alpha Lead',
            'compensation_amount' => '600.00', 'compensation_currency' => 'EUR',
        ]);
    }

    private function payment(array $overrides = []): Payment
    {
        return app(PaymentService::class)->record($this->claim(), array_merge([
            'gross_amount' => 1000, 'currency' => 'CAD',
            'payment_date' => now()->toDateString(), 'reference' => 'AC-REMIT-1',
        ], $overrides), $this->admin);
    }
}
