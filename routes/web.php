<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController; // Import this

Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy'); // <--- ADD THIS
    
    // Email Config
    Route::patch('/profile/email', [ProfileController::class, 'updateEmailConfig'])->name('profile.email.update');

    // Password Update
    Route::put('password', [PasswordController::class, 'update'])->name('password.update'); // <--- ADD THIS
});

require __DIR__.'/auth.php';
require __DIR__.'/user.php';