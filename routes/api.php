<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\{SendGridInboundController, SendGridEventController};

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/sendgrid/inbound', [SendGridInboundController::class, 'handle']);
Route::post('/webhooks/sendgrid/events', [SendGridEventController::class, 'handle']);