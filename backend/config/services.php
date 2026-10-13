<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // ID tokens sent to the API must be issued for one of these client IDs
        // (comma-separated). Android requests tokens for the web client ID.
        'allowed_client_ids' => array_values(array_filter(explode(
            ',',
            (string) (env('GOOGLE_ALLOWED_CLIENT_IDS') ?: env('GOOGLE_CLIENT_ID')),
        ))),
    ],

    // Optional: AI product description suggestions. Without a key the feature
    // answers "unavailable". Keep separate, restricted keys for local and production.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Tried once, in place of `model`, only when `model` answers 503 (overloaded).
        // Comma-separated; only the first is used. Empty turns the fallback off.
        'fallback_models' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('GEMINI_FALLBACK_MODELS', 'gemini-3.1-flash-lite'),
        )))),
        // Calls to Gemini per day for the whole platform, as a ceiling on the bill.
        'daily_limit' => (int) env('GEMINI_DAILY_LIMIT', 500),
    ],

];
