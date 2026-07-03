<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API signature verification
    |--------------------------------------------------------------------------
    |
    | Mutating /api/v1 requests require HMAC-SHA256 headers. Requests
    | outside this skew window are rejected.
    |
    */
    'signature_skew_seconds' => env('API_SIGNATURE_SKEW', 300),
];
