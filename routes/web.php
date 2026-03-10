<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\{CheckoutController, StripeWebhookController};
// 1. Put the root route OUTSIDE the auth middleware
Route::get('/', [MarketingController::class, 'index'])->name('home');


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

    Route::get('/checkout/{slug}', [CheckoutController::class, 'checkout'])->name('checkout');
    Route::get('/checkout-success', [CheckoutController::class, 'success'])->name('checkout.success');
});

Route::get('/privacy', function () {
    return view('common.privacy');
})->name('privacy');

Route::get('/terms', function () {
    return view('common.terms');
})->name('terms');
// Stripe Webhook Endpoint (Must be POST, must be outside auth middleware)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

Route::impersonate();
require __DIR__.'/auth.php';
require __DIR__.'/user.php';
require __DIR__.'/admin.php';
// Assuming you have an admin.php or similar included elsewhere