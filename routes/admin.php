<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentReceiptController;
use App\Livewire\Admin\Users\Index as UserIndex;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Institutions\Index as InstitutionIndex;
use App\Livewire\Admin\Settings\Index as AdminSettings;
use App\Livewire\Admin\Templates\Index as AdminTemplates;
use App\Livewire\Admin\SuccessStories\Index as SuccessStoriesIndex;
use App\Livewire\Admin\Plans\Index as AdminPlans;
use App\Livewire\Admin\Support\Index as SupportIndex;
use App\Livewire\Admin\TripReviews\Index as TripReviewsIndex;
use App\Livewire\Admin\FlightClaims\Passengers as FlightClaimsPassengers;
use App\Livewire\Admin\FlightClaims\Trips as FlightClaimsTrips;
use App\Livewire\Admin\FlightClaims\Claims as FlightClaimsClaims;
use App\Livewire\Admin\FlightClaims\ClaimDetail as FlightClaimsClaimDetail;
use App\Livewire\Admin\FlightClaims\Lifecycle as FlightClaimsLifecycle;
use App\Livewire\Admin\FlightClaims\Airlines as FlightClaimsAirlines;
use App\Livewire\Admin\FlightClaims\ClaimTemplates as FlightClaimsTemplates;
use App\Livewire\Admin\FlightClaims\Payments as FlightClaimsPayments;
use App\Livewire\Admin\FlightClaims\Subscriptions as FlightClaimsSubscriptions;
use App\Livewire\Admin\CmsPages\Index as CmsPagesIndex;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role_access:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', UserIndex::class)->name('users.index');
    Route::get('/institutions', InstitutionIndex::class)->middleware('module:institutions')->name('institutions.index');
    Route::get('/categories', CategoriesIndex::class)->middleware('module:categories')->name('categories.index');
    Route::get('/templates', AdminTemplates::class)->middleware('module:email_templates')->name('templates.index');
    Route::get('/settings', AdminSettings::class)->name('settings.index');
    Route::get('/success-stories', SuccessStoriesIndex::class)->middleware('module:success_stories')->name('success-stories.index');
    Route::get('/plans', AdminPlans::class)->middleware('module:plans')->name('plans.index');
    Route::get('/impersonate-case/{case}', [DashboardController::class, 'impersonateAndViewCase'])->name('impersonate.case');
    Route::get('/support', SupportIndex::class)->name('support.index');
    // Module toggles (Settings -> Modules): a switched-off module refuses
    // here, not just in the nav.
    Route::get('/trip-reviews', TripReviewsIndex::class)->middleware('module:trip_reviews')->name('trip-reviews.index');
    Route::get('/flight-claims/trips', FlightClaimsTrips::class)->middleware('module:trips')->name('flight-claims.trips');
    Route::get('/flight-claims/claims', FlightClaimsClaims::class)->middleware('module:claims')->name('flight-claims.claims');
    Route::get('/flight-claims/passengers', FlightClaimsPassengers::class)->middleware('module:passengers')->name('flight-claims.passengers');
    Route::get('/flight-claims/lifecycle', FlightClaimsLifecycle::class)->middleware('module:lifecycle')->name('flight-claims.lifecycle');
    Route::get('/flight-claims/airlines', FlightClaimsAirlines::class)->middleware('module:airlines')->name('flight-claims.airlines');
    Route::get('/flight-claims/templates', FlightClaimsTemplates::class)->middleware('module:claim_templates')->name('flight-claims.templates');
    Route::get('/flight-claims/subscriptions', FlightClaimsSubscriptions::class)->middleware('module:subscriptions')->name('flight-claims.subscriptions');
    Route::get('/flight-claims/payments', FlightClaimsPayments::class)->middleware('module:payments')->name('flight-claims.payments');
    Route::get('/flight-claims/payments/{payment}/receipt', PaymentReceiptController::class)->middleware('module:payments')->name('flight-claims.payments.receipt');
    Route::get('/flight-claims/claims/{claim}', FlightClaimsClaimDetail::class)->whereNumber('claim')->middleware('module:claims')->name('flight-claims.claims.show');
    Route::get('/flight-claims/claims/{claim}/document/{key}', function (\App\Models\Claim $claim, string $key) {
        $path = $claim->documentPath($key);
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path);
    })->whereNumber('claim')->name('flight-claims.claims.document');
    Route::get('/trip-reviews/{trip}/document/{index}', function (\App\Models\Trip $trip, int $index) {
        $doc = $trip->report_details['documents'][$index] ?? null;
        abort_unless($doc && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc['path']), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($doc['path'], $doc['name'] ?? null);
    })->whereNumber('trip')->whereNumber('index')->name('trip-reviews.document');
    Route::get('/cms-pages', CmsPagesIndex::class)->name('cms-pages.index');
});

    // 1. Leave Impersonation Route
    Route::get('/admin/leave-impersonation', function () {
        app('impersonate')->leave(); 
        return redirect()->route('admin.users.index')->with('success', 'Welcome back, Admin!');
        
    })->middleware('auth')->name('admin.leave.impersonation');