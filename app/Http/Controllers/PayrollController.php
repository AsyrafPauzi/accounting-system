<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRequest;
use App\Models\Account;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    /**
     * Show the "Run Payroll" form. Auto-creates the payroll Chart of
     * Accounts rows on first visit so tenants don't need to manually
     * set them up before their first run.
     */
    public function create(): Response
    {
        $this->authorize('journal.create');

        $accounts = $this->payroll->ensureAccounts();

        $bankAccounts = Account::bankOrCash()
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])
            ->values()
            ->all();

        $accountSummary = collect($accounts)->mapWithKeys(fn ($a, $key) => [
            $key => ['code' => $a->code, 'name' => $a->name],
        ])->toArray();

        return Inertia::render('Payroll/Run', [
            'bankAccounts' => $bankAccounts,
            'accounts'     => $accountSummary,
            'todayIso'     => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function store(StorePayrollRequest $request): RedirectResponse
    {
        $journal = $this->payroll->record($request->validated());

        return redirect()
            ->route('journal.index')
            ->with('success', "Payroll posted: {$journal->description} (RM " . number_format(
                $journal->items()->sum('debit'),
                2
            ) . ' total). View it on the Manual Journal list.');
    }
}
