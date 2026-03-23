<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitAndLossController extends Controller
{
    /**
     * Real-time P&L: Income vs Expenses from the general ledger.
     */
    public function index(Request $request): Response
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->whereDate('journal_entries.date', '>=', $dateFrom)
            ->whereDate('journal_entries.date', '<=', $dateTo)
            ->select(
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->get();

        $revenueAccounts = [];
        $expenseAccounts = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $amount = $row->type === 'income' ? ($credit - $debit) : ($debit - $credit);

            $line = [
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round($amount, 2),
            ];

            if ($row->type === 'income') {
                $revenueAccounts[] = $line;
                $totalRevenue += $amount;
            } else {
                $expenseAccounts[] = $line;
                $totalExpenses += $amount;
            }
        }

        $netProfit = $totalRevenue - $totalExpenses;

        return Inertia::render('Reports/ProfitAndLoss', [
            'revenue_accounts' => $revenueAccounts,
            'expense_accounts' => $expenseAccounts,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Build P&L data for the given date range.
     */
    protected function buildPlData(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->whereDate('journal_entries.date', '>=', $dateFrom)
            ->whereDate('journal_entries.date', '<=', $dateTo)
            ->select(
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->get();

        $revenueAccounts = [];
        $expenseAccounts = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $amount = $row->type === 'income' ? ($credit - $debit) : ($debit - $credit);
            $line = ['code' => $row->code, 'name' => $row->name, 'amount' => round($amount, 2)];
            if ($row->type === 'income') {
                $revenueAccounts[] = $line;
                $totalRevenue += $amount;
            } else {
                $expenseAccounts[] = $line;
                $totalExpenses += $amount;
            }
        }

        $netProfit = $totalRevenue - $totalExpenses;
        return [
            'revenue_accounts' => $revenueAccounts,
            'expense_accounts' => $expenseAccounts,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $data = $this->buildPlData($dateFrom, $dateTo);

        $filename = 'profit-and-loss-' . $dateFrom . '-to-' . $dateTo . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'code', 'name', 'amount']);
            foreach ($data['revenue_accounts'] as $row) {
                fputcsv($out, ['Revenue', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, ['', '', 'Total revenue', $data['total_revenue']]);
            foreach ($data['expense_accounts'] as $row) {
                fputcsv($out, ['Expense', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, ['', '', 'Total expenses', $data['total_expenses']]);
            fputcsv($out, ['', '', 'Net profit/loss', $data['net_profit']]);
            fclose($out);
        }, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $data = $this->buildPlData($dateFrom, $dateTo);
        $company = $this->reportCompany();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.profit-and-loss', [
            'revenue_accounts' => $data['revenue_accounts'],
            'expense_accounts' => $data['expense_accounts'],
            'total_revenue' => $data['total_revenue'],
            'total_expenses' => $data['total_expenses'],
            'net_profit' => $data['net_profit'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('profit-and-loss-' . $dateFrom . '-to-' . $dateTo . '.pdf');
    }

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
