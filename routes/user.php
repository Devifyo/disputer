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
        Route::get('api/claims', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'index'])->middleware('module:claims')->name('api.claims.index');
        Route::post('api/claims', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'store'])->middleware('module:claims')->name('api.claims.store');
        Route::get('api/claims/{claim}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'show'])->middleware('module:claims')->name('api.claims.show');
        Route::get('api/claims/{claim}/document/{index}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'document'])->middleware('module:claims')->whereNumber('index')->name('api.claims.document');
        Route::post('api/claims/{claim}/info', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'updateInfo'])->middleware('module:claims')->name('api.claims.info');
        Route::post('api/claims/{claim}/documents', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'addDocuments'])->middleware('module:claims')->name('api.claims.documents');

        // Payout bank accounts (encrypted at rest, masked on the way out)
        Route::get('api/payout-accounts', [\App\Http\Controllers\User\Api\PayoutAccountApiController::class, 'index'])->name('api.payout-accounts');
        Route::post('api/payout-accounts', [\App\Http\Controllers\User\Api\PayoutAccountApiController::class, 'store'])->name('api.payout-accounts.store');
        Route::post('api/payout-accounts/{currency}/default', [\App\Http\Controllers\User\Api\PayoutAccountApiController::class, 'makeDefault'])->name('api.payout-accounts.default');
        Route::get('payments/{payment}/receipt', \App\Http\Controllers\User\PaymentReceiptController::class)->name('payments.receipt');
        Route::delete('api/payout-accounts/{currency}', [\App\Http\Controllers\User\Api\PayoutAccountApiController::class, 'destroy'])->name('api.payout-accounts.destroy');

        // In-app notifications
        Route::get('api/notifications', [\App\Http\Controllers\User\Api\NotificationApiController::class, 'index'])->name('api.notifications');
        Route::post('api/notifications/read', [\App\Http\Controllers\User\Api\NotificationApiController::class, 'markRead'])->name('api.notifications.read');

        // Unjamm Plus billing (Stripe Checkout + Customer Portal)
        Route::get('api/billing', [\App\Http\Controllers\User\Api\BillingApiController::class, 'overview'])->name('api.billing.overview');
        Route::post('api/billing/checkout', [\App\Http\Controllers\User\Api\BillingApiController::class, 'checkout'])->name('api.billing.checkout');
        Route::post('api/billing/portal', [\App\Http\Controllers\User\Api\BillingApiController::class, 'portal'])->name('api.billing.portal');

        // Out-of-pocket expense receipts
        Route::post('api/claims/{claim}/expenses', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'addExpense'])->middleware('module:claims')->name('api.claims.expenses.store');
        Route::delete('api/claims/{claim}/expenses/{expense}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'removeExpense'])->middleware('module:claims')->whereNumber('expense')->name('api.claims.expenses.destroy');
        Route::get('api/claims/{claim}/expenses/{expense}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'expenseFile'])->middleware('module:claims')->whereNumber('expense')->name('api.claims.expense');

        // Claim confirmation + e-signatures
        Route::get('api/claims/{claim}/confirmation', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'confirmation'])->middleware('module:claims')->name('api.claims.confirmation');
        Route::post('api/claims/{claim}/passengers', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'updatePassengers'])->middleware('module:claims')->name('api.claims.passengers');
        Route::post('api/claims/{claim}/confirm', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'confirm'])->middleware('module:claims')->name('api.claims.confirm');
        Route::get('api/claims/{claim}/signers', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'signers'])->middleware('module:claims')->name('api.claims.signers');
        Route::get('api/claims/{claim}/signers/{signer}/url', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'signUrl'])->middleware('module:claims')->whereNumber('signer')->name('api.claims.sign-url');
        Route::post('api/claims/{claim}/signers/{signer}/sign', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'sign'])->middleware('module:claims')->whereNumber('signer')->name('api.claims.sign');
        Route::post('api/claims/{claim}/signers/{signer}/invite', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'inviteSigner'])->middleware('module:claims')->whereNumber('signer')->name('api.claims.invite');
        Route::get('api/claims/{claim}/legal/{doc}', [\App\Http\Controllers\User\Api\ClaimApiController::class, 'legalDocument'])->middleware('module:claims')->name('api.claims.legal');

        // Trips API — "Protect Your Trip" (declared before the SPA catch-all)
        Route::get('api/trips', [\App\Http\Controllers\User\Api\TripApiController::class, 'index'])->middleware('module:trips')->name('api.trips.index');
        Route::post('api/trips', [\App\Http\Controllers\User\Api\TripApiController::class, 'store'])->middleware('module:trips')->name('api.trips.store');
        Route::post('api/trips/upload', [\App\Http\Controllers\User\Api\TripApiController::class, 'upload'])->middleware('module:trips')->name('api.trips.upload');
        Route::get('api/trips/{trip}', [\App\Http\Controllers\User\Api\TripApiController::class, 'show'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.show');
        Route::get('api/trips/{trip}/ticket', [\App\Http\Controllers\User\Api\TripApiController::class, 'ticket'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.ticket');
        Route::get('api/trips/{trip}/monitoring', [\App\Http\Controllers\User\Api\TripApiController::class, 'monitoring'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.monitoring');
        Route::post('api/trips/{trip}/sync', [\App\Http\Controllers\User\Api\TripApiController::class, 'sync'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.sync');
        Route::post('api/trips/{trip}/claim', [\App\Http\Controllers\User\Api\TripApiController::class, 'createClaim'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.claim');
        Route::post('api/trips/{trip}/report/questions', [\App\Http\Controllers\User\Api\TripApiController::class, 'reportQuestions'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.report.questions');
        Route::post('api/trips/{trip}/report', [\App\Http\Controllers\User\Api\TripApiController::class, 'reportDisruption'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.report');
        Route::delete('api/trips/{trip}', [\App\Http\Controllers\User\Api\TripApiController::class, 'destroy'])->middleware('module:trips')->whereNumber('trip')->name('api.trips.destroy');

        // Itinerary API (declared before the SPA catch-all)
        Route::get('api/list', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'index'])->middleware('module:claims')->name('api.index');
        Route::post('api/upload', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'store'])->middleware('module:claims')->name('api.store');
        Route::get('api/{itinerary}', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'show'])->middleware('module:claims')->whereNumber('itinerary')->name('api.show');
        Route::post('api/{itinerary}/reparse', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'reparse'])->middleware('module:claims')->whereNumber('itinerary')->name('api.reparse');
        Route::delete('api/{itinerary}', [\App\Http\Controllers\User\Api\ItineraryApiController::class, 'destroy'])->middleware('module:claims')->whereNumber('itinerary')->name('api.destroy');

        // Original file download
        Route::get('{itinerary}/file', [ItineraryController::class, 'file'])->whereNumber('itinerary')->name('file');

        // Vue SPA shell — handles /flight-disputes and any client-side route
        // (e.g. /flight-disputes/claims/new, /flight-disputes/claims/{hash}) on reload/deep-link.
        Route::get('{path?}', [ItineraryController::class, 'index'])->where('path', '.*')->name('index');
    });

    // Retired customer case pages: off by default via the module switches
    // (Settings -> Website -> Modules), so a bookmarked URL is not a way
    // back in - but an admin can reopen them without a code change.
    Route::middleware('module:cases')->group(function () {
        // ✅ CREATE must come BEFORE {case_reference_id}
        Route::get('/cases/create', [CaseController::class, 'createStep1'])
            ->name('cases.create');

        Route::get('/cases', [CaseController::class, 'index'])
            ->name('cases.index');

        Route::get('/cases/{case_reference_id}', [CaseController::class, 'show'])
            ->name('cases.show');
        Route::get('/cases/{case}/export', [CaseController::class, 'exportPdf'])->name('cases.export');
    });

    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('module:documents')->name('documents.index');

    Route::get('/api/institutions/search', [CaseController::class, 'searchInstitutions'])
        ->name('api.institutions.search');
    Route::get('/document/view/{attachment}', [DocumentController::class, 'showPublic'])
    ->name('evidence.view');
    Route::get('/document/download/{attachment}', [DocumentController::class, 'downloadSecure'])
    ->name('evidence.download')
    ->middleware('signed');

    // Letter templates - retired with the case-management module; the
    // Cases Email Templates module switch (off by default) reopens them.
    Route::middleware(['requires_subscription', 'module:case_templates'])->group(function () {
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
