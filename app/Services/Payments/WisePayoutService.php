<?php

namespace App\Services\Payments;

use App\Jobs\ProcessWisePayout;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Notifications\PaymentEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wise (TransferWise) payouts. Credentials live only in config/env - never
 * in code or the database. All transfer work happens in the queued
 * ProcessWisePayout job (with retries); admins only ever draft, send,
 * retry or cancel. Every API call is logged; every money movement lands in
 * the ledger with its exchange rate, and conversions are append-only.
 *
 * Recipients are Wise "email" recipients: Wise contacts the passenger for
 * their bank details, so Unjamm never stores account numbers.
 */
class WisePayoutService
{
    public function configured(): bool
    {
        return (bool) (config('services.wise.token') && $this->profileId());
    }

    public function sandbox(): bool
    {
        return (bool) config('services.wise.sandbox');
    }

    /**
     * The Wise profile to pay from. Configured explicitly, or resolved once
     * from the API (business profile preferred) so a fresh sandbox only
     * needs its token pasted in.
     */
    public function profileId(): ?string
    {
        if ($id = config('services.wise.profile_id')) {
            return (string) $id;
        }

        if (!config('services.wise.token')) {
            return null;
        }

        $cacheKey = 'wise.profile.' . substr(sha1((string) config('services.wise.token')), 0, 12);

        return Cache::rememberForever($cacheKey, function () {
            try {
                $profiles = $this->call('get', '/v1/profiles');
            } catch (\Throwable) {
                return null;
            }

            $business = collect($profiles)->firstWhere('type', 'business');

            return (string) (($business ?? collect($profiles)->first())['id'] ?? '') ?: null;
        });
    }

    /**
     * Mid-market rate between two currencies, cached for 6 hours - used for
     * the dashboard's approximate base-currency totals only, never to move
     * money (transfers always price from their own live quote).
     */
    public function rate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        if (!config('services.wise.token')) {
            return null;
        }

