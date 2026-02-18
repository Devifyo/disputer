<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Livewire\Admin\Users\Index as UserIndex;
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', UserIndex::class)->name('users.index');
    });


    // 1. Leave Impersonation Route
    Route::get('/admin/leave-impersonation', function () {
        app('impersonate')->leave(); 
        return redirect()->route('admin.users.index')->with('success', 'Welcome back, Admin!');
        
    })->middleware('auth')->name('admin.leave.impersonation');