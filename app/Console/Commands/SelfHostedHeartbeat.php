<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Licensing\LicenseService;
use App\Support\Deployment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CUSTOMER-side daily heartbeat. Runs from the Laravel scheduler
 * (kernel registration in routes/console.php) when the deployment is
 * self-hosted. Pings the publisher with usage stats, picks up any
 * revocations, and records the timestamp so the lockdown middleware
 * knows whether we're "online" or "stranded".
 *
 * Network-fault-tolerant: any HTTP failure is logged but does not
 * fail the command. Lockdown is decided by the *age* of the last
 * successful heartbeat, not by the success of any individual ping.
 */
class SelfHostedHeartbeat extends Command
{
    protected $signature = 'self-hosted:heartbeat {--dry-run : Build the payload but do not send}';
    protected $description = 'Send a self-hosted install heartbeat to the publisher.';

    public const LAST_OK_KEY = 'self_hosted_last_heartbeat_at';

    public function handle(LicenseService $svc): int
    {
        if (! Deployment::isSelfHosted()) {
            $this->info('Skipping: not in self_hosted mode.');
            return self::SUCCESS;
        }

        $endpoint = (string) (config('deployment.heartbeat_endpoint') ?? 'https://api.bukucloud.io/api/self-hosted/heartbeat');
        $licenseKey = (string) (config('deployment.license_key') ?? '');

        if ($licenseKey === '') {
            $this->warn('No license key configured (APP_LICENSE_KEY).');
            return self::SUCCESS;
        }

        $payload = [
            'license_key' => $licenseKey,
            'version'     => (string) config('app.version', 'unknown'),
            'user_count'  => User::count(),
            'payload'     => [
                'php'     => PHP_VERSION,
                'laravel' => app()->version(),
                'mode'    => Deployment::mode(),
            ],
        ];

        if ($this->option('dry-run')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(10)->retry(2, 250)->asJson()->post($endpoint, $payload);
        } catch (\Throwable $e) {
            // Don't crash the schedule on connectivity hiccups —
            // lockdown is a function of "last heartbeat older than
            // grace period", not of individual ping failures.
            Log::warning('Self-hosted heartbeat request failed', ['err' => $e->getMessage()]);
            $this->error('Heartbeat request failed: '.$e->getMessage());
            return self::SUCCESS;
        }

        if ($response->successful()) {
            Cache::forever(self::LAST_OK_KEY, now()->toIso8601String());
            $body = $response->json();
            if (! empty($body['revoked']) && is_array($body['revoked'])) {
                $svc->recordRevocations($body['revoked']);
            }
            // Persist the publisher's "latest available" advertisement
            // locally so the in-app banner can read it without re-doing
            // a network round-trip on every page render.
            if (! empty($body['latest_version'])) {
                \App\Models\PlatformSetting::set('latest_available_version', (string) $body['latest_version']);
            }
            if (array_key_exists('update_notes', $body)) {
                \App\Models\PlatformSetting::set('latest_update_notes', $body['update_notes']);
            }
            if (array_key_exists('update_url', $body)) {
                \App\Models\PlatformSetting::set('latest_update_url', $body['update_url']);
            }
            $this->info('Heartbeat OK.');
            if (! empty($body['latest_version'])) {
                $this->line('Latest available version: '.$body['latest_version']);
            }
        } else {
            Log::warning('Self-hosted heartbeat non-2xx', ['status' => $response->status(), 'body' => $response->body()]);
            $this->warn('Heartbeat returned HTTP '.$response->status().'.');
        }

        return self::SUCCESS;
    }
}
