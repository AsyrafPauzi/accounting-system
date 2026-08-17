<?php

return [
    /*
    | Dry-run submits a local UUID without calling LHDN — used in tests
    | and local books that have no intermediary credentials yet.
    | Set MYINVOIS_MODE=live once client id/secret are stored.
    */
    'mode' => env('MYINVOIS_MODE', 'dry-run'),

    'preprod_url' => env('MYINVOIS_PREPROD_URL', 'https://preprod-api.myinvois.hasil.gov.my'),
    'prod_url' => env('MYINVOIS_PROD_URL', 'https://api.myinvois.hasil.gov.my'),
    'environment' => env('MYINVOIS_ENV', 'preprod'), // preprod | production

    'qr_base' => env('MYINVOIS_QR_BASE', 'https://myinvois.hasil.gov.my'),
];
