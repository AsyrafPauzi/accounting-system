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
        
        // Stancl/Tenancy stores attributes in the 'data' JSON column but makes them accessible directly
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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['admin', 'super-admin'])) {
            return redirect()->back()->with('error', 'Only administrators can update company settings.');
        }

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
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'max:10'],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'language' => ['nullable', 'string', 'in:en,ms'],
        ]);

        $tenant->fill($validated);
        $tenant->save();

        // Bust the cached translations so the new locale is reflected immediately.
        \Illuminate\Support\Facades\Cache::forget('translations.en');
        \Illuminate\Support\Facades\Cache::forget('translations.ms');

        return redirect()->route('settings.company')->with('success', 'Company settings updated.');
    }
}

