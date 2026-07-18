<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\{MarketingController, SupportController};
use App\Http\Controllers\{CheckoutController, StripeWebhookController};
// 1. Put the root route OUTSIDE the auth middleware
Route::get('/', [MarketingController::class, 'index'])->name('home');
Route::get('/api/live-disruptions', [MarketingController::class, 'liveDisruptions'])
    ->middleware('throttle:30,1')->name('live-disruptions');
Route::get('/support', [SupportController::class, 'index'])->name('support');
Route::post('/support', [SupportController::class, 'submit'])->name('support.submit');

// 2. Keep the rest INSIDE the auth middleware
Route::middleware('auth')->group(function () {
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Email Config
    Route::patch('/profile/email', [ProfileController::class, 'updateEmailConfig'])->name('profile.email.update');

    // Password Update
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // payment routes
    Route::get('/checkout/{slug}', [CheckoutController::class, 'checkout'])->name('checkout');
    Route::get('/checkout-success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::post('/subscription/cancel', [CheckoutController::class, 'cancelSubscription'])->name('subscription.cancel');
    Route::post('/subscription/resume', [CheckoutController::class, 'resumeSubscription'])->name('subscription.resume');
    Route::get('/billing/payment-methods', [CheckoutController::class, 'getPaymentMethods'])->name('billing.payment-methods');
    Route::delete('/billing/payment-method', [CheckoutController::class, 'removePaymentMethod'])->name('billing.remove-payment-method');
    Route::post('/billing/set-default-payment-method', [CheckoutController::class, 'setDefaultPaymentMethod'])->name('billing.set-default-payment-method');
    Route::get('/billing/setup-intent', [CheckoutController::class, 'getSetupIntent'])->name('billing.setup-intent');
    Route::post('/billing/payment-method', [CheckoutController::class, 'updatePaymentMethod'])->name('billing.update-payment-method');
});

Route::get('/privacy', function () {
    return view('common.privacy');
})->name('privacy');

Route::get('/terms', function () {
    return view('common.terms');
})->name('terms');
// Stripe Webhook Endpoint (Must be POST, must be outside auth middleware)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Tokenised public signing page for claim co-passengers without an account.
Route::get('/claim-signature/{token}', [App\Http\Controllers\ClaimSignatureController::class, 'show'])->name('claim-signature.show');
Route::post('/claim-signature/{token}', [App\Http\Controllers\ClaimSignatureController::class, 'store'])->name('claim-signature.store');

Route::impersonate();
require __DIR__.'/auth.php';
require __DIR__.'/user.php';
require __DIR__.'/admin.php';
// Assuming you have an admin.php or similar included elsewhere