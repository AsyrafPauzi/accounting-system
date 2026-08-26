<?php

namespace App\Http\Controllers;

use App\Models\TaxCode;
use App\Support\TaxCodeDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaxCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings.view');

        TaxCodeDefaults::seedMissing();

        $codes = TaxCode::query()
            ->orderBy('code')
            ->get()
            ->map(fn (TaxCode $c) => [
                'id'                    => $c->id,
                'code'                  => $c->code,
                'name'                  => $c->name,
                'rate'                  => (float) $c->rate,
                'type'                  => $c->type,
                'output_account_code'   => $c->output_account_code,
                'input_account_code'    => $c->input_account_code,
                'is_active'             => (bool) $c->is_active,
                'is_system'             => in_array($c->code, ['SR-8', 'ST-10', 'ES', 'ZRL'], true),
            ])
            ->values()
            ->all();

        return Inertia::render('Settings/TaxCodes/Index', [
            'taxCodes' => $codes,
            'canEdit'  => $request->user()?->can('settings.edit') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('settings.edit');

        $validated = $request->validate([
            'code'                  => 'required|string|max:20|unique:tax_codes,code',
            'name'                  => 'required|string|max:120',
            'rate'                  => 'required|numeric|min:0|max:100',
            'type'                  => 'required|in:standard,zero,exempt,out_of_scope',
            'output_account_code'   => 'nullable|string|max:10',
            'input_account_code'    => 'nullable|string|max:10',
        ]);

        TaxCode::query()->create([
            ...$validated,
            'code' => strtoupper(trim($validated['code'])),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Tax code created.');
    }

    public function update(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $this->authorize('settings.edit');

        $validated = $request->validate([
            'name'                  => 'required|string|max:120',
            'rate'                  => 'required|numeric|min:0|max:100',
            'type'                  => 'required|in:standard,zero,exempt,out_of_scope',
            'output_account_code'   => 'nullable|string|max:10',
            'input_account_code'    => 'nullable|string|max:10',
            'is_active'             => 'boolean',
        ]);

        $taxCode->update($validated);

        return redirect()->back()->with('success', 'Tax code updated.');
    }

    public function destroy(TaxCode $taxCode): RedirectResponse
    {
        $this->authorize('settings.edit');

        $inUse = $this->codeInUse($taxCode->id);
        if ($inUse) {
            return redirect()->back()->with('error', 'Tax code is in use on line items — deactivate instead.');
        }

        if (in_array($taxCode->code, ['SR-8', 'ST-10', 'ES', 'ZRL'], true)) {
            return redirect()->back()->with('error', 'System tax codes cannot be deleted.');
        }

        $taxCode->delete();

        return redirect()->back()->with('success', 'Tax code deleted.');
    }

    private function codeInUse(int $taxCodeId): bool
    {
        foreach (['invoice_items', 'bill_items', 'credit_note_items', 'debit_note_items', 'supplier_credit_note_items', 'supplier_debit_note_items'] as $table) {
            if (DB::table($table)->where('tax_code_id', $taxCodeId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
