<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\BudgetVsActualService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BudgetVsActualController extends Controller
{
    public function __construct(private BudgetVsActualService $budgets) {}

    public function index(Request $request): Response
    {
        $this->authorize('reports.profit-loss');

        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];

        $fiscalYear = (int) ($request->input('fiscal_year') ?: (int) date('Y', strtotime($dateFrom)));
        $budget = Budget::query()->where('fiscal_year', $fiscalYear)->first();
        $report = $budget
            ? $this->budgets->build($budget, $dateFrom, $dateTo)
            : $this->emptyReport($fiscalYear, $dateFrom, $dateTo);

        return Inertia::render('Reports/BudgetVsActual', [
            ...$report,
            'budget' => $budget ? [
                'id'           => $budget->id,
                'name'         => $budget->name,
                'fiscal_year'  => $budget->fiscal_year,
            ] : null,
            'filters' => [
                'preset'      => $resolved['preset'],
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'fiscal_year' => $fiscalYear,
            ],
            'yearOptions' => range(now()->year + 1, now()->year - 3),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('reports.profit-loss');

        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $fiscalYear = (int) ($request->input('fiscal_year') ?: (int) date('Y', strtotime($dateFrom)));

        $budget = Budget::query()->where('fiscal_year', $fiscalYear)->firstOrFail();
        $report = $this->budgets->build($budget, $dateFrom, $dateTo);

        $filename = 'budget-vs-actual-'.$fiscalYear.'-'.$dateFrom.'-to-'.$dateTo.'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Section', 'Account code', 'Account name', 'Budget', 'Actual', 'Variance', 'Variance %']);

            foreach ($report['revenue_rows'] as $row) {
                fputcsv($out, [
                    'Revenue',
                    $row['code'],
                    $row['name'],
                    number_format($row['budget'], 2, '.', ''),
                    number_format($row['actual'], 2, '.', ''),
                    number_format($row['variance'], 2, '.', ''),
                    $row['variance_pct'] === null ? '' : number_format($row['variance_pct'], 1, '.', '').'%',
                ]);
            }

            foreach ($report['expense_rows'] as $row) {
                fputcsv($out, [
                    'Expense',
                    $row['code'],
                    $row['name'],
                    number_format($row['budget'], 2, '.', ''),
                    number_format($row['actual'], 2, '.', ''),
                    number_format($row['variance'], 2, '.', ''),
                    $row['variance_pct'] === null ? '' : number_format($row['variance_pct'], 1, '.', '').'%',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Revenue total', '', '', number_format($report['total_budget_revenue'], 2, '.', ''), number_format($report['total_actual_revenue'], 2, '.', ''), number_format($report['total_variance_revenue'], 2, '.', ''), '']);
            fputcsv($out, ['Expense total', '', '', number_format($report['total_budget_expense'], 2, '.', ''), number_format($report['total_actual_expense'], 2, '.', ''), number_format($report['total_variance_expense'], 2, '.', ''), '']);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(int $fiscalYear, string $dateFrom, string $dateTo): array
    {
        return [
            'fiscal_year'            => $fiscalYear,
            'date_from'              => $dateFrom,
            'date_to'                => $dateTo,
            'revenue_rows'           => [],
            'expense_rows'           => [],
            'total_budget_revenue'   => 0.0,
            'total_actual_revenue'   => 0.0,
            'total_variance_revenue' => 0.0,
            'total_budget_expense'   => 0.0,
            'total_actual_expense'   => 0.0,
            'total_variance_expense' => 0.0,
        ];
    }
}
