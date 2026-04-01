<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChartOfAccountsController extends Controller
{
    protected const TYPE_ORDER = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function index(Request $request): Response
    {
        $accounts = Account::with('parent')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->sortBy(fn (Account $a) => [array_search($a->type, self::TYPE_ORDER, true), $a->display_order ?? 9999, $a->code])
            ->values()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'type_label' => Account::getTypeLabel($a->type),
                'parent_id' => $a->parent_id,
                'parent_code' => $a->parent?->code,
                'description' => $a->description,
                'is_active' => $a->is_active,
                'display_order' => $a->display_order,
            ]);

        $groupedByType = $accounts->groupBy('type');

        return Inertia::render('ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'groupedByType' => $groupedByType,
        ]);
    }

    public function create(): Response
    {
        $accounts = Account::orderBy('type')->orderBy('code')->get(['id', 'code', 'name', 'type']);

        return Inertia::render('ChartOfAccounts/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['parent_id'] = $validated['parent_id'] ?? null;
        $validated['display_order'] = isset($validated['display_order']) && $validated['display_order'] !== '' ? (int) $validated['display_order'] : null;
        $validated['is_active'] = $request->boolean('is_active', true);

        Account::create($validated);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(int $id): Response|RedirectResponse
    {
        $account = Account::findOrFail($id);
        $accounts = Account::where('id', '!=', $id)->orderBy('type')->orderBy('code')->get(['id', 'code', 'name', 'type']);

        return Inertia::render('ChartOfAccounts/Edit', [
            'account' => [
                'id'            => $account->id,
                'code'          => $account->code,
                'name'          => $account->name,
                'type'          => $account->type,
                'parent_id'     => $account->parent_id,
                'description'   => $account->description,
                'is_active'     => $account->is_active,
                'display_order' => $account->display_order,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateAccountRequest $request, int $id): RedirectResponse
    {
        $account = Account::findOrFail($id);
        $validated = $request->validated();
        $validated['parent_id'] = $validated['parent_id'] ?? null;
        $validated['display_order'] = isset($validated['display_order']) && $validated['display_order'] !== '' ? (int) $validated['display_order'] : null;
        $validated['is_active'] = $request->boolean('is_active', true);

        $account->update($validated);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $account = Account::findOrFail($id);

        if ($account->children()->exists()) {
            return redirect()->route('chart-of-accounts.index')
                ->with('error', 'Cannot delete an account that has sub-accounts. Reassign or delete sub-accounts first.');
        }

        $inUse = DB::table('journal_items')->where('account_code', $account->code)->exists();
        if ($inUse) {
            return redirect()->route('chart-of-accounts.index')
                ->with('error', 'Cannot delete this account because it is used in journal entries.');
        }

        $account->delete();

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    /**
     * Seed default chart of accounts (e.g. 1000, 1100, 1200, 2000, 2100, 3000, 4000, 5000) when empty or add missing.
     */
    public function seedDefault(Request $request): RedirectResponse
    {
        $defaults = [
            ['code' => '1000', 'name' => 'Assets', 'type' => 'asset', 'display_order' => 1],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'display_order' => 2],
            ['code' => '1200', 'name' => 'Bank', 'type' => 'asset', 'display_order' => 3],
            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability', 'display_order' => 4],
            ['code' => '2100', 'name' => 'Tax Payable', 'type' => 'liability', 'display_order' => 5],
            ['code' => '2110', 'name' => 'Accounts Payable', 'type' => 'liability', 'display_order' => 6],
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'display_order' => 7],
            ['code' => '4000', 'name' => 'Revenue', 'type' => 'income', 'display_order' => 8],
            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'display_order' => 9],
        ];

        $existingCodes = Account::pluck('code')->toArray();
        $created = 0;
        foreach ($defaults as $row) {
            if (in_array($row['code'], $existingCodes, true)) {
                continue;
            }
            Account::create(array_merge($row, ['is_active' => true]));
            $existingCodes[] = $row['code'];
            $created++;
        }

        $message = $created > 0
            ? "Default chart seeded: {$created} account(s) added."
            : 'Chart already has default accounts; no new accounts added.';

        return redirect()->route('chart-of-accounts.index')->with('success', $message);
    }

    /**
     * Export chart of accounts as CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $accounts = Account::with('parent')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->sortBy(fn (Account $a) => [array_search($a->type, self::TYPE_ORDER, true), $a->display_order ?? 9999, $a->code])
            ->values();

        $filename = 'chart-of-accounts-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($accounts) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', 'name', 'type', 'type_label', 'parent_code', 'description', 'is_active', 'display_order']);
            foreach ($accounts as $a) {
                fputcsv($out, [
                    $a->code,
                    $a->name,
                    $a->type,
                    Account::getTypeLabel($a->type),
                    $a->parent?->code ?? '',
                    $a->description ?? '',
                    $a->is_active ? 'Yes' : 'No',
                    $a->display_order ?? '',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export chart of accounts as PDF.
     */
    public function exportPdf()
    {
        $accounts = Account::with('parent')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->sortBy(fn (Account $a) => [array_search($a->type, self::TYPE_ORDER, true), $a->display_order ?? 9999, $a->code])
            ->values()
            ->groupBy('type');

        $company = $this->reportCompany();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.chart-of-accounts', [
            'groupedByType' => $accounts,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('chart-of-accounts-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Company data for report PDF header (tenant or config fallback).
     */
    protected function reportCompany(): array
    {
        $user = request()->user();
        if ($user && $user->tenant_id) {
            $tenant = \App\Models\Tenant::find($user->tenant_id);
            $data = $tenant?->data ?? [];
            $c = $data['company'] ?? [];
            $name = $c['display_name'] ?? $c['legal_name'] ?? config('invoice.company.name');
            $addressParts = array_filter([$c['street'] ?? '', $c['city'] ?? '', $c['state'] ?? '', $c['postcode'] ?? '', $c['country'] ?? '']);
            $address = implode(', ', $addressParts);

            return ['name' => $name ?: config('invoice.company.name'), 'address' => $address ?: config('invoice.company.address')];
        }

        return config('invoice.company');
    }
}
