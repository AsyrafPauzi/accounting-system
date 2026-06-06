<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifies + interprets BukuCloud self-hosted license keys.
 *
 * Format
 * ------
 *
 *   <base64url(payload_json)>.<base64url(signature_bytes)>
 *
 * Signature is RSA-SHA256 (openssl) over the raw payload JSON. The
 * public key is shipped as part of the customer install via
 * `APP_LICENSE_PUBLIC_KEY` (a PEM-encoded RSA public key in `.env`).
 * The matching private key lives only on the SaaS publisher side and
 * is what `php artisan license:issue` uses to mint keys.
 *
 * Why not JWT? Because we don't want to drag in a JWT lib for the
 * customer-side runtime. The format above is intentionally minimal:
 * payload is just JSON, signature is raw bytes, both base64url'd.
 *
 * Payload schema (validated in `parseClaims()`):
 *   - license_id     : uuid the publisher uses to revoke
 *   - customer_id    : opaque (e.g. "acme-co")
 *   - customer_name  : human-readable
 *   - plan_tier      : 'self-hosted-standard' | 'self-hosted-enterprise'
 *   - max_users      : integer (0 = unlimited)
 *   - features[]     : string list of feature flags
 *   - issued_at      : ISO 8601
 *   - expires_at     : ISO 8601 (null = perpetual)
 *
 * Cache: results are memoised in the `license_status` cache key for
 * 60 minutes. Heartbeat / revocation logic invalidates this on every
 * tick so revocations propagate within the next request after a
 * heartbeat fetches them.
 */
class LicenseService
{
    public const CACHE_KEY = 'license_status';
    public const CACHE_TTL_SEC = 3600;

    /**
     * Top-level evaluation result. Returns one of:
     *   - ['status' => 'valid', 'claims' => [...]]
     *   - ['status' => 'missing']           — no key configured
     *   - ['status' => 'malformed']         — wrong format
     *   - ['status' => 'bad_signature']     — signature mismatch
     *   - ['status' => 'expired',  'claims' => [...]]
     *   - ['status' => 'revoked',  'claims' => [...]]
     *   - ['status' => 'unconfigured']      — public key missing
     *
     * Cached so we don't sign-check on every middleware run.
     */
    public function status(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SEC, fn () => $this->evaluate());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Convenience: is the install valid right now?
     */
    public function isValid(): bool
    {
        return ($this->status()['status'] ?? '') === 'valid';
    }

    /**
     * Decoded license claims (or null if invalid).
     * @return array<string, mixed>|null
     */
    public function claims(): ?array
    {
        $s = $this->status();
        return $s['claims'] ?? null;
    }

    /**
     * True if a feature flag is included in the license payload.
     * Treat missing claims as "no features", not "all features" —
     * defaults must be conservative.
     */
    public function hasFeature(string $name): bool
    {
        $claims = $this->claims();
        if (! $claims) return false;
        $features = $claims['features'] ?? [];
        return in_array($name, $features, true);
    }

    /**
     * Fully evaluate the configured license key. Run from `status()`,
     * but also callable directly (e.g. by the heartbeat command) to
     * bypass the cache.
     */
    public function evaluate(): array
    {
        $key       = (string) (config('deployment.license_key') ?? '');
        $publicKey = (string) (config('deployment.license_public_key') ?? '');

        if ($key === '')           return ['status' => 'missing'];
        if ($publicKey === '')     return ['status' => 'unconfigured'];

        $parts = explode('.', $key);
        if (count($parts) !== 2)   return ['status' => 'malformed'];

        $payloadB64   = $parts[0];
        $signatureB64 = $parts[1];

        $payloadJson = self::base64UrlDecode($payloadB64);
        $signature   = self::base64UrlDecode($signatureB64);
        if ($payloadJson === null || $signature === null) {
            return ['status' => 'malformed'];
        }

        $verified = openssl_verify($payloadJson, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return ['status' => 'bad_signature'];
        }

        $claims = json_decode($payloadJson, true);
        if (! is_array($claims)) {
            return ['status' => 'malformed'];
        }

        // Local revocation list — heartbeat command writes this when
        // it learns the publisher revoked our key.
        $revokedIds = (array) Cache::get('license_revoked_ids', []);
        if (! empty($claims['license_id']) && in_array($claims['license_id'], $revokedIds, true)) {
            return ['status' => 'revoked', 'claims' => $claims];
        }

        if (! empty($claims['expires_at'])) {
            $expires = CarbonImmutable::parse($claims['expires_at']);
            if ($expires->isPast()) {
                return ['status' => 'expired', 'claims' => $claims];
            }
        }

        return ['status' => 'valid', 'claims' => $claims];
    }

    /**
     * Mints a license key. Only used on the publisher (SaaS) side
     * — never run on a customer install. The private key is read
     * from `APP_LICENSE_PRIVATE_KEY` (PEM contents) so we never put
     * a key file on disk.
     *
     * @param array $claims  Required: customer_id, customer_name, plan_tier
     */
    public static function issue(array $claims, string $privateKeyPem): string
    {
        // Fill in audit fields the publisher always wants.
        $claims = array_merge([
            'license_id' => (string) \Illuminate\Support\Str::uuid(),
            'issued_at'  => CarbonImmutable::now()->toIso8601String(),
            'features'   => [],
            'max_users'  => 0,
        ], $claims);

        $payloadJson = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode license payload.');
        }

        $signature = '';
        $ok = openssl_sign($payloadJson, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            $err = '';
            while (($e = openssl_error_string()) !== false) { $err .= $e."\n"; }
            throw new \RuntimeException('License signing failed: '.$err);
        }

        return self::base64UrlEncode($payloadJson).'.'.self::base64UrlEncode($signature);
    }

    /**
     * Adds the given license_id to the revocation cache so subsequent
     * `evaluate()` calls return 'revoked'. Called by the heartbeat
     * command when the publisher returns a revoked-list update.
     */
    public function recordRevocations(array $licenseIds): void
    {
        $current = (array) Cache::get('license_revoked_ids', []);
        Cache::forever('license_revoked_ids', array_values(array_unique(array_merge($current, $licenseIds))));
        $this->flush();
        Log::info('License revocation list updated.', ['ids' => $licenseIds]);
    }

    private static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $b64): ?string
    {
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $bin = base64_decode($b64, true);
        return $bin === false ? null : $bin;
    }
}
