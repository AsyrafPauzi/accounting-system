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

    /*
    |--------------------------------------------------------------------------
    | Toyyibpay (SaaS billing only)
    |--------------------------------------------------------------------------
    |
    | These are the publisher's payment-gateway credentials. They are
    | only consulted by routes guarded by `saas.only` (subscription
    | checkout / webhook). Self-hosted installs MUST NOT carry these
    | secrets — the customer is paying via the license, not via our
    | gateway. We zero them out at config load when in self-hosted
    | mode so a curious operator running `php artisan config:show` /
    | `phpinfo()` / a config dump can't extract them.
    |
    */
    'toyyibpay' => env('APP_DEPLOYMENT_MODE', 'saas') === 'self_hosted'
        ? ['secret_key' => null, 'category_code' => null, 'env' => 'disabled']
        : [
            'secret_key'    => env('TOYYIBPAY_SECRET_KEY'),
            'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
            'env'           => env('TOYYIBPAY_ENV', 'sandbox'),
        ],

    'commercepay' => [
        'staging_url'    => env('COMMERCEPAY_STAGING_URL', 'https://staging-payments.commerce.asia'),
        'production_url' => env('COMMERCEPAY_PRODUCTION_URL', 'https://payments.commerce.asia'),
    ],

];
