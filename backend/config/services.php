<?php

/**
 * Third-party service configuration.
 *
 * This file centralizes credentials and endpoints for external integrations
 * used by the application.
 */

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

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect_uri' => env('DISCORD_REDIRECT_URI'),
        'scopes' => env('DISCORD_SCOPES', 'identify email'),
        'base_uri' => env('DISCORD_BASE_URI', 'https://discord.com'),
        'api_base_uri' => env('DISCORD_API_BASE_URI', 'https://discord.com/api'),
        'bot_secret' => env('DISCORD_BOT_SECRET'),
        'bot_internal_base_url' => env('DISCORD_BOT_INTERNAL_BASE_URL'),
    ],

];
