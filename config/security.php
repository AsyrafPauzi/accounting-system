<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spam-bot Guard
    |--------------------------------------------------------------------------
    |
    | Honeypot + time-challenge middleware applied to guest auth POST
    | endpoints. See App\Http\Middleware\SpamBotGuard.
    |
    | enabled — master switch. Default ON. Disable only when running
    |           feature tests that POST forms without rendering them
    |           first (phpunit.xml sets this to false for that reason).
    |
    */

    'spambot_guard' => [
        'enabled' => env('SPAMBOT_GUARD_ENABLED', true),
    ],

];
