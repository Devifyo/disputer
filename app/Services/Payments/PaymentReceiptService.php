<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The branded PDF receipt for a payment - one builder for both the admin
 * download and the customer's. The customer variant hides internal retry
 * churn (failed/cancelled attempts); the money that moved is identical.
 */
class PaymentReceiptService
{
    public function number(Payment $payment): string
    {
        return sprintf('RCPT-%s-%05d', $payment->claim?->number ?? 'X', $payment->id);
    }

    public function download(Payment $payment, bool $internal = true): Response
    {
        $payment->load(['claim', 'user', 'payouts', 'transactions']);

        $transactions = $payment->transactions->sortBy('id');

        if (!$internal) {
            $transactions = $transactions->reject(fn ($tx) => in_array($tx->type, ['failed', 'cancelled'], true));
        }

        $number = $this->number($payment);

        return Pdf::loadView('pdf.payment-receipt', [
            'payment'       => $payment,
            'payouts'       => $payment->payouts->whereIn('status', ['completed', 'sent', 'processing'])->sortBy('id'),
            'transactions'  => $transactions->values(),
            'receiptNumber' => $number,
            'issuedAt'      => now(),
        ])->setPaper('a4')->download("{$number}.pdf");
    }
}
