<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Sst02ExportService;
use App\Support\MyInvoisGap;
use App\Support\ReportPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales Tax Report
 *
 * Output (collected) tax: from invoices the tenant issued (SST/GST charged
 *                          to customers).
 * Input  (paid) tax     : from bills the tenant received (SST/GST paid to
 *                          suppliers).
 *
 *   Net tax payable      = output tax  − input tax
 *
 *  • Output > Input → tenant owes the tax authority.
 *  • Input  > Output → tenant has reclaimable / receivable tax.
 *
 * Tax amounts come from the `tax_amount` column on each header (already
 * computed at issue time), so we don't have to re-derive from line items.
 *
 * Drafts and voided documents are excluded.
 */
class SalesTaxReportController extends Controller
{
    public function index(Request $request): Response
    {
        $resolved = $this->resolvePeriod($request);
        $start = $resolved['date_from'];
        $end = $resolved['date_to'];
        $data = $this->buildReportData($start, $end);

        return Inertia::render('Reports/SalesTax', [
            'filters' => ['preset' => $resolved['preset'], 'start_date' => $start, 'end_date' => $end],
            ...$data,
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $resolved = $this->resolvePeriod($request);
        $start = $resolved['date_from'];
        $end = $resolved['date_to'];
        $data = $this->buildReportData($start, $end);

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['section', 'rate', 'taxable', 'tax', 'document', 'status']);
            fputcsv($out, ['Pack — output tax', '', $data['pack']['taxable_sales'], $data['pack']['output_tax'], '', '']);
            fputcsv($out, ['Pack — input tax', '', '', $data['pack']['input_tax'], '', '']);
            fputcsv($out, ['Pack — net tax', '', '', $data['pack']['net_tax'], '', '']);
            fputcsv($out, ['Pack — exempt sales', 'Exempt', $data['pack']['exempt_sales'], 0, '', '']);

            foreach ($data['by_rate'] as $row) {
                fputcsv($out, ['Sales by rate', $row['label'], $row['taxable'], $row['tax_collected'], '', '']);
            }

            foreach ($data['myinvois_gaps'] as $row) {
                fputcsv($out, ['MyInvois gap', '', $row['total'], '', $row['invoice_number'], $row['reason']]);
            }
            fclose($out);
        }, "sales-tax-{$start}-to-{$end}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $resolved = $this->resolvePeriod($request);
        $start = $resolved['date_from'];
        $end = $resolved['date_to'];
        $data = $this->buildReportData($start, $end);

        $pdf = Pdf::loadView('pdf.sales-tax', [
            ...$data,
            'company' => $this->reportCompany(),
            'base_currency' => $this->tenantBaseCurrency(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("sales-tax-{$start}-to-{$end}.pdf");
    }

    public function exportSst02(Request $request, Sst02ExportService $sst02): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $resolved = $this->resolvePeriod($request);
        $start = $resolved['date_from'];
        $end = $resolved['date_to'];
        $rows = $sst02->build($start, $end);

        if ($request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('pdf.sst-02-summary', [
                'rows' => $rows,
                'period_from' => $start,
                'period_to' => $end,
                'company' => $this->reportCompany(),
                'base_currency' => $this->tenantBaseCurrency(),
            ])->setPaper('a4', 'portrait');

            return $pdf->download("sst-02-helper-{$start}-to-{$end}.pdf");
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'tax_code',
                'taxable_sales',
                'output_tax',
                'taxable_purchases',
                'input_tax',
                'net_tax',
                'cn_adjustment',
                'dn_adjustment',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['tax_code'],
                    $row['taxable_sales'],
                    $row['output_tax'],
                    $row['taxable_purchases'],
                    $row['input_tax'],
                    $row['net_tax'],
                    $row['cn_adjustment'],
                    $row['dn_adjustment'],
                ]);
            }

            fclose($out);
        }, "sst-02-helper-{$start}-to-{$end}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $preset = $request->input('preset');
        if ($preset === null && ! $request->filled('start_date') && ! $request->filled('end_date')) {
            $preset = 'this_sst_period';
        }

