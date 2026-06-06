<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SelfHostedHeartbeat;
use App\Models\SelfHostedInstall;
use App\Services\Licensing\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Publisher-side endpoint that customer self-hosted installs ping
 * once a day with their license key + usage stats. We:
 *
 *   1. Verify the license signature against our public key (so spoofed
 *      keys can't pollute the install table).
 *   2. Upsert `self_hosted_installs` with the latest fields.
 *   3. Record a row in `self_hosted_heartbeats` for forensics.
 *   4. Return:
 *        - the current revocation list for *this* customer's tier
 *        - the latest available version (so the customer's UI can
 *          show "an update is available")
 *
 * No authentication beyond the license signature — the license is the
 * authentication. This route is public on purpose; we only ever read
 * data from a request whose payload is signed.
 */
class HeartbeatController extends Controller
{
    public function __construct(private readonly LicenseService $svc)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'min:50', 'max:4000'],
            'version'     => ['nullable', 'string', 'max:32'],
            'user_count'  => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payload'     => ['nullable', 'array'],
        ]);

        // Re-use the same verifier the customer side uses, but force
        // it to read the license from the request rather than config.
        $publicKey = (string) (config('deployment.license_public_key') ?? '');
        if ($publicKey === '') {
            Log::warning('Heartbeat: no public key configured on publisher side.');
            return response()->json(['ok' => false, 'reason' => 'server_misconfigured'], 503);
        }

        // Inline parse — the LicenseService::evaluate() method reads
        // from config; we don't want to rebind config in a request
        // handler, so we duplicate the verify step here. This is the
        // only place that's allowed to bypass the cache helpers.
        $parts = explode('.', $validated['license_key']);
        if (count($parts) !== 2) {
            return response()->json(['ok' => false, 'reason' => 'malformed'], 422);
        }
        [$payloadB64, $sigB64] = $parts;
        $payloadJson = self::b64Decode($payloadB64);
        $signature   = self::b64Decode($sigB64);
        if (! $payloadJson || ! $signature) {
            return response()->json(['ok' => false, 'reason' => 'malformed'], 422);
        }
        $verified = openssl_verify($payloadJson, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return response()->json(['ok' => false, 'reason' => 'bad_signature'], 422);
        }
        $claims = json_decode($payloadJson, true);
        if (! is_array($claims) || empty($claims['license_id'])) {
            return response()->json(['ok' => false, 'reason' => 'malformed'], 422);
        }

        // Upsert the install row.
        $install = SelfHostedInstall::firstOrNew(['license_id' => $claims['license_id']]);
        $install->fill([
            'customer_id'         => (string) ($claims['customer_id'] ?? 'unknown'),
            'customer_name'       => (string) ($claims['customer_name'] ?? 'unknown'),
            'plan_tier'           => (string) ($claims['plan_tier'] ?? 'self-hosted-standard'),
            'max_users'           => (int) ($claims['max_users'] ?? 0),
            'features'            => (array) ($claims['features'] ?? []),
            'expires_at'          => ! empty($claims['expires_at']) ? \Carbon\CarbonImmutable::parse($claims['expires_at']) : null,
            'issued_at'           => ! empty($claims['issued_at']) ? \Carbon\CarbonImmutable::parse($claims['issued_at']) : now(),
            'latest_version'      => $validated['version'] ?? null,
            'latest_user_count'   => $validated['user_count'] ?? null,
            'latest_payload'      => $validated['payload'] ?? null,
            'latest_ip'           => $request->ip(),
            'latest_heartbeat_at' => now(),
        ]);
        if (! $install->exists) {
            $install->first_heartbeat_at = now();
        }
        $install->save();

        SelfHostedHeartbeat::create([
            'install_id'  => $install->id,
            'version'     => $validated['version'] ?? null,
            'user_count'  => $validated['user_count'] ?? null,
            'payload'     => $validated['payload'] ?? null,
            'ip'          => $request->ip(),
            'received_at' => now(),
        ]);

        // Build response. We only echo back a tiny revocation list
        // *for this license id* — leaking the full list would let
        // customers infer the publisher's churn, which we don't want.
        $revoked = $install->isRevoked() ? [$install->license_id] : [];

        // Latest release version is now stored in `platform_settings`
        // so the SaaS super-admin can broadcast a new version through
        // the UI (no config edit / redeploy needed). Falls back to the
        // legacy config key for envs that haven't run the migration.
        $latestVersion = \App\Models\PlatformSetting::get('latest_release_version')
            ?? config('app.latest_release', null);
        $updateNotes  = \App\Models\PlatformSetting::get('update_notes');
        $updateUrl    = \App\Models\PlatformSetting::get('latest_release_url');

        return response()->json([
            'ok'              => true,
            'revoked'         => $revoked,
            'revoked_reason'  => $install->revoked_reason,
            'latest_version'  => $latestVersion,
            'update_notes'    => $updateNotes,
            'update_url'      => $updateUrl,
            'server_time'     => now()->toIso8601String(),
        ]);
    }

    private static function b64Decode(string $s): ?string
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        $bin = base64_decode($s, true);
        return $bin === false ? null : $bin;
    }
}
