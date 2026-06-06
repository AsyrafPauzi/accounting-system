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

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    |
    | Where the customer's daily heartbeat goes, and how long we'll let an
    | install run without one before locking it to the "license invalid"
    | page. Defaults are sized for "the customer was on holiday for two
    | weeks", not "we're enforcing daily phone-home".
    */
    'heartbeat_endpoint'    => env('APP_HEARTBEAT_ENDPOINT', 'https://api.bukucloud.io/api/self-hosted/heartbeat'),
    'heartbeat_grace_days'  => (int) env('APP_HEARTBEAT_GRACE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Internal admin host (optional)
    |--------------------------------------------------------------------------
    |
    | When set (e.g. `internal.bukucloud.com`), the platform-level admin
    | UI — license manager, patch broadcaster, tenant management — is
    | only reachable on that exact host. Requests for /admin/* on the
    | main `bukucloud.com` host return 404.
    |
    | When null (the default for local dev), the admin UI works on
    | whatever host the request comes in on, so `php artisan serve`
    | doesn't require DNS gymnastics.
    |
    | Production setup:
    |   - DNS A-record: internal.bukucloud.com → your app server
    |   - Nginx server_name: internal.bukucloud.com
    |   - Same Laravel app, different vhost
    |
    */
    'internal_admin_host'   => env('INTERNAL_ADMIN_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Vendor / renewal contact
    |--------------------------------------------------------------------------
    |
    | Surfaced on the self-hosted "Plan & Usage" page so customers know
    | who to contact for license renewal, seat expansion, or support.
    | A reseller / SI partner can override these in their own .env
    | without forking the Inertia page.
    |
    | `vendor_name`          → display name shown in the renewal block.
    | `vendor_contact_email` → mailto: target. Falls back to MAIL_FROM_ADDRESS.
    | `vendor_contact_url`   → optional billing-portal / contact page link.
    |
    */
    'vendor_name'          => env('SELFHOSTED_VENDOR_NAME', 'BukuCloud'),
    'vendor_contact_email' => env('SELFHOSTED_VENDOR_EMAIL'),
    'vendor_contact_url'   => env('SELFHOSTED_VENDOR_URL'),
];
