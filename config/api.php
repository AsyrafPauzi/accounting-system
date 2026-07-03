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

    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | Max requests per minute per API key on /api/v1/*.
    |
    */
    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 600),
];