        return Cache::remember("wise.rate.{$from}.{$to}", now()->addHours(6), function () use ($from, $to) {
            try {
                $rates = $this->call('get', '/v1/rates', ['source' => $from, 'target' => $to]);

                // Only trust the entry for OUR pair - Wise returns its full
                // rate list when it does not recognise the filter.
                $match = collect($rates)->first(fn ($r) => ($r['source'] ?? $from) === $from && ($r['target'] ?? $to) === $to);

                return (float) ($match['rate'] ?? 0) ?: null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    private function http(): PendingRequest
    {
        return Http::withToken(config('services.wise.token'))
            ->baseUrl(rtrim(config('services.wise.base_url', 'https://api.sandbox.transferwise.tech'), '/'))
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Draft a payout for review - nothing leaves until an admin sends it.
     * The destination is the CUSTOMER's choice: their default saved account
     * is used automatically. Admins never pick an account; when the customer
     * has none, the payout falls back to a Wise email request (or the admin
     * asks the customer to add details first).
     */
    public function draft(Payment $payment, ?string $targetCurrency, User $admin, bool $viaWiseEmail = false): Payout
    {
        abort_unless(in_array($payment->status, [Payment::STATUS_RECEIVED, Payment::STATUS_READY_FOR_PAYOUT], true),
            422, 'Only received payments can be prepared for payout.');
        abort_if($payment->payouts()->whereIn('status', [Payout::STATUS_DRAFT, Payout::STATUS_PROCESSING, Payout::STATUS_SENT])->exists(),
            422, 'A payout is already in flight for this payment.');

        $account = $viaWiseEmail ? null : UserPayoutAccount::defaultFor($payment->user);

        if ($account) {
            $targetCurrency = $account->currency; // the account dictates the currency
        }

        $targetCurrency = $targetCurrency ?: $payment->currency;

        $payments = app(PaymentService::class);

        return DB::transaction(function () use ($payment, $targetCurrency, $admin, $payments, $account) {
            $payout = $payment->payouts()->create([
                'user_id'            => $payment->user_id,
                'method'             => Payout::METHOD_WISE,
                'status'             => Payout::STATUS_DRAFT,
                'currency'           => strtoupper($targetCurrency),
                'amount'             => $payment->net_amount,   // refined by the live quote at send time
                'source_currency'    => $payment->currency,
                'source_amount'      => $payment->net_amount,
                'recipient_name'     => $payment->claim?->passenger_name ?: $payment->user?->name,
                'transfer_reference' => 'UNJAMM-' . ($payment->claim?->number ?? $payment->id),
                'customer_transaction_id' => (string) Str::uuid(),
                'user_payout_account_id'  => $account?->id,
                'recipient_iban'          => $account?->masked,
                'created_by'         => $admin->id,
            ]);

            if ($payment->status === Payment::STATUS_RECEIVED) {
                $payments->transition($payment, Payment::STATUS_READY_FOR_PAYOUT, $admin, 'Payout drafted.');
            }

            $payments->ledger($payment, 'payout_created', (float) $payment->net_amount, $payment->currency, $admin->id,
                $payout->transfer_reference, 'Wise payout drafted for review', [], $payout->id);
            $payments->audit($payment, 'payout_created', $admin, null, ['payout_id' => $payout->id, 'target_currency' => $payout->currency]);

            return $payout;
        });
    }

    /** Record a payout made outside Wise (bank transfer, Interac...). */
    public function recordManual(Payment $payment, array $data, User $admin): Payout
    {
        $payments = app(PaymentService::class);

        $payout = DB::transaction(function () use ($payment, $data, $admin, $payments) {
            $payout = $payment->payouts()->create([
                'user_id'            => $payment->user_id,
                'method'             => Payout::METHOD_MANUAL,
                'status'             => Payout::STATUS_COMPLETED,
                'currency'           => strtoupper($data['currency']),
                'amount'             => round((float) $data['amount'], 2),
                'source_currency'    => $payment->currency,
                'source_amount'      => $payment->net_amount,
                'exchange_rate'      => $data['exchange_rate'] ?? null,
                'converted_at'       => now(),
                'transfer_reference' => $data['reference'] ?? ('MANUAL-' . ($payment->claim?->number ?? $payment->id)),
                'transferred_at'     => now(),
                'created_by'         => $admin->id,
            ]);

            if ($payment->status === Payment::STATUS_RECEIVED) {
                $payments->transition($payment, Payment::STATUS_READY_FOR_PAYOUT, $admin, 'Manual payout.');
            }
            $payments->transition($payment, Payment::STATUS_PROCESSING, $admin, 'Manual payout recorded.');
            $payments->transition($payment, Payment::STATUS_PAID, $admin, 'Manual payout completed.');

            if (strtoupper($data['currency']) !== $payment->currency && !empty($data['exchange_rate'])) {
                $payments->ledger($payment, 'conversion', round((float) $data['amount'], 2), strtoupper($data['currency']), $admin->id, null, null, [
                    'from' => $payment->currency, 'to' => strtoupper($data['currency']),
                    'rate' => (float) $data['exchange_rate'], 'converted_at' => now()->toIso8601String(),
                ], $payout->id);
            }

            $payments->ledger($payment, 'completed', round((float) $data['amount'], 2), strtoupper($data['currency']), $admin->id,
                $payout->transfer_reference, 'Paid manually outside Wise', [], $payout->id);
            $payments->audit($payment, 'payout_completed', $admin, null, ['payout_id' => $payout->id, 'method' => 'manual']);

            return $payout;
        });

        $payment->claim?->recordEvent(sprintf('Your payout of %s %s has been sent', $payout->currency, number_format((float) $payout->amount, 2)), 'done', now(), 2);
        $payment->user?->notify(new PaymentEvent(
            $payment, 'Your payout has been sent',
            sprintf('%s %s was paid out. Reference: %s.', $payout->currency, number_format((float) $payout->amount, 2), $payout->transfer_reference),
            'payout-completed', ['reference' => $payout->transfer_reference],
        ));

        return $payout;
    }

    /** Queue the actual Wise transfer - the admin's "Send payout" button. */
    public function send(Payout $payout, User $admin): void
    {
        abort_unless($this->configured(), 422, 'Wise is not configured yet (WISE_API_TOKEN / WISE_PROFILE_ID).');
        abort_unless(in_array($payout->status, [Payout::STATUS_DRAFT, Payout::STATUS_FAILED], true), 422, 'This payout is not in a sendable state.');
        abort_unless($admin->can('payouts.send'), 403, 'You do not have permission to send payouts.');

        $payments = app(PaymentService::class);

        $payout->forceFill(['status' => Payout::STATUS_PROCESSING, 'error_message' => null])->save();
        $payments->transition($payout->payment, Payment::STATUS_PROCESSING, $admin, 'Wise payout queued.');
        $payments->audit($payout->payment, 'wise_transfer_queued', $admin, null, ['payout_id' => $payout->id]);

        ProcessWisePayout::dispatch($payout->id);
    }

    public function retry(Payout $payout, User $admin): void
    {
        abort_unless($payout->isRetryable(), 422, 'Only failed payouts can be retried.');
        $this->send($payout, $admin);
    }

    public function cancel(Payout $payout, User $admin, ?string $reason = null): void
    {
        abort_unless($payout->isCancellable(), 422, 'This payout cannot be cancelled - the transfer may already be funded.');

        // A sent-but-unfunded transfer is cancelled at Wise first.
        if ($payout->status === Payout::STATUS_SENT && $payout->wise_transfer_id) {
            try {
                $this->call('put', '/v1/transfers/' . $payout->wise_transfer_id . '/cancel');
            } catch (\Throwable $e) {
                // Already cancelled/refunded at Wise is fine; anything else is not.
                if (!str_contains($e->getMessage(), 'cancel')) {
                    throw $e;
                }
            }
        }

        $payments = app(PaymentService::class);

        DB::transaction(function () use ($payout, $admin, $reason, $payments) {
            $payout->forceFill(['status' => Payout::STATUS_CANCELLED, 'transfer_status' => $payout->wise_transfer_id ? 'cancelled' : null])->save();

            $payment = $payout->payment;
            if ($payment->status === Payment::STATUS_PROCESSING) {
                // processing -> failed -> ready_for_payout: back to a state
                // where a fresh payout can be drafted.
                $payments->transition($payment, Payment::STATUS_FAILED, $admin, 'Payout cancelled before funding.');
                $payments->transition($payment, Payment::STATUS_READY_FOR_PAYOUT, $admin, 'Ready for a new payout.');
            }

            if (in_array($payment->status, [Payment::STATUS_READY_FOR_PAYOUT, Payment::STATUS_FAILED], true) && $payout->method === Payout::METHOD_WISE && !$payout->wise_transfer_id) {
                $payments->transition($payment, $payment->status === Payment::STATUS_FAILED ? Payment::STATUS_READY_FOR_PAYOUT : Payment::STATUS_RECEIVED, $admin, 'Payout cancelled.');
            }

            $payments->ledger($payment, 'cancelled', null, $payout->currency, $admin->id, $payout->transfer_reference, $reason, [], $payout->id);
            $payments->audit($payment, 'payout_cancelled', $admin, null, ['payout_id' => $payout->id], $reason);
        });
    }

    /**
     * The queued job's body: quote -> recipient -> transfer -> fund, logging
     * every call and recording the quote's exchange rate as a conversion.
     */
    public function executeTransfer(Payout $payout): void
    {
        $payment  = $payout->payment;
        $payments = app(PaymentService::class);
        $profile  = $this->profileId();

        $payout->increment('attempts');

        // Queue delivery is at-least-once: a re-run for a payout that
        // already has its transfer must RESUME (fund + sync), never create
        // a second transfer - that would pay the passenger twice. A DEAD
        // transfer (cancelled/refunded at Wise) is the one exception: retry
        // starts over with a fresh idempotency key.
        if ($payout->wise_transfer_id) {
            if (!in_array($payout->transfer_status, Payout::WISE_FAILED_STATES, true)) {
                $this->fundTransfer($payout, $profile);
                $this->refreshStatus($payout->fresh());

                return;
            }

            $payout->forceFill([
                'wise_transfer_id'        => null,
                'wise_quote_id'           => null,
                'wise_recipient_id'       => null,   // fresh recipient too - type rules may differ
                'transfer_status'         => null,
                'customer_transaction_id' => (string) Str::uuid(),
            ])->save();
        }

        // 1. Quote: fixes the exchange rate for this transfer.
        $quote = $this->call('post', "/v3/profiles/{$profile}/quotes", [
            'sourceCurrency' => $payout->source_currency,
            'targetCurrency' => $payout->currency,
            'sourceAmount'   => (float) $payout->source_amount,
            'payOut'         => 'BANK_TRANSFER',
        ]);

        $rate      = (float) ($quote['rate'] ?? 1);
        $converted = round((float) ($quote['targetAmount']
            ?? collect($quote['paymentOptions'] ?? [])->firstWhere('payIn', 'BALANCE')['targetAmount']
            ?? $payout->source_amount * $rate), 2);

        $payout->forceFill([
            'wise_quote_id' => $quote['id'] ?? null,
            'exchange_rate' => $rate,
            'amount'        => $converted,
            'converted_at'  => now(),
        ])->save();

        // Historical conversions are never overwritten - each is a new row.
        if ($payout->currency !== $payout->source_currency) {
            $payments->ledger($payment, 'conversion', $converted, $payout->currency, null, $quote['id'] ?? null, null, [
                'from' => $payout->source_currency, 'to' => $payout->currency,
                'rate' => $rate, 'source_amount' => (float) $payout->source_amount,
                'converted_at' => now()->toIso8601String(),
            ], $payout->id);
        }

        // 2. Recipient. LIVE: email type - Wise collects the bank details
        // from the passenger directly, so we never store account numbers.
        // SANDBOX: email recipients would wait forever for input nobody
        // gives, so Wise's published test bank details are used instead -
        // transfers then complete instantly via wise:simulate.
        if (!$payout->wise_recipient_id) {
            $recipient = $this->call('post', '/v1/accounts', [
                'profile'           => (int) $profile,
                'accountHolderName' => $payout->recipient_name ?: $payout->user->name,
                'currency'          => $payout->currency,
            ] + $this->recipientDetails($payout));
            $payout->forceFill(['wise_recipient_id' => $recipient['id'] ?? null])->save();
        }

        // 3. Transfer (idempotent via customerTransactionId).
        if (!$payout->customer_transaction_id) {
            $payout->forceFill(['customer_transaction_id' => (string) Str::uuid()])->save();
        }

        // Wise dedupes on customerTransactionId - the second line of defence
        // against duplicates even if this code ever runs concurrently.
        $transfer = $this->call('post', '/v1/transfers', [
            'targetAccount'         => (int) $payout->wise_recipient_id,
            'quoteUuid'             => $payout->wise_quote_id,
            'customerTransactionId' => $payout->customer_transaction_id,
            'details'               => ['reference' => Str::limit($payout->transfer_reference, 18, '')],
        ]);

        $payout->forceFill(['wise_transfer_id' => (string) $transfer['id']])->save();
        $funding = $this->fundTransfer($payout, $profile);

        $payout->forceFill([
            'wise_transfer_id' => (string) $transfer['id'],
            'transfer_status'  => $transfer['status'] ?? 'processing',
            'status'           => Payout::STATUS_SENT,
            'transferred_at'   => now(),
            'api_response'     => ['quote' => ['id' => $quote['id'] ?? null, 'rate' => $rate], 'transfer' => $transfer, 'funding' => $funding],
        ])->save();

        $payments->ledger($payment, 'wise_transfer', $converted, $payout->currency, $payout->created_by,
            (string) $transfer['id'], 'Transfer funded from balance', ['reference' => $payout->transfer_reference], $payout->id);
        $payments->audit($payment, 'wise_transfer_created', null, null, [
            'payout_id' => $payout->id, 'wise_transfer_id' => (string) $transfer['id'], 'rate' => $rate,
        ]);

        $payment->claim?->recordEvent(sprintf('Your payout of %s is on its way', $payout->money()), 'done', now(), 2);
        $payout->user?->notify(new PaymentEvent(
            $payment, 'Your payout is on its way',
            sprintf('%s sent via Wise. Reference: %s.', $payout->money(), $payout->transfer_reference),
            'payout-initiated', ['reference' => $payout->transfer_reference],
        ));
    }

    /**
     * Recipient type + details. Sandbox uses Wise's test bank details so
     * transfers never wait on recipient input; live uses email recipients.
     *
     * @return array{type: string, details: array}
     */
    private function recipientDetails(Payout $payout): array
    {
        // The account chosen at draft time, else the customer's saved account
        // for this currency - the money goes exactly where they told us.
        $saved = $payout->payoutAccount
            ?? $payout->user->payoutAccounts()->where('currency', $payout->currency)->orderByDesc('is_default')->first();

        if ($saved) {
            $payout->forceFill(['recipient_iban' => $saved->masked])->save();

            return $saved->wiseRecipient();
        }

        if ($this->sandbox()) {
            $test = match ($payout->currency) {
                'GBP' => ['type' => 'sort_code', 'details' => ['legalType' => 'PRIVATE', 'sortCode' => '231470', 'accountNumber' => '28821822']],
                'EUR' => ['type' => 'iban', 'details' => ['legalType' => 'PRIVATE', 'IBAN' => 'DE89370400440532013000']],
                default => null,
            };

            if ($test) {
                return $test;
            }
        }

        return ['type' => 'email', 'details' => ['email' => $payout->user->email]];
    }

    /** Fund an existing transfer - the admin/simulate-facing wrapper. */
    public function fund(Payout $payout): array
    {
        abort_unless($payout->wise_transfer_id, 422, 'No Wise transfer to fund yet.');

        return $this->fundTransfer($payout, (string) $this->profileId());
    }

    /**
     * Fund a transfer from the Wise balance. In the SANDBOX an empty balance
     * or missing SCA key is normal - the transfer stays unfunded (advance it
     * with wise:simulate) instead of failing the payout. Live failures throw.
     */
    private function fundTransfer(Payout $payout, string $profile): array
    {
        try {
            return $this->call('post', "/v3/profiles/{$profile}/transfers/{$payout->wise_transfer_id}/payments", ['type' => 'BALANCE']);
        } catch (\Throwable $e) {
            if (!$this->sandbox()) {
                throw $e;
            }

            Log::warning('Wise sandbox funding failed - continuing unfunded for simulation', ['error' => $e->getMessage()]);

            return ['skipped' => 'sandbox funding unavailable - use wise:simulate'];
        }
    }

    /** Terminal failure (after the job's retries ran out). */
    public function markFailed(Payout $payout, string $error): void
    {
        $payments = app(PaymentService::class);
        $payment  = $payout->payment;

        // Atomic claim, same as completion: concurrent failure signals
        // (webhook + refresh) must produce ONE alert, not two.
        $claimed = Payout::whereKey($payout->id)
            ->where('status', '!=', Payout::STATUS_FAILED)
            ->update(['status' => Payout::STATUS_FAILED, 'error_message' => Str::limit($error, 2000), 'updated_at' => now()]);

        if (!$claimed) {
            return;
        }

        $payout->refresh();
        $payment->refresh();

        if ($payment->status === Payment::STATUS_PROCESSING) {
            $payments->transition($payment, Payment::STATUS_FAILED, null, $error);
        }

        $payments->ledger($payment, 'failed', null, $payout->currency, null, $payout->transfer_reference, Str::limit($error, 500), [], $payout->id);
        $payments->audit($payment, 'wise_transfer_failed', null, null, ['payout_id' => $payout->id], Str::limit($error, 500));

        $payout->user?->notify(new PaymentEvent(
            $payment, 'A problem with your payout',
            'Your payout could not be completed - our team has been alerted and is on it.',
            'payout-failed',
        ));

        $payments->alertAdmins($payment, 'Wise transfer failed - retry required',
            sprintf('Payout %s on claim [CLAIM] failed after %d attempt(s): %s', $payout->transfer_reference, $payout->attempts, Str::limit($error, 200)));
    }

    /** Wise webhook / manual refresh: apply the transfer's current state. */
    public function applyTransferState(Payout $payout, string $state): void
    {
        // A payout we already closed (cancelled locally, or completed) is
        // immune to late webhooks for its old transfer - otherwise Wise's
        // cancellation echo would re-open it and fail a payment that has a
        // NEW payout in flight.
        if (in_array($payout->status, [Payout::STATUS_CANCELLED, Payout::STATUS_COMPLETED], true)) {
            $payout->forceFill(['transfer_status' => $state])->save();

            return;
        }

        $payments = app(PaymentService::class);
        $payment  = $payout->payment;

        if (in_array($state, Payout::WISE_COMPLETE_STATES, true)) {
            // The webhook and a manual refresh/simulate can race to complete
            // the same transfer. This conditional UPDATE is the atomic claim:
            // exactly one process flips the status and gets a row back - only
            // that one may write the ledger and notify the customer.
            $claimed = Payout::whereKey($payout->id)
                ->where('status', '!=', Payout::STATUS_COMPLETED)
                ->update(['status' => Payout::STATUS_COMPLETED, 'transfer_status' => $state, 'updated_at' => now()]);

            if (!$claimed) {
                return;
            }

            $payout->refresh();
            $payment->refresh();

            if ($payment->status === Payment::STATUS_PROCESSING) {
                $payments->transition($payment, Payment::STATUS_PAID, null, 'Wise confirmed the transfer.');
            }
            $payments->ledger($payment, 'completed', (float) $payout->amount, $payout->currency, null, $payout->wise_transfer_id, null, [], $payout->id);
            $payments->audit($payment, 'wise_transfer_completed', null, null, ['payout_id' => $payout->id, 'state' => $state]);

            $payment->claim?->recordEvent(sprintf('Your payout of %s has arrived', $payout->money()), 'done', now(), 2);
            $payout->user?->notify(new PaymentEvent(
                $payment, 'Your payout has completed',
                sprintf('%s has been paid out. Reference: %s.', $payout->money(), $payout->transfer_reference),
                'payout-completed', ['reference' => $payout->transfer_reference],
            ));

            return;
        }

        $payout->forceFill(['transfer_status' => $state])->save();

        if (in_array($state, Payout::WISE_FAILED_STATES, true)) {
            $this->markFailed($payout, "Wise reported state \"{$state}\".");
        }
    }

    /** Poll Wise for the current transfer state (admin "refresh" button). */
    public function refreshStatus(Payout $payout): void
    {
        abort_unless($payout->wise_transfer_id, 422, 'No Wise transfer exists for this payout yet.');

        $transfer = $this->call('get', '/v1/transfers/' . $payout->wise_transfer_id);
        $this->applyTransferState($payout, (string) ($transfer['status'] ?? 'unknown'));
    }

    /** One logged, error-checked Wise API call, with SCA signing on challenge. */
    private function call(string $method, string $path, array $payload = []): array
    {
        $response = $this->http()->{$method}($path, $payload);

        // Strong Customer Authentication: Wise answers 403 with a one-time
        // token; signing it with our registered key and retrying satisfies
        // the challenge (required for balance payments on personal tokens).
        if ($response->status() === 403 && ($ott = $response->header('x-2fa-approval'))) {
            $signature = $this->signSca($ott);

            if ($signature !== null) {
                $response = $this->http()
                    ->withHeaders(['x-2fa-approval' => $ott, 'X-Signature' => $signature])
                    ->{$method}($path, $payload);
            }
        }

        Log::info('Wise API call', [
            'method' => strtoupper($method), 'path' => $path,
            'status' => $response->status(),
            // Payload logged without personal details.
            'keys'   => array_keys($payload),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(sprintf('Wise %s %s failed (HTTP %d): %s',
                strtoupper($method), $path, $response->status(), Str::limit($response->body(), 400)));
        }

        return $response->json() ?? [];
    }

    /** Base64 RSA-SHA256 signature of the SCA one-time token, or null when no key. */
    private function signSca(string $oneTimeToken): ?string
    {
        $path = config('services.wise.sca_private_key');

        if (!$path || !is_readable($path)) {
            return null;
        }

        $key = openssl_pkey_get_private((string) file_get_contents($path));

        if (!$key || !openssl_sign($oneTimeToken, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::warning('Wise SCA signing failed - check WISE_SCA_PRIVATE_KEY');

            return null;
        }

        return base64_encode($signature);
    }
}
