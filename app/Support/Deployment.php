<?php

namespace App\Support;

class Deployment
{
    public static function mode(): string
    {
        return (string) config('deployment.mode', 'saas');
    }

    public static function isSaas(): bool
    {
        return self::mode() === 'saas';
    }

    public static function isSelfHosted(): bool
    {
        return self::mode() === 'self_hosted';
    }
}
