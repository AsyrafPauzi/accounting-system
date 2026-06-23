<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_smoke_command_sends_representative_mailables(): void
    {
        Mail::fake();

        $this->artisan('mail:smoke', [
            '--to' => 'smoke@example.test',
            '--skip-notifications' => true,
        ])
            ->assertExitCode(0);

        Mail::assertSentCount(4);
    }
}
