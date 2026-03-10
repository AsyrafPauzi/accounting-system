<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TenantAdminController extends Controller
{
    /**
     * Simple admin view of all tenants and their databases.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user() && $request->user()->role === 'admin', 403);

        $tenants = Tenant::all()->map(function (Tenant $tenant) {
            $dbName = method_exists($tenant, 'database') ? $tenant->database()->getName() : null;
            $owner = User::where('tenant_id', $tenant->getKey())->orderBy('id')->first();

            return [
                'id' => $tenant->getKey(),
                'name' => $tenant->getKey(),
                'database' => $dbName,
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Impersonate a tenant's user (log in as them).
     */
    public function impersonate(Request $request, int $userId): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->role === 'admin', 403);

        $user = User::findOrFail($userId);

        // Remember the original admin so we can restore later if needed.
        if (! $request->session()->has('impersonator_id')) {
            $request->session()->put('impersonator_id', $request->user()->id);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Stop impersonation and return to the original admin user.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_unless($impersonatorId, 403);

        Auth::loginUsingId($impersonatorId);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}

