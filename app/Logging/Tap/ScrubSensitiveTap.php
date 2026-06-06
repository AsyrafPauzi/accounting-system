<?php

namespace App\Logging\Tap;

use App\Logging\Processors\ScrubSensitive;
use Illuminate\Log\Logger;

/**
 * Laravel "tap" hook — invoked by every log channel that lists this class
 * under its `tap` config key. Pushes our redaction processor onto the
 * underlying Monolog logger so it fires for every record on that channel.
 */
class ScrubSensitiveTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        // pushProcessor is a stack — last-pushed runs first. We register at
        // the top so we redact before any downstream formatting/handlers
        // see the record.
        $monolog->pushProcessor(new ScrubSensitive());
    }
}
