<?php

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SentrySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sentry_smoke_command_succeeds_without_dsn(): void
    {
        config(['sentry.dsn' => null]);

        $exit = Artisan::call('sentry:smoke');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('not configured', Artisan::output());
    }

    public function test_sentry_smoke_command_dispatches_when_dsn_set(): void
    {
        config(['sentry.dsn' => 'https://examplePublicKey@o0.ingest.sentry.io/0']);

        $exit = Artisan::call('sentry:smoke');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Test event dispatched', Artisan::output());
    }
}
