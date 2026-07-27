<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentReceiptService;

/** Admin copy of the payment receipt - full ledger including retries. */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, PaymentReceiptService $receipts)
    {
        abort_unless(auth()->user()->can('payments.view'), 403);

        return $receipts->download($payment);
    }
}
