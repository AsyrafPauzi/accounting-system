<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Services\BudgetVsActualService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private BudgetVsActualService $budgets) {}

    public function index(Request $request): Response
    {
        $this->authorize('reports.profit-loss');

        $fiscalYear = (int) ($request->input('fiscal_year') ?: now()->year);
        $selectedMonth = (int) ($request->input('month') ?: now()->month);
        $selectedMonth = max(1, min(12, $selectedMonth));

        $budget = $this->budgets->ensureBudgetForYear($fiscalYear);

        $accounts = Account::query()
            ->whereIn('type', ['income', 'expense'])
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('display_order')
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $amounts = BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->where('month', $selectedMonth)
            ->pluck('amount', 'account_code')
            ->map(fn ($amount) => (float) $amount)
            ->all();

        $yearOptions = range(now()->year + 1, now()->year - 3);

        return Inertia::render('Budgets/Index', [
            'budget'        => $budget,
            'accounts'      => $accounts,
            'amounts'       => $amounts,
            'fiscalYear'    => $fiscalYear,
            'selectedMonth' => $selectedMonth,
            'yearOptions'   => $yearOptions,
            'monthLabels'   => $this->monthLabels(),
        ]);
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('reports.profit-loss');

        $validated = $request->validate([
            'month'              => 'required|integer|min:1|max:12',
            'lines'              => 'required|array',
            'lines.*.account_code' => 'required|string|exists:accounts,code',
            'lines.*.amount'     => 'nullable|numeric|min:0',
        ]);

        $lines = collect($validated['lines'])->map(fn (array $line) => [
            'account_code' => $line['account_code'],
            'month'        => (int) $validated['month'],
            'amount'       => $line['amount'] ?? 0,
        ])->all();

        $this->budgets->upsertLines($budget, $lines);

        return redirect()
            ->route('budgets.index', [
                'fiscal_year' => $budget->fiscal_year,
                'month'       => $validated['month'],
            ])
            ->with('success', 'Budget saved for '.$this->monthLabels()[(int) $validated['month']].' '.$budget->fiscal_year.'.');
    }

    /**
     * @return array<int, string>
     */
    private function monthLabels(): array
    {
        return [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
    }
}
