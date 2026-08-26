<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds tenant_id and user_id to every log record when available.
 * Used by the JSON log channel and any channel that taps TenantContextTap.
 */
class TenantContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        if (function_exists('tenancy') && tenancy()->initialized) {
            $extra['tenant_id'] = tenant('id');
        }

        if ($user = auth()->user()) {
            $extra['user_id'] = $user->getAuthIdentifier();
        }

        return $record->with(extra: $extra);
    }
}
