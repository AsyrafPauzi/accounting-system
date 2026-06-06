<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate the RSA keypair the publisher uses to sign self-hosted
 * licenses (private) and that customer installs use to verify them
 * (public). Writes both keys into the project `.env` so the issue
 * UI / artisan commands work immediately, with the option of
 * also dumping them as files for distribution.
 *
 *   php artisan license:keygen                # writes to .env
 *   php artisan license:keygen --dump=storage # also writes pem files
 *
 * Re-running BLOWS AWAY the old keypair unless --force is omitted —
 * we refuse to overwrite an existing key without confirmation, since
 * doing so invalidates every license in the field.
 */
class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen
        {--bits=2048 : RSA key size in bits (2048 minimum recommended)}
        {--dump= : Optional directory to also write license_private.pem / license_public.pem}
        {--force : Overwrite existing APP_LICENSE_PRIVATE_KEY in .env without confirming}';

    protected $description = 'Generate the RSA keypair used to sign self-hosted licenses';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->error('.env file not found at '.$envPath);
            return self::FAILURE;
        }

        $bits = max(2048, (int) $this->option('bits'));
        $env = File::get($envPath);

        if (preg_match('/^APP_LICENSE_PRIVATE_KEY="?[^"\n]+/m', $env) && ! $this->option('force')) {
            if (! $this->confirm('APP_LICENSE_PRIVATE_KEY is already set in .env. Overwriting will invalidate every license already issued. Continue?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $this->line("Generating {$bits}-bit RSA keypair…");

        $res = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            $this->error('openssl_pkey_new failed: '.openssl_error_string());
            return self::FAILURE;
        }

        if (! openssl_pkey_export($res, $privatePem)) {
            $this->error('openssl_pkey_export failed: '.openssl_error_string());
            return self::FAILURE;
        }
        $details = openssl_pkey_get_details($res);
        $publicPem = $details['key'] ?? null;
        if (! $publicPem) {
            $this->error('Could not read public key from keypair.');
            return self::FAILURE;
        }

        // Write to .env. We single-line PEM with literal \n inside
        // double-quoted env values so vlucas/phpdotenv decodes them.
        $privateOneLine = '"'.str_replace("\n", '\\n', trim($privatePem)).'"';
        $publicOneLine  = '"'.str_replace("\n", '\\n', trim($publicPem)).'"';

        $env = $this->upsertEnv($env, 'APP_LICENSE_PRIVATE_KEY', $privateOneLine);
        $env = $this->upsertEnv($env, 'APP_LICENSE_PUBLIC_KEY',  $publicOneLine);

        File::put($envPath, $env);
        $this->info('Wrote APP_LICENSE_PRIVATE_KEY and APP_LICENSE_PUBLIC_KEY to .env.');

        if ($dump = $this->option('dump')) {
            if (! is_dir($dump) && ! mkdir($dump, 0755, true) && ! is_dir($dump)) {
                $this->error('Could not create dump directory '.$dump);
                return self::FAILURE;
            }
            File::put(rtrim($dump, '/').'/license_private.pem', $privatePem);
            File::put(rtrim($dump, '/').'/license_public.pem', $publicPem);
            chmod(rtrim($dump, '/').'/license_private.pem', 0600);
            $this->info('Also wrote license_private.pem and license_public.pem to '.$dump);
        }

        // Tell Laravel to reload its cached config so the new value is
        // visible without a manual `php artisan config:clear`.
        $this->call('config:clear');

        $this->newLine();
        $this->line('<comment>Next steps:</comment>');
        $this->line('  1. Restart `php artisan serve` so it sees the new env vars.');
        $this->line('  2. Visit /admin/self-hosted → Issue licence works now.');
        $this->line('  3. Distribute APP_LICENSE_PUBLIC_KEY to your customer self-hosted .env files.');

        return self::SUCCESS;
    }

    private function upsertEnv(string $env, string $key, string $value): string
    {
        $line = $key.'='.$value;
        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $env)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $env, 1);
        }
        return rtrim($env, "\n")."\n".$line."\n";
    }
}
