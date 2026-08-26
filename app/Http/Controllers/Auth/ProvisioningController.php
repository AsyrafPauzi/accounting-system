<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProvisioningController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant->isProvisioned()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Provisioning', [
            'status' => $tenant->provision_status,
            'error' => $tenant->provision_error,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        return response()->json([
            'status' => $tenant->provision_status,
            'error' => $tenant->provision_error,
            'redirect' => $tenant->isProvisioned()
                ? route('dashboard', absolute: false)
                : null,
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant->provision_status !== 'failed') {
            return redirect()->route('provisioning');
        }

        $tenant->update([
            'provision_status' => 'pending',
            'provision_error' => null,
        ]);

        ProvisionTenantJob::dispatch($tenant);

        return redirect()->route('provisioning');
    }

    private function resolveTenant(Request $request): Tenant
    {
        $user = $request->user();
        abort_unless($user && $user->tenant_id, 403);

        $tenant = Tenant::find($user->tenant_id);
        abort_unless($tenant, 404);

        return $tenant;
    }
}
