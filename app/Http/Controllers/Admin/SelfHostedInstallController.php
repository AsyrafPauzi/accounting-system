<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SelfHostedInstall;
use App\Services\Licensing\LicenseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin (publisher / SaaS) UI for managing self-hosted installs:
 *
 *   - List every install we know about, with health status (last
 *     heartbeat age, version, user count, revoked-or-not).
 *   - Issue a brand-new license key for a customer (writes to
 *     `self_hosted_installs` and prints the key). The customer pastes
 *     it into their .env / install wizard.
 *   - Revoke an existing license. Next heartbeat from that install
 *     receives `revoked: [<id>]` and the install locks itself.
 *
 * Permission gate: `admin.tenants` — same role that already has
 * platform-operator privilege. No new permission rolled out so we
 * can avoid touching every existing super-admin's role assignment.
 */
class SelfHostedInstallController extends Controller
{
    public function index(Request $request): Response
    {
        $installs = SelfHostedInstall::orderByDesc('latest_heartbeat_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (SelfHostedInstall $i) => [
                'id'                  => $i->id,
                'license_id'          => $i->license_id,
                'customer_id'         => $i->customer_id,
                'customer_name'       => $i->customer_name,
                'plan_tier'           => $i->plan_tier,
                'max_users'           => $i->max_users,
                'max_tenants'         => $i->max_tenants,
                'features'            => $i->features ?? [],
                'expires_at'          => $i->expires_at?->toIso8601String(),
                'issued_at'           => $i->issued_at?->toIso8601String(),
                'latest_version'      => $i->latest_version,
                'latest_user_count'   => $i->latest_user_count,
                'latest_heartbeat_at' => $i->latest_heartbeat_at?->toIso8601String(),
                'first_heartbeat_at'  => $i->first_heartbeat_at?->toIso8601String(),
                'revoked_at'          => $i->revoked_at?->toIso8601String(),
                'revoked_reason'      => $i->revoked_reason,
                'health'              => $this->classifyHealth($i),
            ]);

