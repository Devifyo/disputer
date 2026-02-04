<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\{DashboardController, CaseController};

/*
|--------------------------------------------------------------------------
| User / Client Routes
|--------------------------------------------------------------------------
| Prefix: /app
| Name: user.*
| Middleware: auth, verified
*/

Route::middleware(['auth', 'verified'])->name('user.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ✅ CREATE must come BEFORE {case_reference_id}
    Route::get('/cases/create', [CaseController::class, 'createStep1'])
        ->name('cases.create');

    Route::get('/cases', [CaseController::class, 'index'])
        ->name('cases.index');

    Route::get('/cases/{case_reference_id}', [CaseController::class, 'show'])
        ->name('cases.show');

    Route::get('/api/institutions/search', [CaseController::class, 'searchInstitutions'])
        ->name('api.institutions.search');

});
