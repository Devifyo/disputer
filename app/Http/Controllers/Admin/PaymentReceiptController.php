<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The professional PDF receipt for a payment: compensation in, fee, payouts
 * out with their transaction numbers and exchange rates, full ledger.
 */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment)
    {
        abort_unless(auth()->user()->can('payments.view'), 403);

        $payment->load(['claim', 'user', 'payouts', 'transactions']);

        $receiptNumber = sprintf('RCPT-%s-%05d', $payment->claim?->number ?? 'X', $payment->id);

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment'       => $payment,
            'payouts'       => $payment->payouts->whereIn('status', ['completed', 'sent', 'processing'])->sortBy('id'),
            'transactions'  => $payment->transactions->sortBy('id'),
            'receiptNumber' => $receiptNumber,
            'issuedAt'      => now(),
        ])->setPaper('a4');

        return $pdf->download("{$receiptNumber}.pdf");
    }
}
