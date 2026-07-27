<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentReceiptService;
use Illuminate\Support\Facades\Auth;

/** The customer's copy of the payment receipt - own payments only. */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, PaymentReceiptService $receipts)
    {
        abort_unless($payment->user_id === Auth::id(), 403);

        return $receipts->download($payment, internal: false);
    }
}
