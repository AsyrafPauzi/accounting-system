<?php

namespace App\Console\Commands;

use App\Services\Licensing\LicenseService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * PUBLISHER-side command. Mints a license key for a customer and
 * prints it to stdout so the support team can email it to the
 * buyer. Reads the private key from `APP_LICENSE_PRIVATE_KEY` —
 * this env var should ONLY be present on the SaaS side.
 *
 * Example:
 *   php artisan license:issue \
 *     --customer-id=acme-co \
 *     --customer-name="Acme Sdn Bhd" \
 *     --tier=self-hosted-enterprise \
 *     --max-users=50 \
 *     --features=mfa-required,advanced-reports \
 *     --expires=2027-06-01
 */
class LicenseIssue extends Command
{
    protected $signature = 'license:issue
        {--customer-id= : Opaque ID for the buyer (e.g. acme-co)}
        {--customer-name= : Human-readable name (Acme Sdn Bhd)}
        {--tier=self-hosted-standard : Plan tier slug}
        {--max-users=0 : Max active users (0 = unlimited)}
        {--features= : Comma-separated feature flags}
        {--expires= : YYYY-MM-DD expiry date (omit for perpetual)}
        {--save-as= : Optional path to write the key into (default: stdout only)}';

    protected $description = '[Publisher only] Mint a self-hosted license key.';

    public function handle(): int
    {
        $privateKey = (string) env('APP_LICENSE_PRIVATE_KEY', '');
        if ($privateKey === '') {
            $this->error('APP_LICENSE_PRIVATE_KEY is not configured. This command must run on the publisher side only.');
            return self::FAILURE;
        }

        $customerId   = (string) $this->option('customer-id');
        $customerName = (string) $this->option('customer-name');
        if ($customerId === '' || $customerName === '') {
            $this->error('--customer-id and --customer-name are required.');
            return self::FAILURE;
        }

        $features = array_filter(array_map('trim', explode(',', (string) $this->option('features'))));
        $expiresAt = null;
        if ($exp = $this->option('expires')) {
            $expiresAt = CarbonImmutable::parse((string) $exp)->endOfDay()->toIso8601String();
        }

        $claims = [
            'customer_id'   => $customerId,
            'customer_name' => $customerName,
            'plan_tier'     => (string) $this->option('tier'),
            'max_users'     => (int) $this->option('max-users'),
            'features'      => array_values($features),
            'expires_at'    => $expiresAt,
        ];

        $key = LicenseService::issue($claims, $privateKey);

        $this->newLine();
        $this->info('License key minted.');
        $this->newLine();
        $this->line($key);
        $this->newLine();
        $this->info('Customer should set APP_LICENSE_KEY in their .env to the value above.');

        if ($savePath = $this->option('save-as')) {
            file_put_contents((string) $savePath, $key.PHP_EOL);
            $this->info("Saved to {$savePath}");
        }

        return self::SUCCESS;
    }
}
