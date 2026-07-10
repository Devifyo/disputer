<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Email-forwarding fallback (handled via SendGrid Inbound Parse).
    // Leave CLAIMS_INBOUND_ADDRESS unset when the Parse host is dedicated to
    // claims (e.g. claims.unjamm.com) — all mail there is treated as a claim.
    // Set it (e.g. claims@unjamm.com) only when the host is a shared domain,
    // so that just that address is processed and other mail is ignored.
    'inbound' => [
        'claims_address' => env('CLAIMS_INBOUND_ADDRESS'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'flightaware' => [
        'api_key'  => env('FLIGHTAWARE_API_KEY'),
        'base_url' => env('FLIGHTAWARE_BASE_URL', 'https://aeroapi.flightaware.com/aeroapi'),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id'     => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect'      => env('APPLE_REDIRECT_URI'),
    ],

];
