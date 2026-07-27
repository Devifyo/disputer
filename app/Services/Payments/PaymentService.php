<?php

namespace App\Services\Payments;

use App\Models\Claim;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentEvent;
use App\Services\Claims\AdminAlertRecipients;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of payment state. Every mutation runs in a transaction,
 * lands in the append-only ledger and the immutable audit log, and fires
 * the queued notifications. The success fee comes from Settings (default
 * 25%); overriding it on a specific payment requires its own permission.
 */
class PaymentService
{
    /** Which status changes are legal - money never teleports. */
    private const TRANSITIONS = [
        Payment::STATUS_PENDING          => [Payment::STATUS_RECEIVED, Payment::STATUS_CANCELLED],
        Payment::STATUS_RECEIVED         => [Payment::STATUS_READY_FOR_PAYOUT, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED],
        Payment::STATUS_READY_FOR_PAYOUT => [Payment::STATUS_PROCESSING, Payment::STATUS_RECEIVED, Payment::STATUS_CANCELLED],
        Payment::STATUS_PROCESSING       => [Payment::STATUS_PAID, Payment::STATUS_FAILED],
        Payment::STATUS_FAILED           => [Payment::STATUS_READY_FOR_PAYOUT, Payment::STATUS_PROCESSING, Payment::STATUS_CANCELLED],
        Payment::STATUS_PAID             => [Payment::STATUS_REFUNDED],
        Payment::STATUS_CANCELLED        => [],
        Payment::STATUS_REFUNDED         => [],
    ];

    public static function defaultFeePercent(): float
    {
        return (float) Setting::get('claims.success_fee_percent', 25);
    }

    /** Airline paid us: record it, split it, tell everyone who needs to know. */
    public function record(Claim $claim, array $data, User $admin): Payment
    {
        $feePercent = self::defaultFeePercent();

        if (isset($data['fee_percent']) && (float) $data['fee_percent'] !== $feePercent) {
            abort_unless($admin->can('payments.override_fee'), 403, 'You do not have permission to override the success fee.');
            $feePercent = (float) $data['fee_percent'];
        }

        $gross = round((float) $data['gross_amount'], 2);
        $fee   = round($gross * $feePercent / 100, 2);

        $payment = DB::transaction(function () use ($claim, $data, $admin, $feePercent, $gross, $fee) {
            $payment = Payment::create([
                'claim_id'     => $claim->id,
                'user_id'      => $claim->user_id,
                'airline'      => $claim->airline,
                'currency'     => strtoupper($data['currency']),
                'gross_amount' => $gross,
                'fee_percent'  => $feePercent,
                'fee_amount'   => $fee,
                'net_amount'   => round($gross - $fee, 2),
                'status'       => Payment::STATUS_RECEIVED,
                'payment_date' => $data['payment_date'],
                'reference'    => $data['reference'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $admin->id,
                'updated_by'   => $admin->id,
            ]);

            $this->ledger($payment, 'payment_received', $gross, $payment->currency, $admin->id, $data['reference'] ?? null);
            $this->ledger($payment, 'fee_deducted', -$fee, $payment->currency, $admin->id, null,
                sprintf('%s%% success fee', rtrim(rtrim(number_format($feePercent, 2), '0'), '.')));

            $this->audit($payment, 'created', $admin, null, $payment->only([
                'currency', 'gross_amount', 'fee_percent', 'fee_amount', 'net_amount', 'status', 'payment_date',
            ]));

            return $payment;
        });

        $claim->recordEvent(sprintf('The airline paid %s - your payout of %s is being prepared',
            $payment->money($payment->gross_amount), $payment->money($payment->net_amount)), 'done', now(), 2);

        $payment->user?->notify(new PaymentEvent(
            $payment,
            'The airline has paid your claim',
            sprintf('%s received - your payout of %s is being prepared.', $payment->money($payment->gross_amount), $payment->money($payment->net_amount)),
            'payment-received',
        ));

        $this->alertAdmins($payment, 'New payment received',
            sprintf('%s was recorded on claim [CLAIM] by %s.', $payment->money($payment->gross_amount), $admin->name));

        $threshold = (float) Setting::get('payments.large_payment_threshold', 10000);
        if ((float) $payment->gross_amount >= $threshold) {
            $this->alertAdmins($payment, 'Large payment alert',
                sprintf('%s exceeds the large-payment threshold (%s) - please review.', $payment->money($payment->gross_amount), number_format($threshold, 0)));
        }

        return $payment;
    }

    /** Change the fee on an existing, not-yet-paid payment. Permissioned. */
    public function overrideFee(Payment $payment, float $newPercent, User $admin, ?string $reason = null): Payment
    {
        abort_unless($admin->can('payments.override_fee'), 403, 'You do not have permission to override the success fee.');
        abort_if(in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED], true),
            422, 'The fee cannot change after the money has moved.');

