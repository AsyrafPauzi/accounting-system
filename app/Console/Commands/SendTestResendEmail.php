<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Resend;
use Throwable;

/**
 * Smoke test for the Resend transport. Sends a single hardcoded test
 * email straight through the Resend HTTP API (bypassing Laravel's Mail
 * facade) so you can confirm RESEND_API_KEY is correct and the sender
 * domain is verified before wiring Resend up to a real Mailable.
 *
 * Reads the API key from RESEND_API_KEY (config/services.php → resend.key)
 * — never hardcode the key here. Uses resend/resend-php directly because
 * that is the package this project ships (Laravel 12 has the resend Mail
 * transport built in, so the Laravel wrapper is not installed).
 */
class SendTestResendEmail extends Command
{
    protected $signature = 'mail:test-resend
                            {--to=asyraf.pauzi@hirix.ai : Recipient address}
                            {--from=onboarding@resend.dev : Sender address (must be on a Resend-verified domain)}';

    protected $description = 'Send a one-off "Hello World" test email via the Resend API.';

    public function handle(): int
    {
        $apiKey = (string) config('services.resend.key');
        if ($apiKey === '') {
            $this->error('RESEND_API_KEY is not set. Add it to your .env and re-run.');
            return self::FAILURE;
        }

        $to = (string) $this->option('to');
        $from = (string) $this->option('from');

        try {
            $client = Resend::client($apiKey);
            $response = $client->emails->send([
                'from' => $from,
                'to' => $to,
                'subject' => 'Hello World',
                'html' => '<p>Congrats on sending your <strong>first email</strong>!</p>',
            ]);
        } catch (Throwable $e) {
            $this->error('Resend rejected the request: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Sent. Resend message id: {$response->id}");
        return self::SUCCESS;
    }
}
