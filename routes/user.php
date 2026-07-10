<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\{DashboardController, CaseController, DocumentController, TemplateController, EmailController, ItineraryController};
use App\Http\Controllers\AiReplyController;
/*
|--------------------------------------------------------------------------
| User / Client Routes
|--------------------------------------------------------------------------
| Prefix: /app
| Name: user.*
| Middleware: auth, verified
*/

Route::middleware(['auth', 'verified', 'role_access:user'])->name('user.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Flight Disputes — Vue module (JSON API + SPA shell)
    Route::prefix('flight-disputes')->name('itineraries.')->group(function () {
        // Claims API
        Route::get('api/claims', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'index'])->name('api.claims.index');
        Route::post('api/claims', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'store'])->name('api.claims.store');
        Route::get('api/claims/{claim}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'show'])->name('api.claims.show');

        // Trips API — "Protect Your Trip" (declared before the SPA catch-all)
        Route::get('api/trips', [\App\Http\Controllers\User\Api\TripApiController::class, 'index'])->name('api.trips.index');
        Route::post('api/trips', [\App\Http\Controllers\User\Api\TripApiController::class, 'store'])->name('api.trips.store');
        Route::post('api/trips/upload', [\App\Http\Controllers\User\Api\TripApiController::class, 'upload'])->name('api.trips.upload');
        Route::get('api/trips/{trip}', [\App\Http\Controllers\User\Api\TripApiController::class, 'show'])->whereNumber('trip')->name('api.trips.show');
        Route::get('api/trips/{trip}/ticket', [\App\Http\Controllers\User\Api\TripApiController::class, 'ticket'])->whereNumber('trip')->name('api.trips.ticket');
        Route::get('api/trips/{trip}/monitoring', [\App\Http\Controllers\User\Api\TripApiController::class, 'monitoring'])->whereNumber('trip')->name('api.trips.monitoring');
        Route::post('api/trips/{trip}/sync', [\App\Http\Controllers\User\Api\TripApiController::class, 'sync'])->whereNumber('trip')->name('api.trips.sync');
        Route::delete('api/trips/{trip}', [\App\Http\Controllers\User\Api\TripApiController::class, 'destroy'])->whereNumber('trip')->name('api.trips.destroy');

        // Itinerary API (declared before the SPA catch-all)
        Route::get('api/list', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'index'])->name('api.index');
        Route::post('api/upload', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'store'])->name('api.store');
        Route::get('api/{itinerary}', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'show'])->whereNumber('itinerary')->name('api.show');
        Route::post('api/{itinerary}/reparse', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'reparse'])->whereNumber('itinerary')->name('api.reparse');
        Route::delete('api/{itinerary}', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'destroy'])->whereNumber('itinerary')->name('api.destroy');

        // Original file download
        Route::get('{itinerary}/file', [ItineraryController::class, 'file'])->whereNumber('itinerary')->name('file');

        // Vue SPA shell — handles /flight-disputes and any client-side route
        // (e.g. /flight-disputes/claims/new, /flight-disputes/claims/{hash}) on reload/deep-link.
        Route::get('{path?}', [ItineraryController::class, 'index'])->where('path', '.*')->name('index');
    });

    // ✅ CREATE must come BEFORE {case_reference_id}
    Route::get('/cases/create', [CaseController::class, 'createStep1'])
        ->name('cases.create');

    Route::get('/cases', [CaseController::class, 'index'])
        ->name('cases.index');

    Route::get('/cases/{case_reference_id}', [CaseController::class, 'show'])
        ->name('cases.show');
    Route::get('/cases/{case}/export', [CaseController::class, 'exportPdf'])->name('cases.export');
    Route::get('/api/institutions/search', [CaseController::class, 'searchInstitutions'])
        ->name('api.institutions.search');

    // Documents Route
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/document/view/{attachment}', [DocumentController::class, 'showPublic'])
    ->name('evidence.view');
    Route::get('/document/download/{attachment}', [DocumentController::class, 'downloadSecure'])
    ->name('evidence.download')
    ->middleware('signed');

    // lettler templates — requires active subscription or cases remaining
    Route::middleware('requires_subscription')->group(function () {
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/search', [TemplateController::class, 'search'])->name('templates.search');
    });
    //emails
    Route::resource('emails', EmailController::class)->only(['index', 'show', 'create', 'store']);
    
    // Add this new route for sending emails from the case timeline
    Route::post('/cases/{case}/send-email', [CaseController::class, 'sendEmail'])->name('cases.send_email');
    Route::post('/cases/{case}/update-stage', [App\Http\Controllers\User\CaseController::class, 'updateStage'])
    ->name('user.cases.update_stage');

    Route::post('/cases/{case_id}/ai-reply', [AiReplyController::class, 'generate'])->name('ai.generate-reply');

//     
});
