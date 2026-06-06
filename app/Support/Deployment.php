<?php

namespace App\Support;

/**
 * Single source of truth for "what shape is this deployment?".
 *
 * Two modes only:
 *
 *   saas         — Multi-tenant SaaS. Stancl tenancy active, public
 *                  registration on, subscription billing on, Practice
 *                  console available, super-admin manages tenants.
 *
 *   self_hosted  — Single-tenant install run by a customer on their
 *                  own infra. Stancl tenancy still loads (because most
 *                  of the app's data layer assumes tenant DB
 *                  initialisation), but we point it at a single
 *                  "default" tenant and disable the multi-tenant UI:
 *                  no public registration, no Practice console, no
 *                  super-admin tenant management.
 *
 * Why we don't strip Stancl entirely: a huge fraction of the app
 * (every controller that touches `tenant_id`, every per-tenant
 * migration, every storage-prefix decision) depends on tenancy being
 * initialised. Faking a single-tenant install via a "default" tenant
 * is far less invasive and lets us keep ONE codebase that runs both
 * shapes.
 */
class Deployment
{
    public const DEFAULT_TENANT_ID = 'default';

    public static function mode(): string
    {
        return (string) config('deployment.mode', 'saas');
    }

    public static function isSaas(): bool
    {
        return self::mode() === 'saas';
    }

    public static function isSelfHosted(): bool
    {
        return self::mode() === 'self_hosted';
    }

    /**
     * Should the public sign-up routes be advertised + functional?
     * Self-hosted = no — the customer's admin invites their team.
     */
    public static function publicRegistrationEnabled(): bool
    {
        return self::isSaas();
    }

    /**
     * Should the multi-tenant SaaS surface (super-admin tenants page,
     * subscription billing) be available?
     *
     * Note: this is intentionally NOT used by the Practice console
     * anymore — see `practiceConsoleEnabled()` for the license-aware
     * gate. SaaS-only billing / public super-admin tenants list still
     * use this hard gate because they don't make sense on a
     * customer-owned install at all.
     */
    public static function saasFeaturesEnabled(): bool
    {
        return self::isSaas();
    }

    /**
     * The Practice (Accountant) console is available either:
     *   - on SaaS (always), or
     *   - on a self-hosted install whose license carries the
     *     `practice.console` feature flag (i.e. Enterprise tier).
     *
     * Wrapped in try/catch so a missing/invalid license never crashes
     * the request — we just fall back to "not enabled".
     */
    public static function practiceConsoleEnabled(): bool
    {
        if (self::isSaas()) {
            return true;
        }
        return self::licenseHasFeature('practice.console');
    }

    /**
     * Can this install create new tenants at runtime? SaaS always
     * yes (that's what registration is). Self-hosted only when the
     * license carries `tenants.create` (Enterprise tier). Standard
     * self-hosted is single-tenant by license — the "Add Client"
     * UI is hidden / aborts.
     */
    public static function multiTenantEnabled(): bool
    {
        if (self::isSaas()) {
            return true;
        }
        return self::licenseHasFeature('tenants.create');
    }

    /**
     * Maximum number of client tenants this self-hosted install may
     * create (the firm + N clients model).
     *
     * Returns null when the cap doesn't apply (SaaS, or no license,
     * or `max_tenants: 0` which by convention means "unlimited").
     * Returns a positive int otherwise. Callers that don't care
     * about plan/license source treat null as "no cap".
     */
    public static function licenseMaxTenants(): ?int
    {
        if (self::isSaas()) {
            return null;
        }
        $claims = self::licenseClaims();
        if (! $claims) return null;
        $n = $claims['max_tenants'] ?? null;
        if ($n === null || $n === '') return null;
        $n = (int) $n;
        // 0 is "unlimited" by license-schema convention; we surface
        // that as null so the rest of the codebase's "null = no cap"
        // idiom keeps working.
        return $n > 0 ? $n : null;
    }

    /** @return list<string> */
    public static function licenseFeatures(): array
    {
        if (self::isSaas()) return [];
        $claims = self::licenseClaims();
        return is_array($claims['features'] ?? null) ? array_values($claims['features']) : [];
    }

    private static function licenseHasFeature(string $name): bool
    {
        try {
            return app(\App\Services\Licensing\LicenseService::class)->hasFeature($name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private static function licenseClaims(): ?array
    {
        try {
            return app(\App\Services\Licensing\LicenseService::class)->claims();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
