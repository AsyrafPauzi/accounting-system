<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LHDN MyInvois API Configuration
    |--------------------------------------------------------------------------
    |
    | To obtain credentials:
    |  1. Register your company at https://mytax.hasil.gov.my/
    |  2. Log in and navigate to MyInvois > API Access
    |  3. Create a new application to receive a Client ID and Client Secret
    |  4. For sandbox testing, use the preprod portal:
    |     https://preprod.myinvois.hasil.gov.my/
    |
    | Set these in your .env file:
    |   LHDN_ENV=sandbox          # or "production"
    |   LHDN_CLIENT_ID=your-id
    |   LHDN_CLIENT_SECRET=your-secret
    |
    */

    'env' => env('LHDN_ENV', 'sandbox'),

    'client_id'     => env('LHDN_CLIENT_ID'),
    'client_secret' => env('LHDN_CLIENT_SECRET'),

    'sandbox' => [
        'base_url'  => 'https://preprod-api.myinvois.hasil.gov.my',
        'token_url' => 'https://preprod-api.myinvois.hasil.gov.my/connect/token',
    ],

    'production' => [
        'base_url'  => 'https://api.myinvois.hasil.gov.my',
        'token_url' => 'https://api.myinvois.hasil.gov.my/connect/token',
    ],
];
