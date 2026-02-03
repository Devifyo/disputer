<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\DashboardController;

/*
|--------------------------------------------------------------------------
| User / Client Routes
|--------------------------------------------------------------------------
| Prefix: /app
| Name: user.*
| Middleware: auth, verified
*/

Route::middleware(['auth', 'verified'])->prefix('app')->name('user.')->group(function () {

    // Dashboard -> route('user.dashboard')
    // URL: /app/dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Step 1: Show the "Select Institution" Page
    Route::get('/cases/create', [\App\Http\Controllers\User\CaseController::class, 'createStep1'])->name('cases.create');

    // API: Live Search for Institutions (Used by the Step 1 JS)
    Route::get('/api/institutions/search', [\App\Http\Controllers\User\CaseController::class, 'searchInstitutions'])->name('api.institutions.search');
    // My Disputes -> route('user.cases.index')
    // Route::resource('cases', \App\Http\Controllers\User\CaseController::class);

    // Profile Settings
    // Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});
