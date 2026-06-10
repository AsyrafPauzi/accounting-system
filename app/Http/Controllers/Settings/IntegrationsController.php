<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TenantApiCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → API & Integrations.
 *
 * Lists every active OAuth credential the tenant has issued and lets
 * an admin revoke any of them. Plan-gated by `api.access` (Solo+).
 *
 * Note this is NOT where users *issue* keys — that happens through the
 * partner-driven OAuth flow (the partner's "Connect to BukuCloud"
 * button). Manual issuance from this page would defeat the purpose
 * of having an authorization handshake at all.
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
            // Surface the configured partner list so the empty-state can
            // tell the user "to connect Fin Persona, click their Connect
            // button" rather than a generic blank slate.
            'available_partners' => collect(config('oauth.clients', []))
                ->map(fn ($c, $id) => [
                    'id'          => $id,
                    'name'        => $c['name'] ?? $id,
                    'description' => $c['description'] ?? null,
                ])
                ->values()
                ->all(),
        ]);
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
