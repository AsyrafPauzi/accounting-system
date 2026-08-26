<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class NotifyFailedJob
{
    public function handle(JobFailed $event): void
    {
        $context = [
            'connection' => $event->connectionName,
            'queue'      => $event->job->getQueue(),
            'job'        => $event->job->resolveName(),
            'exception'  => $event->exception->getMessage(),
        ];

        if (function_exists('tenancy') && tenancy()->initialized) {
            $context['tenant_id'] = tenant('id');
        }

        Log::channel('json')->error('Queue job failed', $context);

        if (filled(config('sentry.dsn'))) {
            \Sentry\captureException($event->exception);
        }
    }
}
