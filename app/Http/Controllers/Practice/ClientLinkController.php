<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\FirmClient;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Manages the firm ↔ client tenant link itself, distinct from the
 * "switch into a client" flow (ClientSwitcherController) and the
 * "add a client" flow (AddClientController).
 *
 * Today this is a single endpoint — `destroy` — that breaks the link
 * between a firm and one of its client tenants. The tenant itself is
 * preserved verbatim: their database, users, books, subscription and
 * branding stay exactly as they were. The only thing that changes is
 * that the firm loses access.
 *
 * Why no destructive option here:
 *   - The firm is the bookkeeper, not the data owner. Even when the
 *     firm originally provisioned the tenant via `AddClient::createNew`,
 *     the tenant has its own admin user (the `owner_email` the firm
 *     entered at creation). They can sign in and continue solo.
 *   - Hard-deleting an entire client tenant is a finance-records-act
 *     hazard (7-year retention) and should be a deliberate super-admin
 *     action, not a one-click on the firm dashboard.
 */
class ClientLinkController extends Controller
{
    /**
     * Unlink a client tenant from the firm. The pivot row is hard-deleted
     * (no soft-delete on FirmClient) so reconnecting later is a fresh
     * invitation rather than a "restore". If the firm-user happened to
     * be acting on this tenant when they unlinked, we drop their
     * `acting_tenant_id` so the next request lands them back on the
     * Practice console rather than 403'ing into a tenant they can no
     * longer access.
     */
    public function destroy(Request $request, string $tenantId): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);

        // Belt-and-braces: the route already gates on the
        // practice.clients.unlink permission via middleware, but if the
        // permission was renamed (or the middleware accidentally
        // dropped) we still refuse here.
        abort_unless($user->can('practice.clients.unlink'), 403);

        $link = FirmClient::query()
            ->where('firm_id', $user->firm_id)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_unless($link, 404, 'No such client linked to your firm.');

        // Resolve the tenant for a friendlier flash message before we
        // wipe the pivot. If the tenant row is gone (orphaned pivot)
        // fall back to the id — unlinking still proceeds.
        $tenant = Tenant::find($tenantId);
        $clientLabel = $tenant?->display_name
            ?: $tenant?->legal_name
            ?: $tenantId;

        $link->delete();

        // If the firm user was inside this client right now, drop them
        // back to the firm console. Otherwise leave the session alone —
        // they might be acting on a different client.
        if ($request->session()->get('acting_tenant_id') === $tenantId) {
            $request->session()->forget('acting_tenant_id');
        }

        Log::info('Practice: client unlinked', [
            'user_id'   => $user->id,
            'firm_id'   => $user->firm_id,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('practice.dashboard')->with(
            'success',
            "Unlinked {$clientLabel} from your firm. Their books and users are unchanged — they can continue on their own."
        );
    }
}
