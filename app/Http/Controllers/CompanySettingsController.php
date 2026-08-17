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
            'msic_code' => $tenant->msic_code ?? '',
            'sst_number' => $tenant->sst_number ?? '',
            'invoice_brand_color' => $tenant->invoice_brand_color ?? '#0f172a',
            'invoice_logo_url' => $tenant->invoice_logo_url ?? '',
            'default_invoice_customer_notes' => $tenant->default_invoice_customer_notes ?? '',
            'default_estimate_customer_notes' => $tenant->default_estimate_customer_notes ?? '',
            'reminder_offsets' => is_array($tenant->reminder_offsets) && $tenant->reminder_offsets !== []
                ? $tenant->reminder_offsets
                : \App\Services\InvoiceReminderService::DEFAULT_OFFSETS,
            'myinvois_client_id' => $tenant->myinvois_client_id ?? '',
            'myinvois_secret_set' => filled($tenant->myinvois_client_secret ?? null),
            'myinvois_environment' => in_array($tenant->myinvois_environment ?? '', ['preprod', 'production'], true)
                ? $tenant->myinvois_environment
                : 'preprod',
            'myinvois_id_type' => in_array(strtoupper((string) ($tenant->myinvois_id_type ?? '')), ['BRN', 'NRIC', 'PASSPORT', 'ARMY'], true)
                ? strtoupper($tenant->myinvois_id_type)
                : (str_starts_with(strtoupper((string) ($tenant->tin ?? '')), 'IG') ? 'PASSPORT' : 'BRN'),
            'myinvois_id_value' => $tenant->myinvois_id_value ?? '',
            'myinvois_cert_set' => filled($tenant->myinvois_cert ?? null),
            'toyyibpay_category_code' => $tenant->toyyibpay_category_code ?? '',
            'toyyibpay_secret_set' => filled($tenant->toyyibpay_secret_key ?? null),
            'late_fee_percent' => (float) ($tenant->late_fee_percent ?? 1.5),
            'show_goods_flow' => $tenant->show_goods_flow !== false,
            'invoice_gateway' => $tenant->invoice_gateway ?? 'toyyibpay',
            'billplz_collection_id' => $tenant->billplz_collection_id ?? '',
            'billplz_secret_set' => filled($tenant->billplz_secret_key ?? null),
            'billplz_xsignature_set' => filled($tenant->billplz_xsignature_key ?? null),
            'billplz_sandbox' => $tenant->billplz_sandbox !== false,
            'commercepay_username' => $tenant->commercepay_username ?? '',
            'commercepay_password_set' => filled($tenant->commercepay_password ?? null),
            'commercepay_secret_set' => filled($tenant->commercepay_secret_key ?? null),
            'commercepay_live' => (bool) ($tenant->commercepay_live ?? false),
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
            'msic_code' => ['nullable', 'string', 'max:10'],
            'sst_number' => ['nullable', 'string', 'max:50'],
            'invoice_brand_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_logo_url' => ['nullable', 'string', 'max:500'],
            'default_invoice_customer_notes' => ['nullable', 'string', 'max:5000'],
            'default_estimate_customer_notes' => ['nullable', 'string', 'max:5000'],
            'reminder_offsets' => ['nullable', 'array'],
            'reminder_offsets.*' => ['integer'],
            'myinvois_client_id' => ['nullable', 'string', 'max:255'],
            'myinvois_client_secret' => ['nullable', 'string', 'max:500'],
            'myinvois_environment' => ['nullable', 'string', 'in:preprod,production'],
            'myinvois_id_type' => ['nullable', 'string', 'in:BRN,NRIC,PASSPORT,ARMY'],
            'myinvois_id_value' => ['nullable', 'string', 'max:50'],
            'myinvois_cert' => ['nullable', 'file', 'max:2048'],
            'myinvois_cert_password' => ['nullable', 'string', 'max:500'],
            'toyyibpay_category_code' => ['nullable', 'string', 'max:80'],
            'toyyibpay_secret_key' => ['nullable', 'string', 'max:500'],
            'late_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'show_goods_flow' => ['nullable', 'boolean'],
            'invoice_gateway' => ['nullable', 'string', 'in:toyyibpay,billplz,commercepay'],
            'billplz_collection_id' => ['nullable', 'string', 'max:80'],
            'billplz_secret_key' => ['nullable', 'string', 'max:500'],
            'billplz_xsignature_key' => ['nullable', 'string', 'max:500'],
            'billplz_sandbox' => ['nullable', 'boolean'],
            'commercepay_username' => ['nullable', 'string', 'max:120'],
            'commercepay_password' => ['nullable', 'string', 'max:500'],
            'commercepay_secret_key' => ['nullable', 'string', 'max:500'],
            'commercepay_live' => ['nullable', 'boolean'],
        ]);

        $secret = $validated['myinvois_client_secret'] ?? null;
        $certPassword = $validated['myinvois_cert_password'] ?? null;
        $toyyibSecret = $validated['toyyibpay_secret_key'] ?? null;
        $billplzSecret = $validated['billplz_secret_key'] ?? null;
        $billplzXsig = $validated['billplz_xsignature_key'] ?? null;
        $commercePassword = $validated['commercepay_password'] ?? null;
        $commerceSecret = $validated['commercepay_secret_key'] ?? null;
        unset(
            $validated['myinvois_client_secret'],
            $validated['myinvois_cert'],
            $validated['myinvois_cert_password'],
            $validated['toyyibpay_secret_key'],
            $validated['billplz_secret_key'],
            $validated['billplz_xsignature_key'],
            $validated['commercepay_password'],
            $validated['commercepay_secret_key']
        );

        if (isset($validated['reminder_offsets'])) {
            $validated['reminder_offsets'] = array_values(array_map('intval', $validated['reminder_offsets']));
        }
        if (array_key_exists('show_goods_flow', $validated)) {
            $validated['show_goods_flow'] = filter_var($validated['show_goods_flow'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('billplz_sandbox', $validated)) {
            $validated['billplz_sandbox'] = filter_var($validated['billplz_sandbox'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('commercepay_live', $validated)) {
            $validated['commercepay_live'] = filter_var($validated['commercepay_live'], FILTER_VALIDATE_BOOLEAN);
        }

        $tenant->fill($validated);
        if (filled($secret)) {
            $tenant->myinvois_client_secret = encrypt($secret);
        }
        if ($request->hasFile('myinvois_cert')) {
            $tenant->myinvois_cert = encrypt(base64_encode($request->file('myinvois_cert')->get()));
        }
        if (filled($certPassword)) {
            $tenant->myinvois_cert_password = encrypt($certPassword);
        }
        if (filled($toyyibSecret)) {
            $tenant->toyyibpay_secret_key = encrypt($toyyibSecret);
        }
        if (filled($billplzSecret)) {
            $tenant->billplz_secret_key = encrypt($billplzSecret);
        }
        if (filled($billplzXsig)) {
            $tenant->billplz_xsignature_key = encrypt($billplzXsig);
        }
        if (filled($commercePassword)) {
            $tenant->commercepay_password = encrypt($commercePassword);
        }
        if (filled($commerceSecret)) {
            $tenant->commercepay_secret_key = encrypt($commerceSecret);
        }
        $tenant->save();

        // Translations are now memoised per-request only (see HandleInertiaRequests),
        // so there's no cross-request cache to bust — the next page load picks up the new locale.

        return redirect()->route('settings.company')->with('success', 'Company settings updated.');
    }

    public function testMyInvois(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canAdminCurrentTenant()) {
            return redirect()->back()->with('error', 'You don\'t have permission to test MyInvois.');
        }

        $tenant = $this->resolveTenant($request);
        $env = $request->validate([
            'myinvois_environment' => ['nullable', 'string', 'in:preprod,production'],
        ])['myinvois_environment'] ?? null;
        if ($env) {
            $tenant->myinvois_environment = $env;
        }

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        $result = app(\App\Services\MyInvoisService::class)->probeAuth();

        return redirect()->back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
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
