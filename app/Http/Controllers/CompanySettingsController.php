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
        $companyData = $tenant->company ?? [];
        $company = [
            'legal_name' => $companyData['legal_name'] ?? $tenant->legal_name ?? '',
            'display_name' => $companyData['display_name'] ?? $tenant->display_name ?? '',
            'tin' => $companyData['tin'] ?? $tenant->tin ?? '',
            'brn' => $companyData['brn'] ?? $tenant->brn ?? '',
            'street' => $companyData['street'] ?? $tenant->street ?? '',
            'city' => $companyData['city'] ?? $tenant->city ?? '',
            'state' => $companyData['state'] ?? $tenant->state ?? '',
            'postcode' => $companyData['postcode'] ?? $tenant->postcode ?? '',
            'country' => $companyData['country'] ?? $tenant->country ?? 'Malaysia',
            'phone' => $companyData['phone'] ?? $tenant->phone ?? '',
            'website' => $companyData['website'] ?? $tenant->website ?? '',
            'base_currency' => $companyData['base_currency'] ?? $tenant->base_currency ?? 'MYR',
            'financial_year_start_month' => $companyData['financial_year_start_month'] ?? $tenant->financial_year_start_month ?? 1,
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

        $tenant->company = $validated;
        $tenant->save();

        return redirect()->route('settings.company')->with('success', 'Company settings updated.');
    }
}

