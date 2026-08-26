<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Support\PostedJournalScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetVsActualService
{
    /**
     * @return array{
     *     budget: Budget,
     *     fiscal_year: int,
     *     date_from: string,
     *     date_to: string,
     *     revenue_rows: list<array<string, mixed>>,
     *     expense_rows: list<array<string, mixed>>,
     *     total_budget_revenue: float,
     *     total_actual_revenue: float,
     *     total_variance_revenue: float,
     *     total_budget_expense: float,
     *     total_actual_expense: float,
     *     total_variance_expense: float,
     * }
     */
    public function build(Budget $budget, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $months = $this->monthsInRange($budget->fiscal_year, $from, $to);

        $budgetByAccount = $this->budgetTotalsByAccount($budget->id, $months);
        $actualByAccount = $this->actualTotalsByAccount($dateFrom, $dateTo);

        $accounts = Account::query()
            ->whereIn('type', ['income', 'expense'])
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $revenueRows = [];
        $expenseRows = [];
        $totalBudgetRevenue = 0.0;
        $totalActualRevenue = 0.0;
        $totalBudgetExpense = 0.0;
        $totalActualExpense = 0.0;

        foreach ($accounts as $account) {
            $budgetAmount = round((float) ($budgetByAccount[$account->code] ?? 0), 2);
            $actualAmount = round((float) ($actualByAccount[$account->code] ?? 0), 2);

            if ($budgetAmount == 0.0 && $actualAmount == 0.0) {
                continue;
            }

            $variance = round($actualAmount - $budgetAmount, 2);
            $row = [
                'code'           => $account->code,
                'name'           => $account->name,
                'type'           => $account->type,
                'budget'         => $budgetAmount,
                'actual'         => $actualAmount,
                'variance'       => $variance,
                'variance_pct'   => $this->variancePercent($budgetAmount, $variance),
            ];

            if ($account->type === 'income') {
                $revenueRows[] = $row;
                $totalBudgetRevenue += $budgetAmount;
                $totalActualRevenue += $actualAmount;
            } else {
                $expenseRows[] = $row;
                $totalBudgetExpense += $budgetAmount;
                $totalActualExpense += $actualAmount;
            }
        }

        return [
            'budget'                 => $budget,
            'fiscal_year'            => $budget->fiscal_year,
            'date_from'              => $dateFrom,
            'date_to'                => $dateTo,
            'revenue_rows'           => $revenueRows,
            'expense_rows'           => $expenseRows,
            'total_budget_revenue'   => round($totalBudgetRevenue, 2),
            'total_actual_revenue'   => round($totalActualRevenue, 2),
            'total_variance_revenue' => round($totalActualRevenue - $totalBudgetRevenue, 2),
            'total_budget_expense'   => round($totalBudgetExpense, 2),
            'total_actual_expense'   => round($totalActualExpense, 2),
            'total_variance_expense' => round($totalActualExpense - $totalBudgetExpense, 2),
        ];
    }

    public function ensureBudgetForYear(int $fiscalYear): Budget
    {
        return Budget::firstOrCreate(
            ['fiscal_year' => $fiscalYear],
            ['name' => "Budget {$fiscalYear}", 'is_active' => true]
        );
    }

    /**
     * @param  list<array{account_code:string,month:int,amount:float|int|string}>  $lines
     */
    public function upsertLines(Budget $budget, array $lines): void
    {
        foreach ($lines as $line) {
            $month = (int) ($line['month'] ?? 0);
            $code = trim((string) ($line['account_code'] ?? ''));
            if ($month < 1 || $month > 12 || $code === '') {
                continue;
            }

            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($amount == 0.0) {
                BudgetLine::query()
                    ->where('budget_id', $budget->id)
                    ->where('account_code', $code)
                    ->where('month', $month)
                    ->delete();

                continue;
            }

            BudgetLine::updateOrCreate(
                [
                    'budget_id'    => $budget->id,
                    'account_code' => $code,
                    'month'        => $month,
                ],
                ['amount' => $amount]
            );
        }
    }

    /**
     * @return array<string, float>
     */
    private function budgetTotalsByAccount(int $budgetId, array $months): array
    {
        if ($months === []) {
            return [];
        }

        return BudgetLine::query()
            ->where('budget_id', $budgetId)
            ->whereIn('month', $months)
            ->select('account_code', DB::raw('SUM(amount) as total'))
            ->groupBy('account_code')
            ->pluck('total', 'account_code')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function actualTotalsByAccount(string $dateFrom, string $dateTo): array
    {
        $query = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->where('journal_entries.date', '>=', $dateFrom)
            ->where('journal_entries.date', '<=', $dateTo);
        PostedJournalScope::apply($query);

        $rows = $query
            ->select(
                'accounts.code',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.code', 'accounts.type')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $totals[$row->code] = $row->type === 'income'
                ? ($credit - $debit)
                : ($debit - $credit);
        }

        return $totals;
    }

    /**
     * Calendar months (1–12) within the fiscal year that overlap the report range.
     *
     * @return list<int>
     */
    private function monthsInRange(int $fiscalYear, Carbon $from, Carbon $to): array
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            if ((int) $cursor->year === $fiscalYear) {
                $months[] = (int) $cursor->month;
            }
            $cursor->addMonth();
        }

        return array_values(array_unique($months));
    }

    private function variancePercent(float $budget, float $variance): ?float
    {
        if ($budget == 0.0) {
            return null;
        }

        return round(($variance / $budget) * 100, 1);
    }
}