        return ReportPeriod::range(
            $preset,
            $request->input('start_date'),
            $request->input('end_date')
        );
    }

    private function buildReportData(string $start, string $end): array
    {
        $invoiceBase = DB::table('invoices')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->whereNull('deleted_at');

        // ── Output tax (collected on sales) ────────────────────────────────
        $outputTax = (float) (clone $invoiceBase)->sum('tax_amount');
        $outputTax += $this->sumDocumentTax('debit_notes', 'issue_date', $start, $end);
        $outputTax -= $this->sumDocumentTax('credit_notes', 'issue_date', $start, $end);

        $invoiceCount = (int) (clone $invoiceBase)->count();
        $taxableSales = (float) (clone $invoiceBase)->sum('amount_before_tax');
        $taxableSales += $this->sumDocumentTaxable('debit_notes', 'issue_date', $start, $end, 'total_amount', 'tax_amount');
        $taxableSales -= $this->sumDocumentTaxable('credit_notes', 'issue_date', $start, $end, 'total_amount', 'tax_amount');

        // ── Input tax (paid on purchases) ──────────────────────────────────
        $billBase = DB::table('bills')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('bill_date', [$start, $end])
            ->whereNull('deleted_at');

        $inputTax = (float) (clone $billBase)->sum('tax_amount');
        $inputTax += $this->sumDocumentTax('supplier_debit_notes', 'issue_date', $start, $end);
        $inputTax -= $this->sumDocumentTax('supplier_credit_notes', 'issue_date', $start, $end);
        $billCount = (int) (clone $billBase)->count();
        $taxablePurchases = (float) (clone $billBase)->sum(DB::raw('total_amount - tax_amount'));

        // ── Breakdown by tax code (for sales, derived from line items) ─────
        $byCode = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('tax_codes as tc', 'tc.id', '=', 'ii.tax_code_id')
            ->select(
                DB::raw("COALESCE(tc.code, CASE WHEN ii.tax_rate = 0 THEN 'ES' WHEN ii.tax_rate >= 9.5 THEN 'ST-10' WHEN ii.tax_rate >= 7.5 THEN 'SR-8' ELSE CONCAT(ii.tax_rate, '%') END) as tax_code"),
                DB::raw('COALESCE(tc.rate, ii.tax_rate) as tax_rate'),
                DB::raw('SUM(ii.amount) as taxable'),
                DB::raw('SUM(ii.amount * (COALESCE(tc.rate, ii.tax_rate) / 100)) as tax_collected'),
                DB::raw('COUNT(DISTINCT i.id) as invoice_count')
            )
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereNull('i.deleted_at')
            ->whereNull('ii.deleted_at')
            ->groupBy('tax_code', DB::raw('COALESCE(tc.rate, ii.tax_rate)'))
            ->orderBy('tax_code')
            ->get()
            ->map(fn ($r) => [
                'tax_code' => (string) $r->tax_code,
                'tax_rate' => (float) $r->tax_rate,
                'label' => (float) $r->tax_rate === 0.0 ? ((string) $r->tax_code === 'ZRL' ? 'ZRL' : 'Exempt') : ((string) $r->tax_code),
                'taxable' => round((float) $r->taxable, 2),
                'tax_collected' => round((float) $r->tax_collected, 2),
                'invoice_count' => (int) $r->invoice_count,
            ])
            ->values()
            ->all();

        $byRate = $byCode;

        $exemptSales = collect($byCode)->first(fn ($row) => in_array($row['tax_code'], ['ES', 'ZRL'], true) || $row['tax_rate'] === 0.0)['taxable'] ?? 0.0;

        // ── Per-invoice list (so the user can audit any number) ────────────
        $invoiceList = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->select([
                'i.id', 'i.invoice_number', 'i.issue_date', 'i.amount_before_tax',
                'i.tax_amount', 'i.total_amount', 'i.currency', 'c.name as customer_name',
            ])
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereNull('i.deleted_at')
            ->where('i.tax_amount', '>', 0)
            ->orderBy('i.issue_date')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'invoice_number' => $r->invoice_number,
                'issue_date' => $r->issue_date,
                'customer' => $r->customer_name ?? '—',
                'taxable' => round((float) $r->amount_before_tax, 2),
                'tax' => round((float) $r->tax_amount, 2),
                'total' => round((float) $r->total_amount, 2),
                'currency' => $r->currency ?? 'MYR',
            ])
            ->all();

        $billList = DB::table('bills as b')
            ->leftJoin('suppliers as s', 's.id', '=', 'b.supplier_id')
            ->select([
                'b.id', 'b.bill_number', 'b.bill_date', 'b.total_amount',
                'b.tax_amount', 'b.currency', 's.name as supplier_name',
            ])
            ->whereNotIn('b.status', ['draft', 'void'])
            ->whereBetween('b.bill_date', [$start, $end])
            ->whereNull('b.deleted_at')
            ->where('b.tax_amount', '>', 0)
            ->orderBy('b.bill_date')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'bill_number' => $r->bill_number,
                'bill_date' => $r->bill_date,
                'supplier' => $r->supplier_name ?? '—',
                'taxable' => round((float) $r->total_amount - (float) $r->tax_amount, 2),
                'tax' => round((float) $r->tax_amount, 2),
                'total' => round((float) $r->total_amount, 2),
                'currency' => $r->currency ?? 'MYR',
            ])
            ->all();

        [$gaps, $gapCounts] = $this->myinvoisGaps($start, $end);
        $pack = [
            'period_from' => $start,
            'period_to' => $end,
            'output_tax' => round($outputTax, 2),
            'input_tax' => round($inputTax, 2),
            'net_tax' => round($outputTax - $inputTax, 2),
            'exempt_sales' => round((float) $exemptSales, 2),
            'taxable_sales' => round($taxableSales, 2),
        ];

        return [
            'pack' => $pack,
            'output_tax' => $pack['output_tax'],
            'input_tax' => $pack['input_tax'],
            'net_tax' => $pack['net_tax'],
            'invoice_count' => $invoiceCount,
            'bill_count' => $billCount,
            'taxable_sales' => $pack['taxable_sales'],
            'taxable_purchases' => round($taxablePurchases, 2),
            'by_rate' => $byRate,
            'by_code' => $byCode,
            'invoices' => $invoiceList,
            'bills' => $billList,
            'myinvois_gaps' => $gaps,
            'gap_counts' => $gapCounts,
        ];
    }

    private function myinvoisGaps(string $start, string $end): array
    {
        $query = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereNull('i.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('i.lhdn_uuid')
                    ->orWhereRaw("TRIM(i.lhdn_uuid) = ''")
                    ->orWhereIn('i.lhdn_status', ['pending', 'rejected', 'invalid']);
            });

        $counts = (clone $query)->selectRaw(
            "SUM(CASE WHEN i.lhdn_uuid IS NULL OR TRIM(i.lhdn_uuid) = '' THEN 1 ELSE 0 END) AS missing,
             SUM(CASE WHEN i.lhdn_uuid IS NOT NULL AND TRIM(i.lhdn_uuid) <> '' AND i.lhdn_status = 'pending' THEN 1 ELSE 0 END) AS pending,
             SUM(CASE WHEN i.lhdn_uuid IS NOT NULL AND TRIM(i.lhdn_uuid) <> '' AND i.lhdn_status IN ('rejected', 'invalid') THEN 1 ELSE 0 END) AS rejected"
        )->first();

        $gaps = $query->select([
            'i.id',
            'i.invoice_number',
            'i.issue_date',
            'i.total_amount',
            'i.lhdn_uuid',
            'i.lhdn_status',
            'c.name as customer_name',
        ])
            ->orderBy('i.issue_date')
            ->orderBy('i.invoice_number')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'invoice_number' => $row->invoice_number,
                'issue_date' => $row->issue_date,
                'customer' => $row->customer_name ?? '—',
                'total' => round((float) $row->total_amount, 2),
                'lhdn_status' => $row->lhdn_status,
                'reason' => MyInvoisGap::myinvoisGapReason($row->lhdn_uuid, $row->lhdn_status),
            ])
            ->all();

        return [$gaps, [
            'missing' => (int) ($counts->missing ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
        ]];
    }

    private function reportCompany(): array
    {
        $user = request()->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            $company = ($tenant?->data ?? [])['company'] ?? [];
            $name = $company['display_name'] ?? $company['legal_name'] ?? config('invoice.company.name');
            $addressParts = array_filter([
                $company['street'] ?? '',
                $company['city'] ?? '',
                $company['state'] ?? '',
                $company['postcode'] ?? '',
                $company['country'] ?? '',
            ]);

            return [
                'name' => $name ?: config('invoice.company.name'),
                'address' => implode(', ', $addressParts) ?: config('invoice.company.address'),
            ];
        }

        return config('invoice.company');
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }

    private function sumDocumentTax(string $table, string $dateColumn, string $start, string $end): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return 0.0;
        }

        return (float) DB::table($table)
            ->where('status', '!=', 'void')
            ->whereBetween($dateColumn, [$start, $end])
            ->whereNull('deleted_at')
            ->sum('tax_amount');
    }

    private function sumDocumentTaxable(
        string $table,
        string $dateColumn,
        string $start,
        string $end,
        string $totalColumn,
        string $taxColumn
    ): float {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return 0.0;
        }

        return (float) DB::table($table)
            ->where('status', '!=', 'void')
            ->whereBetween($dateColumn, [$start, $end])
            ->whereNull('deleted_at')
            ->sum(DB::raw("{$totalColumn} - {$taxColumn}"));
    }
}
