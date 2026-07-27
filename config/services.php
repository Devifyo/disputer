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
        // The claims address shown to customers (site, emails). The
        // claims.unjamm.com subdomain routes to SendGrid Inbound Parse via
        // its own MX record, independent of the root domain's mailboxes.
        'claims_display' => env('CLAIMS_DISPLAY_ADDRESS', 'claims@claims.unjamm.com'),
        // Domain for per-claim reply-to addresses (claims+CLM-XXXX@...).
        // Must be the Inbound Parse host - it accepts any local part, so
        // airline replies come back tagged with the claim they belong to.
        'reply_domain'   => env('CLAIMS_REPLY_DOMAIN', 'claims.unjamm.com'),
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

    // Dropbox Sign (Essentials) - embedded e-signatures for claim
    // authorisation documents. Without an API key the built-in signature
    // pad is used instead.
    'dropbox_sign' => [
        'api_key'   => env('DROPBOX_SIGN_API_KEY'),
        'client_id' => env('DROPBOX_SIGN_CLIENT_ID'),
        'test_mode' => (bool) env('DROPBOX_SIGN_TEST_MODE', true),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // WISE_SANDBOX=true flips the WHOLE integration to Wise's sandbox -
    // separate URL and separate credentials, because sandbox is its own
    // Wise account (a live token is invalid there and vice versa).
    'wise' => [
        'sandbox'            => (bool) env('WISE_SANDBOX', false),
        'token'              => env('WISE_SANDBOX', false) ? env('WISE_SANDBOX_API_TOKEN') : env('WISE_API_TOKEN'),
        'profile_id'         => env('WISE_SANDBOX', false) ? env('WISE_SANDBOX_PROFILE_ID') : env('WISE_PROFILE_ID'),
        'base_url'           => env('WISE_SANDBOX', false)
            ? env('WISE_SANDBOX_BASE_URL', 'https://api.wise-sandbox.com')
            : env('WISE_BASE_URL', 'https://api.transferwise.com'),
        'webhook_public_key' => env('WISE_WEBHOOK_PUBLIC_KEY'),
        // RSA private key for SCA request signing (balance payments return a
        // 403 + x-2fa-approval challenge until the matching PUBLIC key is
        // registered in the Wise account's API settings).
        'sca_private_key'    => env('WISE_SCA_PRIVATE_KEY', storage_path('app/keys/wise-sca.pem')),
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