        return Inertia::render('Admin/SelfHostedInstalls/Index', [
            'installs' => $installs,
            'tiers' => [
                'self-hosted-standard',
                'self-hosted-enterprise',
            ],
        ]);
    }

    /**
     * Mint + persist a new license key. The actual signed key is shown
     * to the operator once and never persisted in plaintext (so a DB
     * dump on its own can't be replayed against another customer
     * install — they'd also need our private key).
     */
    public function issue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'   => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:200'],
            'plan_tier'     => ['required', 'string', 'in:self-hosted-standard,self-hosted-enterprise'],
            'max_users'     => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_tenants'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'features'      => ['nullable', 'string'],
            'expires_at'    => ['nullable', 'date', 'after:tomorrow'],
        ]);

        $privateKey = (string) env('APP_LICENSE_PRIVATE_KEY', '');
        abort_if($privateKey === '', 503, 'Licensing private key is not configured on this server.');

        $features = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['features'] ?? '')))));
        $expiresAt = ! empty($validated['expires_at'])
            ? CarbonImmutable::parse($validated['expires_at'])->endOfDay()->toIso8601String()
            : null;

        // Tier-aware sanity defaults: Standard = single-tenant cap,
        // Enterprise = unlimited (operator can override). This makes
        // a hand-issued Standard license safe even if someone omits
        // the max_tenants field.
        $maxTenants = isset($validated['max_tenants']) && $validated['max_tenants'] !== ''
            ? (int) $validated['max_tenants']
            : ($validated['plan_tier'] === 'self-hosted-standard' ? 1 : 0);

        $claims = [
            'customer_id'   => $validated['customer_id'],
            'customer_name' => $validated['customer_name'],
            'plan_tier'     => $validated['plan_tier'],
            'max_users'     => (int) ($validated['max_users'] ?? 0),
            'max_tenants'   => $maxTenants,
            'features'      => $features,
            'expires_at'    => $expiresAt,
        ];

        $key = LicenseService::issue($claims, $privateKey);

        // Pre-create the install row so it shows up on the dashboard
        // even before the customer's first heartbeat. We use the
        // license_id we generated inside `issue()` — since the key is
        // signed, the values can't be tampered with after the fact.
        $payloadJson = base64_decode(strtr(explode('.', $key)[0], '-_', '+/'));
        $payloadClaims = json_decode($payloadJson, true) ?: [];

        SelfHostedInstall::firstOrCreate(
            ['license_id' => $payloadClaims['license_id'] ?? ''],
            [
                'customer_id'   => $claims['customer_id'],
                'customer_name' => $claims['customer_name'],
                'plan_tier'     => $claims['plan_tier'],
                'max_users'     => $claims['max_users'],
                'max_tenants'   => $claims['max_tenants'],
                'features'      => $claims['features'],
                'expires_at'    => $expiresAt ? CarbonImmutable::parse($expiresAt) : null,
                'issued_at'     => CarbonImmutable::parse($payloadClaims['issued_at'] ?? now()->toIso8601String()),
            ]
        );

        Log::info('Self-hosted license issued', [
            'license_id' => $payloadClaims['license_id'] ?? null,
            'customer'   => $validated['customer_name'],
            'tier'       => $validated['plan_tier'],
        ]);

        // We flash the key into a one-time session bag so the next
        // page-load can render it; never persist alongside the install
        // row. Operator must copy it before navigating away.
        return redirect()->route('admin.self-hosted.index')->with([
            'success'         => 'License issued for '.$validated['customer_name'].'.',
            'issued_license'  => $key,
        ]);
    }

    public function revoke(Request $request, SelfHostedInstall $install): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $install->forceFill([
            'revoked_at'     => now(),
            'revoked_reason' => $validated['reason'],
        ])->save();

        Log::warning('Self-hosted license revoked', [
            'license_id' => $install->license_id,
            'reason'     => $validated['reason'],
        ]);

        return back()->with('success', 'License revoked. Next heartbeat will lock the customer install.');
    }

    public function unrevoke(Request $request, SelfHostedInstall $install): RedirectResponse
    {
        $install->forceFill([
            'revoked_at'     => null,
            'revoked_reason' => null,
        ])->save();

        Log::info('Self-hosted license un-revoked', ['license_id' => $install->license_id]);

        // Re-issue the *same* key bytes from the stored claims. Because
        // RSASSA-PKCS1-v1_5 (what openssl_sign uses with SHA256) is
        // deterministic and we preserve the original license_id + issued_at,
        // the regenerated string is byte-identical to the one originally
        // shown at issue time. The customer's existing .env keeps working
        // — this is a *display* convenience, not a new key. The DB still
        // never stores the signed bytes (they're regenerated on demand).
        $reshow = null;
        $privateKey = (string) env('APP_LICENSE_PRIVATE_KEY', '');
        if ($privateKey !== '') {
            try {
                $reshow = LicenseService::issue([
                    'license_id'    => $install->license_id,
                    'issued_at'     => $install->issued_at?->toIso8601String() ?? now()->toIso8601String(),
                    'customer_id'   => (string) $install->customer_id,
                    'customer_name' => (string) $install->customer_name,
                    'plan_tier'     => (string) $install->plan_tier,
                    'max_users'     => (int) $install->max_users,
                    'max_tenants'   => (int) ($install->max_tenants ?? 0),
                    'features'      => (array) ($install->features ?? []),
                    'expires_at'    => $install->expires_at?->toIso8601String(),
                ], $privateKey);
            } catch (\Throwable $e) {
                // Re-display is best-effort. If signing fails for any
                // reason, the un-revoke still completed; the operator
                // just doesn't see the key. We log so it's diagnosable.
                Log::warning('License re-display on un-revoke failed', [
                    'license_id' => $install->license_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return back()->with([
            'success'         => 'License re-enabled.',
            'issued_license'  => $reshow,
        ]);
    }

    private function classifyHealth(SelfHostedInstall $install): string
    {
        if ($install->isRevoked()) return 'revoked';
        if (! $install->latest_heartbeat_at) return 'pending';
        $age = $install->latest_heartbeat_at->diffInDays(now());
        if ($age <= 2)   return 'healthy';
        if ($age <= 14)  return 'degraded';
        return 'stale';
    }
}
