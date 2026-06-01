<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Mode
    |--------------------------------------------------------------------------
    |
    | Controls whether the application runs as multi-tenant SaaS (default) or
    | as a single-tenant self-hosted install. Changes which features appear:
    |
    | - 'saas'        : multi-tenant. Brand locked to BukuCloud. Subscription
    |                   billing enabled. Public registration enabled.
    | - 'self_hosted' : single-tenant. Super-admin can override brand colors,
    |                   logo, favicon, product name. License-key gated.
    |
    */
    'mode' => env('APP_DEPLOYMENT_MODE', 'saas'),

    /*
    |--------------------------------------------------------------------------
    | Self-hosted license key
    |--------------------------------------------------------------------------
    |
    | Only consulted when mode='self_hosted'. JWT signed by the BukuCloud
    | private key, verified locally against the bundled public key. Encodes
    | tenant name, plan tier, expiry, and seat count. Issued via
    | `php artisan license:issue` on the publisher side.
    |
    */
    'license_key'        => env('APP_LICENSE_KEY'),
    'license_public_key' => env('APP_LICENSE_PUBLIC_KEY'),
];
