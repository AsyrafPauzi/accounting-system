<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $tenant = $user && $user->tenant_id ? Tenant::find($user->tenant_id) : null;
        $data = $tenant?->data ?? [];

        $company = [
            'legal_name' => $data['company']['legal_name'] ?? '',
            'display_name' => $data['company']['display_name'] ?? '',
            'tin' => $data['company']['tin'] ?? '',
            'brn' => $data['company']['brn'] ?? '',
            'street' => $data['company']['street'] ?? '',
            'city' => $data['company']['city'] ?? '',
            'state' => $data['company']['state'] ?? '',
            'postcode' => $data['company']['postcode'] ?? '',
            'country' => $data['company']['country'] ?? 'Malaysia',
            'phone' => $data['company']['phone'] ?? '',
            'website' => $data['company']['website'] ?? '',
            'base_currency' => $data['company']['base_currency'] ?? 'MYR',
            'financial_year_start_month' => $data['company']['financial_year_start_month'] ?? 1,
        ];

        return Inertia::render('Settings/Company', [
            'company' => $company,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenant = $user && $user->tenant_id ? Tenant::findOrFail($user->tenant_id) : null;

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
            'website' => ['nullable', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'max:10'],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $data = $tenant->data ?? [];
        $data['company'] = $validated;

        $tenant->data = $data;
        $tenant->save();

        return redirect()->route('settings.company')->with('success', 'Company settings updated.');
    }
}

