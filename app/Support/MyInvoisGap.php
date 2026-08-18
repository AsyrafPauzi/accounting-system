<?php

namespace App\Support;

final class MyInvoisGap
{
    public static function myinvoisGapReason(?string $uuid, ?string $status): ?string
    {
        if (blank($uuid)) {
            return 'Not submitted';
        }

        $status = strtolower(trim((string) $status));

        return in_array($status, ['pending', 'rejected', 'invalid'], true)
            ? $status
            : null;
    }
}