        return DB::transaction(function () use ($payment, $newPercent, $admin, $reason) {
            $old = $payment->only(['fee_percent', 'fee_amount', 'net_amount']);

            $fee = round((float) $payment->gross_amount * $newPercent / 100, 2);
            $payment->forceFill([
                'fee_percent' => $newPercent,
                'fee_amount'  => $fee,
                'net_amount'  => round((float) $payment->gross_amount - $fee, 2),
                'updated_by'  => $admin->id,
            ])->save();

            // Calculation history: a fresh ledger row per recalculation.
            $this->ledger($payment, 'fee_deducted', -$fee, $payment->currency, $admin->id, null,
                sprintf('Fee changed to %s%%%s', rtrim(rtrim(number_format($newPercent, 2), '0'), '.'), $reason ? " - {$reason}" : ''));

            $this->audit($payment, 'fee_changed', $admin, $old, $payment->only(['fee_percent', 'fee_amount', 'net_amount']), $reason);

            return $payment->fresh();
        });
    }

    /** Guarded status move + ledger + audit. */
    public function transition(Payment $payment, string $to, ?User $admin = null, ?string $notes = null): Payment
    {
        $allowed = self::TRANSITIONS[$payment->status] ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("A payment cannot move from {$payment->status} to {$to}.");
        }

        return DB::transaction(function () use ($payment, $to, $admin, $notes) {
            $old = $payment->status;
            $payment->forceFill(['status' => $to, 'updated_by' => $admin?->id])->save();

            // The state machine audits every hop; LEDGER rows are written by
            // the business action itself (payout completed, refund...) so
            // each money event appears exactly once, with its amount.
            $this->audit($payment, 'status_changed', $admin, ['status' => $old], ['status' => $to], $notes);

            return $payment->fresh();
        });
    }

    public function refund(Payment $payment, User $admin, ?string $reason = null): Payment
    {
        $payment = $this->transition($payment, Payment::STATUS_REFUNDED, $admin, $reason);
        $this->ledger($payment, 'refund', (float) $payment->gross_amount, $payment->currency, $admin->id, null, $reason);

        $payment->user?->notify(new PaymentEvent(
            $payment, 'Refund processed',
            sprintf('A refund of %s was processed on your claim.', $payment->money($payment->gross_amount)),
            'payment-refunded', ['reference' => $reason],
        ));

        return $payment;
    }

    /**
     * Nudge the customer to add their payout bank details - email + in-app
     * bell + a claim timeline step, so the request is visible everywhere.
     */
    public function requestBankDetails(Payment $payment, User $admin): void
    {
        abort_unless(in_array($payment->status, [Payment::STATUS_RECEIVED, Payment::STATUS_READY_FOR_PAYOUT], true),
            422, 'Bank details are only requested while a payout is pending.');

        $payment->user?->notify(new PaymentEvent(
            $payment, 'Add your bank details - your payout is waiting',
            sprintf('Your payout of %s is ready. Add your bank account on your claim page so we can send it.', $payment->money($payment->net_amount)),
            'payout-details-request',
        ));

        $payment->claim?->recordEvent('We need your bank details to send your payout', 'active', now(), 2);
        $this->audit($payment, 'bank_details_requested', $admin, null, ['net_amount' => (float) $payment->net_amount]);
    }

    /** Append a ledger row - the transaction history the UIs render. */
    public function ledger(Payment $payment, string $type, ?float $amount, ?string $currency, ?int $actorId = null, ?string $reference = null, ?string $notes = null, array $meta = [], ?int $payoutId = null): void
    {
        $payment->transactions()->create([
            'payout_id'    => $payoutId,
            'type'         => $type,
            'amount'       => $amount,
            'currency'     => $currency,
            'reference'    => $reference,
            'status'       => $payment->status,
            'performed_by' => $actorId,
            'notes'        => $notes,
            'meta'         => $meta ?: null,
        ]);
    }

    public function audit(Payment $payment, string $action, ?User $admin, ?array $old, ?array $new, ?string $notes = null): void
    {
        $payment->logs()->create([
            'action'     => $action,
            'user_id'    => $admin?->id,
            'ip'         => request()?->ip(),
            'old_values' => $old,
            'new_values' => $new,
            'notes'      => $notes,
        ]);
    }

    /** Email the configured operations mailboxes + in-app bell for admin users. */
    public function alertAdmins(Payment $payment, string $subject, string $body): void
    {
        foreach (AdminAlertRecipients::for(AdminAlertRecipients::TYPE_PAYMENTS) as $recipient) {
            dispatch(function () use ($recipient, $payment, $subject, $body) {
                send_dynamic_email($recipient['email'], 'payment-admin-alert', [
                    '[NAME]'    => $recipient['name'],
                    '[SUBJECT]' => $subject,
                    '[BODY]'    => str_replace('[CLAIM]', '#' . ($payment->claim?->number ?? $payment->claim_id), $body),
                    '[CLAIM]'   => '#' . ($payment->claim?->number ?? $payment->claim_id),
                    '[AMOUNT]'  => $payment->money($payment->gross_amount),
                    '[URL]'     => route('admin.flight-claims.payments'),
                ]);
            });
        }

        foreach (User::role('admin')->get() as $admin) {
            $admin->notify(new PaymentEvent($payment, $subject, str_replace('[CLAIM]', '#' . ($payment->claim?->number ?? $payment->claim_id), $body)));
        }

        $this->audit($payment, 'notification_sent', null, null, ['subject' => $subject]);
    }
}
