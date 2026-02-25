<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Login
    Route::get('login', [AuthController::class, 'createLogin'])->name('login');
    Route::post('login', [AuthController::class, 'storeLogin']);

    // Register
    Route::get('register', [AuthController::class, 'createRegister'])->name('register');
    Route::post('register', [AuthController::class, 'storeRegister']);

    // Forgot Password (View)
    Route::get('forgot-password', [AuthController::class, 'createForgotPassword'])->name('password.request');

    // Forgot Password (Action - Placeholder for now)
    Route::post('forgot-password', [AuthController::class, 'storeForgotPassword'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'submitResetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    // Logout
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
});
