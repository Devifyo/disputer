<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Livewire\Admin\Users\Index as UserIndex;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Institutions\Index as InstitutionIndex;
use App\Livewire\Admin\Settings\Index as AdminSettings;
use App\Livewire\Admin\Templates\Index as AdminTemplates;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', UserIndex::class)->name('users.index');
    Route::get('/institutions', InstitutionIndex::class)->name('institutions.index');
    Route::get('/categories', CategoriesIndex::class)->name('categories.index');
    Route::get('/templates', AdminTemplates::class)->name('templates.index');
    Route::get('/settings', AdminSettings::class)->name('settings.index');
    Route::get('/impersonate-case/{case}', [DashboardController::class, 'impersonateAndViewCase'])->name('impersonate.case');
});


    // 1. Leave Impersonation Route
    Route::get('/admin/leave-impersonation', function () {
        app('impersonate')->leave(); 
        return redirect()->route('admin.users.index')->with('success', 'Welcome back, Admin!');
        
    })->middleware('auth')->name('admin.leave.impersonation');