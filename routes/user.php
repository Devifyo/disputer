<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\{DashboardController, CaseController, DocumentController, TemplateController, EmailController};

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

    // Documents Route
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/document/view/{attachment}', [DocumentController::class, 'showPublic'])
    ->name('evidence.view');
    Route::get('/document/download/{attachment}', [DocumentController::class, 'downloadSecure'])
    ->name('evidence.download')
    ->middleware('signed');

    // lettler templates
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    //emails
    Route::resource('emails', EmailController::class)->only(['index', 'show', 'create', 'store']);
    Route::get('/templates/search', [TemplateController::class, 'search'])->name('templates.search');

});
