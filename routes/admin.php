<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
   Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // other admin routes...
});