<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController;

// 1. Put the root route OUTSIDE the auth middleware
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->role === 'admin' 
            ? redirect()->route('admin.dashboard') 
            : redirect()->route('user.dashboard');
    }
    
    // If not logged in, redirect to login cleanly
    return redirect()->route('login');
});


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
});

require __DIR__.'/auth.php';
require __DIR__.'/user.php';
require __DIR__.'/admin.php';
// Assuming you have an admin.php or similar included elsewhere