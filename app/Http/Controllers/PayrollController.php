<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRequest;
use App\Models\Account;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        return Inertia::render('Payroll/Run', [
            'bankAccounts' => $this->bankAccountOptions(),
            'accounts'     => $this->accountSummary($accounts),
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

    public function batchCreate(): Response
    {
        $this->authorize('journal.create');

        $accounts = $this->payroll->ensureAccounts();

        return Inertia::render('Payroll/Batch', [
            'bankAccounts' => $this->bankAccountOptions(),
            'accounts'     => $this->accountSummary($accounts),
            'todayIso'     => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function batchStore(Request $request): RedirectResponse
    {
        $this->authorize('journal.create');

        $rows = $request->input('rows', []);

        $validator = Validator::make(
            ['rows' => $rows],
            StorePayrollRequest::batchRules(withAccountExists: true)
        );
        StorePayrollRequest::addBatchBalanceChecks($validator, is_array($rows) ? $rows : []);
        $validated = $validator->validate();

        $journals = $this->payroll->recordMany($validated['rows']);
        $count = count($journals);
        $total = collect($journals)->sum(fn ($journal) => (float) $journal->items()->sum('debit'));

        return redirect()
            ->route('journal.index')
            ->with('success', $count === 1
                ? '1 payroll run posted (RM '.number_format($total, 2).' total).'
                : "{$count} payroll runs posted (RM ".number_format($total, 2).' total).');
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function bankAccountOptions(): array
    {
        return Account::bankOrCash()
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, Account>  $accounts
     * @return array<string, array{code:string,name:string}>
     */
    private function accountSummary(array $accounts): array
    {
        return collect($accounts)->mapWithKeys(fn ($a, $key) => [
            $key => ['code' => $a->code, 'name' => $a->name],
        ])->toArray();
    }
}
