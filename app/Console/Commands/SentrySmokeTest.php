<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SentrySmokeTest extends Command
{
    protected $signature = 'sentry:smoke';

    protected $description = 'Send a test event to Sentry when DSN is configured';

    public function handle(): int
    {
        if (! filled(config('sentry.dsn'))) {
            $this->warn('SENTRY_DSN is not configured — nothing sent.');

            return self::SUCCESS;
        }

        \Sentry\captureMessage('BukuCloud Sentry smoke test from artisan sentry:smoke');

        $this->info('Test event dispatched to Sentry.');

        return self::SUCCESS;
    }
}
