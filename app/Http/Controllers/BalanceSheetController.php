<?php

namespace App\Http\Controllers;

use App\Support\CurrentYearEarnings;
use App\Support\PostedJournalScope;
use App\Support\ReportCompare;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetController extends Controller
{
    /**
     * Real-time Balance Sheet: Assets vs Liabilities & Equity as at a date.
     */
    public function index(Request $request): Response
    {
        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_at_date')
        );
        $asAtDate = $resolved['as_of'];
        $compare = $this->resolveCompare($request);
        $data = $this->buildComparedBalanceSheetData($asAtDate, $compare);

        return Inertia::render('Reports/BalanceSheet', [
            ...$data,
            'filters' => [
                'preset' => $resolved['preset'],
                'as_at_date' => $asAtDate,
                'compare' => $compare,
            ],
        ]);
    }

    protected function buildComparedBalanceSheetData(string $asAtDate, string $compare): array
    {
        $current = $this->buildBalanceSheetData($asAtDate);
        $current['compare'] = $compare;
        $current['compare_label'] = null;
        $current['compare_as_at_date'] = null;
        $current['compare_total_assets'] = null;
        $current['compare_total_liabilities'] = null;
        $current['compare_total_equity'] = null;
        $current['assets_variance'] = null;
        $current['liabilities_variance'] = null;
        $current['equity_variance'] = null;

        if ($compare === 'none') {
            return $current;
        }

        $compareDate = ReportPeriod::compareAsOfDate($asAtDate, $compare);
        if (! $compareDate) {
            return $current;
        }

        $prior = $this->buildBalanceSheetData($compareDate);
        $current['asset_accounts'] = ReportCompare::mergeLines($current['asset_accounts'], $prior['asset_accounts']);
        $current['liability_accounts'] = ReportCompare::mergeLines($current['liability_accounts'], $prior['liability_accounts']);
        $current['equity_accounts'] = ReportCompare::mergeLines($current['equity_accounts'], $prior['equity_accounts']);
        $current['compare_label'] = $compare === 'last_year' ? 'Same date last year' : 'Prior month end';
        $current['compare_as_at_date'] = $compareDate;
        $current['compare_total_assets'] = $prior['total_assets'];
        $current['compare_total_liabilities'] = $prior['total_liabilities'];
        $current['compare_total_equity'] = $prior['total_equity'];
        $current['assets_variance'] = round($current['total_assets'] - $prior['total_assets'], 2);
        $current['liabilities_variance'] = round($current['total_liabilities'] - $prior['total_liabilities'], 2);
        $current['equity_variance'] = round($current['total_equity'] - $prior['total_equity'], 2);

        return $current;
    }

    protected function resolveCompare(Request $request): string
    {
        $compare = $request->input('compare', 'previous');

        return in_array($compare, ['previous', 'last_year', 'none'], true) ? $compare : 'previous';
    }

    /**
     * Build balance sheet data as at a date.
     */
    protected function buildBalanceSheetData(string $asAtDate): array
    {
        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->where('journal_entries.date', '<=', $asAtDate);
        PostedJournalScope::apply($rows);
        $rows = $rows->select(
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->get();

        $assetAccounts = [];
        $liabilityAccounts = [];
        $equityAccounts = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $amount = $row->type === 'asset' ? ($debit - $credit) : ($credit - $debit);
            $line = ['code' => $row->code, 'name' => $row->name, 'amount' => round($amount, 2)];
            if ($row->type === 'asset') {
                $assetAccounts[] = $line;
                $totalAssets += $amount;
            } elseif ($row->type === 'liability') {
                $liabilityAccounts[] = $line;
                $totalLiabilities += $amount;
            } else {
                $equityAccounts[] = $line;
                $totalEquity += $amount;
            }
        }

        $fyStartMonth = (int) (tenant()?->financial_year_start_month ?? 1);
        $currentYearEarnings = CurrentYearEarnings::amountAsOf($asAtDate, $fyStartMonth);
        if (abs($currentYearEarnings) >= 0.01) {
            $equityAccounts[] = [
                'code' => '—',
                'name' => 'Current year earnings',
                'amount' => $currentYearEarnings,
            ];
            $totalEquity += $currentYearEarnings;
        }

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $balanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;

        return [
            'asset_accounts' => $assetAccounts,
            'liability_accounts' => $liabilityAccounts,
            'equity_accounts' => $equityAccounts,
            'current_year_earnings' => $currentYearEarnings,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_and_equity' => round($totalLiabilitiesAndEquity, 2),
            'balanced' => $balanced,
            'as_at_date' => $asAtDate,
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_at_date')
        );
        $asAtDate = $resolved['as_of'];
        $data = $this->buildComparedBalanceSheetData($asAtDate, $this->resolveCompare($request));

        $filename = 'balance-sheet-as-at-' . $asAtDate . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $hasCompare = ($data['compare'] ?? 'none') !== 'none';
            fputcsv($out, $hasCompare
                ? ['section', 'code', 'name', 'amount', 'compare_amount', 'variance']
                : ['section', 'code', 'name', 'amount']);
            foreach ($data['asset_accounts'] as $row) {
                fputcsv($out, $hasCompare
                    ? ['Assets', $row['code'], $row['name'], $row['amount'], $row['compare_amount'] ?? '', $row['variance'] ?? '']
                    : ['Assets', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, $hasCompare
                ? ['', '', 'Total assets', $data['total_assets'], $data['compare_total_assets'] ?? '', $data['assets_variance'] ?? '']
                : ['', '', 'Total assets', $data['total_assets']]);
            foreach ($data['liability_accounts'] as $row) {
                fputcsv($out, $hasCompare
                    ? ['Liabilities', $row['code'], $row['name'], $row['amount'], $row['compare_amount'] ?? '', $row['variance'] ?? '']
                    : ['Liabilities', $row['code'], $row['name'], $row['amount']]);
            }
            foreach ($data['equity_accounts'] as $row) {
                fputcsv($out, $hasCompare
                    ? ['Equity', $row['code'], $row['name'], $row['amount'], $row['compare_amount'] ?? '', $row['variance'] ?? '']
                    : ['Equity', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, $hasCompare
                ? ['', '', 'Total liabilities + equity', $data['total_liabilities_and_equity'], '', '']
                : ['', '', 'Total liabilities + equity', $data['total_liabilities_and_equity']]);
            fputcsv($out, ['', '', 'Balanced', $data['balanced'] ? 'Yes' : 'No']);
            fclose($out);
        }, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_at_date')
        );
        $asAtDate = $resolved['as_of'];
        $data = $this->buildComparedBalanceSheetData($asAtDate, $this->resolveCompare($request));
        $company = $this->reportCompany();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.balance-sheet', [
            'asset_accounts' => $data['asset_accounts'],
            'liability_accounts' => $data['liability_accounts'],
            'equity_accounts' => $data['equity_accounts'],
            'total_assets' => $data['total_assets'],
            'total_liabilities' => $data['total_liabilities'],
            'total_equity' => $data['total_equity'],
            'total_liabilities_and_equity' => $data['total_liabilities_and_equity'],
            'balanced' => $data['balanced'],
            'as_at_date' => $asAtDate,
            'compare_label' => $data['compare_label'] ?? null,
            'compare_as_at_date' => $data['compare_as_at_date'] ?? null,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('balance-sheet-as-at-' . $asAtDate . '.pdf');
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
