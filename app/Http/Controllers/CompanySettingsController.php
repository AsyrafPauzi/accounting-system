<?php

namespace App\Http\Controllers;

use App\Models\Firm;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        // Firm-users at the firm console (no client open) need their
        // own settings surface — the tenant Company form doesn't apply
        // because there's no tenant in scope. Render the Firm-level
        // settings page instead so they can edit firm name / contact
        // details. Once they Enter → into a client, this same route
        // resolves the client's tenant and shows the tenant form.
        $user = $request->user();
        if ($user && $user->isFirmUser() && ! $this->actingOnTenant()) {
            return $this->editFirm($request);
        }

        $tenant = $this->resolveTenant($request);

        // Stancl/Tenancy stores attributes in the 'data' JSON column but makes them accessible directly.
        $company = [
            'legal_name' => $tenant->legal_name ?? '',
            'display_name' => $tenant->display_name ?? '',
            'tin' => $tenant->tin ?? '',
            'brn' => $tenant->brn ?? '',
            'street' => $tenant->street ?? '',
            'city' => $tenant->city ?? '',
            'state' => $tenant->state ?? '',
            'postcode' => $tenant->postcode ?? '',
            'country' => $tenant->country ?? 'Malaysia',
            'phone' => $tenant->phone ?? '',
            'email' => $tenant->email ?? '',
            'website' => $tenant->website ?? '',
            'base_currency' => $tenant->base_currency ?? 'MYR',
            'financial_year_start_month' => $tenant->financial_year_start_month ?? 1,
            'language' => $tenant->language ?? 'en',
        ];

        return Inertia::render('Settings/Company', [
            'company' => $company,
            // Surface the gate so the React component can disable
            // inputs / hide the Save button for read-only users (firm
            // viewers, SME staff without admin role).
            'canEdit' => optional($request->user())->canAdminCurrentTenant() ?? false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Firm-level update path — branched off the same route so the
        // form on /settings/company can post to one endpoint regardless
        // of whether the user is in firm-console mode or client mode.
        if ($user && $user->isFirmUser() && ! $this->actingOnTenant()) {
            return $this->updateFirm($request);
        }

        if (! $user || ! $user->canAdminCurrentTenant()) {
            return redirect()->back()->with(
                'error',
                'You don\'t have permission to update this company\'s settings. '
                .'Tenant admins or firm-users with admin access can edit.'
            );
        }

        $tenant = $this->resolveTenant($request);

        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'brn' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'max:10'],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'language' => ['nullable', 'string', 'in:en,ms'],
        ]);

        $tenant->fill($validated);
        $tenant->save();

        // Translations are now memoised per-request only (see HandleInertiaRequests),
        // so there's no cross-request cache to bust — the next page load picks up the new locale.

        return redirect()->route('settings.company')->with('success', 'Company settings updated.');
    }

    /**
     * Resolve the tenant the user is currently working in.
     *
     * Priority order:
     *   1. The active tenancy context — set by InitializeTenancyByLoggedInUser
     *      for both SME users (via their `tenant_id`) and firm-users
     *      (via the `acting_tenant_id` session key). This is the
     *      authoritative answer.
     *   2. Fallback to the user's own `tenant_id` for legacy code paths
     *      that bypass the tenancy bootstrapping middleware.
     *
     * Aborting with 404 when neither resolves keeps stack traces clean
     * and avoids null-pointer access in the calling code.
     */
    private function resolveTenant(Request $request): Tenant
    {
        if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant) {
            $tenant = tenancy()->tenant;
            // tenancy()->tenant is the same Tenant model so we can hand it back directly.
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $user = $request->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                return $tenant;
            }
        }

        abort(404, 'No company in scope. Switch into a client first if you\'re a firm-user.');
    }

    /**
     * True when there's a real tenancy context active (i.e. the
     * firm-user has clicked Enter → into a client). For firm-users
     * sitting at the practice console, this returns false even if
     * `tenancy()` is technically resolvable, which is what we want
     * — at the firm level "company" means the firm itself.
     */
    private function actingOnTenant(): bool
    {
        return function_exists('tenancy')
            && tenancy()->initialized
            && tenancy()->tenant instanceof Tenant;
    }

    /**
     * GET /settings/company for firm-users at the practice console.
     * Edits the Firm row (name, contact email, etc.) — fewer fields
     * than the tenant form because firms don't have legal/tax/year
     * concepts; that's the SME side.
     */
    private function editFirm(Request $request): Response
    {
        $firm = $this->resolveFirm($request);

        return Inertia::render('Settings/CompanyFirm', [
            'firm' => [
                'id'            => $firm->id,
                'name'          => $firm->name ?? '',
                'contact_email' => $firm->contact_email ?? '',
                'contact_phone' => $firm->contact_phone ?? '',
                'country'       => $firm->country ?? 'Malaysia',
                'status'        => $firm->status ?? 'active',
            ],
            // Only firm-owners may edit; staff see the form read-only.
            'canEdit' => $request->user()?->isFirmOwner() ?? false,
        ]);
    }

    private function updateFirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isFirmOwner()) {
            return redirect()->back()->with(
                'error',
                'Only firm owners can edit firm settings.'
            );
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'country'       => ['nullable', 'string', 'max:255'],
        ]);

        $firm = $this->resolveFirm($request);
        $firm->fill($validated)->save();

        return redirect()->route('settings.company')->with('success', 'Firm settings updated.');
    }

    private function resolveFirm(Request $request): Firm
    {
        $user = $request->user();
        $firm = $user?->firm()->first();
        abort_unless($firm, 404, 'No firm associated with this account.');
        return $firm;
    }
}
