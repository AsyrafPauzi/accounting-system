<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\FirmClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sets / clears the `acting_tenant_id` session key the
 * `InitializeTenancyByLoggedInUser` middleware reads. This is the
 * single point of "switch into a client" decisions — every other
 * piece of the Practice console assumes the session is already
 * pointed at the right client by the time the request lands.
 */
class ClientSwitcherController extends Controller
{
    /**
     * Switch into a client tenant. Verifies the firm has an active
     * link, sets the session key, and redirects to the dashboard.
     *
     * Refusing access deliberately throws a 403 rather than redirecting
     * to the practice console — a tampered tenant id is not a UX
     * mistake, it's an attack pattern, and we want the audit trail to
     * show a denial rather than a silent fall-through.
     */
    public function switch(Request $request, string $tenantId): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);

        $link = FirmClient::query()
            ->where('firm_id', $user->firm_id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        abort_unless($link, 403, 'You don\'t have access to that client.');

        $request->session()->put('acting_tenant_id', $tenantId);

        Log::info('Practice: switched into client', [
            'user_id'   => $user->id,
            'firm_id'   => $user->firm_id,
            'tenant_id' => $tenantId,
        ]);

        // Land them on the per-client dashboard. The middleware will
        // initialise tenancy by the time the dashboard controller runs.
        return redirect()->route('dashboard');
    }

    /**
     * Exit the client context and go back to the firm-level Practice
     * console. Just clears the session key — middleware reverts to
     * "no tenancy initialised" on the next request.
     */
    public function exit(Request $request): RedirectResponse
    {
        $request->session()->forget('acting_tenant_id');
        return redirect()->route('practice.dashboard');
    }
}
