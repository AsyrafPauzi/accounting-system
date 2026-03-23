<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashflowSummaryController extends Controller
{
    /**
     * Cashflow Summary: Total Sales vs Total Expenses with optional date range and chart by month.
     */
    public function index(Request $request): Response
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (! $dateFrom || ! $dateTo) {
            $end = now()->startOfDay();
            $start = now()->subMonths(11)->startOfMonth();
            $dateFrom = $start->format('Y-m-d');
            $dateTo = $end->format('Y-m-d');
        } else {
            $start = Carbon::parse($dateFrom)->startOfDay();
            $end = Carbon::parse($dateTo)->endOfDay();
        }

        $postedStatuses = ['unpaid', 'partially paid', 'paid'];

        // Sales: posted invoices (revenue) in period by issue_date
        $salesQuery = Invoice::whereIn('status', $postedStatuses)
            ->whereBetween('issue_date', [$dateFrom, $dateTo]);
        $totalSales = (float) (clone $salesQuery)->sum('total_amount');

        // Expenses: posted bills in period by bill_date
        $expensesQuery = Bill::whereIn('status', $postedStatuses)
            ->whereBetween('bill_date', [$dateFrom, $dateTo]);
        $totalExpenses = (float) (clone $expensesQuery)->sum('total_amount');

        $netCashflow = round($totalSales - $totalExpenses, 2);
        $totalSales = round($totalSales, 2);
        $totalExpenses = round($totalExpenses, 2);

        // Build chart data by month
        $chartData = $this->buildChartData($start, $end, $dateFrom, $dateTo, $postedStatuses);

        return Inertia::render('CashflowSummary/Index', [
            'summary' => [
                'total_sales' => $totalSales,
                'total_expenses' => $totalExpenses,
                'net_cashflow' => $netCashflow,
            ],
            'chartData' => $chartData,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    private function buildChartData(Carbon $start, Carbon $end, string $dateFrom, string $dateTo, array $postedStatuses): array
    {
        $chartData = [];
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $monthKey = $cursor->format('Y-m');
            $monthStart = $cursor->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $cursor->copy()->endOfMonth()->format('Y-m-d');

            $sales = (float) Invoice::whereIn('status', $postedStatuses)
                ->whereBetween('issue_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $expenses = (float) Bill::whereIn('status', $postedStatuses)
                ->whereBetween('bill_date', [$monthStart, $monthEnd])
                ->sum('total_amount');

            $chartData[] = [
                'month' => $monthKey,
                'month_label' => $cursor->format('M Y'),
                'sales' => round($sales, 2),
                'expenses' => round($expenses, 2),
                'net' => round($sales - $expenses, 2),
            ];

            $cursor->addMonth();
        }

        return $chartData;
    }
}
