<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRequest;
use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Services\EpfExportService;
use App\Services\PayrollService;
use App\Services\PcbExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payroll,
        protected EpfExportService $epfExport,
        protected PcbExportService $pcbExport,
    ) {}

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
            'employees'    => $this->employeeOptions(),
            'todayIso'     => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function store(StorePayrollRequest $request): RedirectResponse
    {
        $journal = $this->payroll->record($request->validated());

        $redirect = redirect()
            ->route('payroll.create')
            ->with('success', "Payroll posted: {$journal->description} (RM " . number_format(
                $journal->items()->sum('debit'),
                2
            ) . ' total).');

        if ($journal->payrollEmployeeLines()->exists()) {
            $redirect->with('payroll_exports', ['journal_id' => $journal->id]);
        }

        return $redirect;
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

    public function exportEpf(JournalEntry $journal): StreamedResponse
    {
        $this->authorize('journal.view');

        $csv = $this->epfExport->csvForJournal($journal);
        $filename = 'epf-'.date('Y-m', strtotime((string) $journal->date)).'.csv';

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function exportPcb(JournalEntry $journal): StreamedResponse
    {
        $this->authorize('journal.view');

        $csv = $this->pcbExport->csvForJournal($journal);
        $filename = 'pcb-'.date('Y-m', strtotime((string) $journal->date)).'.csv';

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
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

    /**
     * @return list<array{id:int,label:string,basic_salary:float}>
     */
    private function employeeOptions(): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_number', 'basic_salary'])
            ->map(fn (Employee $employee) => [
                'id'            => $employee->id,
                'label'         => trim($employee->name . ($employee->employee_number ? " ({$employee->employee_number})" : '')),
                'basic_salary'  => (float) $employee->basic_salary,
            ])
            ->values()
            ->all();
    }
}
