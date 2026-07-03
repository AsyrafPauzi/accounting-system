<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantApiCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → API & Integrations.
 *
 * Lists active API credentials and lets an admin generate a direct
 * read-only key or revoke any existing credential. Plan-gated by
 * `api.access` (Solo+).
 */
class IntegrationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $credentials = TenantApiCredential::query()
            ->with(['issuedBy:id,name,email', 'revokedBy:id,name,email'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (TenantApiCredential $c) {
                $client = config("oauth.clients.{$c->oauth_client_id}");
                return [
                    'id'               => $c->id,
                    'partner_id'       => $c->oauth_client_id,
                    'partner_name'     => $client['name'] ?? $c->oauth_client_id,
                    'read_only'        => (bool) ($client['read_only'] ?? false),
                    'masked_api_key'   => $c->maskedApiKey(),
                    'masked_signing'   => $c->maskedSigningKey(),
                    'issued_at'        => $c->created_at?->toIso8601String(),
                    'last_used_at'     => $c->last_used_at?->toIso8601String(),
                    'revoked_at'       => $c->revoked_at?->toIso8601String(),
                    'is_active'        => $c->isActive(),
                    'issued_by'        => $c->issuedBy ? [
                        'name'  => $c->issuedBy->name,
                        'email' => $c->issuedBy->email,
                    ] : null,
                    'revoked_by'       => $c->revokedBy ? [
                        'name'  => $c->revokedBy->name,
                        'email' => $c->revokedBy->email,
                    ] : null,
                ];
            });

        return Inertia::render('Settings/Integrations', [
            'credentials' => $credentials->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

        if (! $tenant || ! $tenant->hasPlanPermission('api.access')) {
            return redirect()->route('settings.integrations.index')
                ->with('error', 'Your plan does not include API access.');
        }

        $issued = TenantApiCredential::issueFor(
            tenantId: $tenant->id,
            oauthClientId: 'direct',
            issuedByUserId: $user->id,
        );

        return redirect()->route('settings.integrations.index')
            ->with('success', 'API key generated. Copy it now — it will not be shown again.')
            ->with('issued_api_key', $issued['api_key']);
    }

    public function revoke(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $credential = TenantApiCredential::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();

        if (! $credential) {
            return redirect()->route('settings.integrations.index')
                ->with('error', 'Credential not found.');
        }

        if (! $credential->isActive()) {
            return redirect()->route('settings.integrations.index')
                ->with('info', 'Credential was already revoked.');
        }

        $credential->revoke($user->id);

        return redirect()->route('settings.integrations.index')
            ->with('success', 'API credential revoked. The partner will receive 401 errors immediately on subsequent calls.');
    }
}
