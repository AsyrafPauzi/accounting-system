<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registered OAuth-style API clients
    |--------------------------------------------------------------------------
    |
    | Each entry here represents one external partner who can drive the
    | "Connect to BukuCloud" handshake on behalf of a BukuCloud tenant.
    | Today only Fin Persona is registered. To add another partner:
    |
    |   1. Add another entry here with a unique `client_id` slug.
    |   2. Set the matching env vars on every environment.
    |   3. Restart so config:cache picks up the new client.
    |
    | The client_secret is never sent to the browser; only the partner's
    | backend ever holds it (used in the /api/oauth/token exchange).
    |
    | redirect_uris is an allow-list. We refuse to mint a code unless
    | the requested redirect_uri matches one of these byte-for-byte.
    | Wildcards are deliberately unsupported — each environment of the
    | partner (dev, staging, prod) gets its own entry.
    |
    */

    'clients' => [
        'finpersona' => [
            'name' => 'Fin Persona',
            'description' => 'Personal finance app — read-only access to transactions, invoices, bills, customers, and suppliers.',
            'client_secret' => env('FINPERSONA_CLIENT_SECRET'),
            'redirect_uris' => array_filter([
                env('FINPERSONA_REDIRECT_URI'),
                env('FINPERSONA_REDIRECT_URI_DEV'),
            ]),
            'read_only' => true,
            'scopes' => [
                'transactions' => 'Bank transactions (read-only)',
                'invoices'     => 'Customer invoices (read-only)',
                'bills'        => 'Supplier bills (read-only)',
                'customers'    => 'Customer directory (read-only)',
                'suppliers'    => 'Supplier directory (read-only)',
            ],
        ],

        // Tenant-generated API keys from Settings → Integrations.
        // No OAuth, no partner env vars. User copies pk_live_* into Fin Persona.
        'direct' => [
            'name' => 'Direct API access',
            'description' => 'Read-only API key for Fin Persona and similar apps.',
            'read_only' => true,
            'scopes' => [
                'transactions' => 'Bank transactions (read-only)',
                'invoices'     => 'Customer invoices (read-only)',
                'bills'        => 'Supplier bills (read-only)',
                'customers'    => 'Customer directory (read-only)',
                'suppliers'    => 'Supplier directory (read-only)',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature verification
    |--------------------------------------------------------------------------
    |
    | Mutating API requests (POST/PUT/DELETE) must carry an
    | X-BukuCloud-Signature header containing
    |
    |   hmac_sha256("$timestamp.$method.$uri.$body", $signing_key)
    |
    | encoded as hex. The X-BukuCloud-Timestamp header carries the
    | unix-millis at which the partner signed; we reject requests whose
    | timestamp is more than `signature_skew_seconds` away from server
    | time. This keeps a leaked signature unusable after the window
    | (replay defence).
    |
    */

    'signature_skew_seconds' => env('OAUTH_SIGNATURE_SKEW', 300), // 5 min
];
