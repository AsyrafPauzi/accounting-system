<?php

namespace App\Http\Controllers;

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
        $asAtDate = $request->input('as_at_date', now()->format('Y-m-d'));

        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['asset', 'liability', 'equity'])
            ->whereDate('journal_entries.date', '<=', $asAtDate)
            ->select(
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
            $amount = $row->type === 'asset'
                ? ($debit - $credit)
                : ($credit - $debit);

            $line = [
                'code' => $row->code,
                'name' => $row->name,
                'amount' => round($amount, 2),
            ];

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

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $balanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;

        return Inertia::render('Reports/BalanceSheet', [
            'asset_accounts' => $assetAccounts,
            'liability_accounts' => $liabilityAccounts,
            'equity_accounts' => $equityAccounts,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_and_equity' => round($totalLiabilitiesAndEquity, 2),
            'balanced' => $balanced,
            'filters' => [
                'as_at_date' => $asAtDate,
            ],
        ]);
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
            ->whereDate('journal_entries.date', '<=', $asAtDate)
            ->select(
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

        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $balanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;

        return [
            'asset_accounts' => $assetAccounts,
            'liability_accounts' => $liabilityAccounts,
            'equity_accounts' => $equityAccounts,
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
        $asAtDate = $request->input('as_at_date', now()->format('Y-m-d'));
        $data = $this->buildBalanceSheetData($asAtDate);

        $filename = 'balance-sheet-as-at-' . $asAtDate . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'code', 'name', 'amount']);
            foreach ($data['asset_accounts'] as $row) {
                fputcsv($out, ['Assets', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, ['', '', 'Total assets', $data['total_assets']]);
            foreach ($data['liability_accounts'] as $row) {
                fputcsv($out, ['Liabilities', $row['code'], $row['name'], $row['amount']]);
            }
            foreach ($data['equity_accounts'] as $row) {
                fputcsv($out, ['Equity', $row['code'], $row['name'], $row['amount']]);
            }
            fputcsv($out, ['', '', 'Total liabilities + equity', $data['total_liabilities_and_equity']]);
            fputcsv($out, ['', '', 'Balanced', $data['balanced'] ? 'Yes' : 'No']);
            fclose($out);
        }, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $asAtDate = $request->input('as_at_date', now()->format('Y-m-d'));
        $data = $this->buildBalanceSheetData($asAtDate);
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
