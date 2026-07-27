<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\{SendGridInboundController, SendGridClaimsInboundController, SendGridEventController, DropboxSignWebhookController};
use App\Http\Controllers\Webhook\StripeWebhookController;
use App\Http\Controllers\Webhook\WiseWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/sendgrid/inbound', [SendGridInboundController::class, 'handle']);
Route::post('/webhooks/sendgrid/claims-inbound', [SendGridClaimsInboundController::class, 'handle']);
Route::post('/webhooks/sendgrid/events', [SendGridEventController::class, 'handle']);
Route::post('/webhooks/dropbox-sign', [DropboxSignWebhookController::class, 'handle']);
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/wise', [WiseWebhookController::class, 'handle']);