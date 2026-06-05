<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->input('start_date', now()->startOfQuarter()->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end_date', now()->toDateString()))->toDateString();

        // ── Output tax (collected on sales) ────────────────────────────────
        $outputTax = (float) DB::table('invoices')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->whereNull('deleted_at')
            ->sum('tax_amount');

        $invoiceCount = (int) DB::table('invoices')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->whereNull('deleted_at')
            ->count();

        $taxableSales = (float) DB::table('invoices')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->whereNull('deleted_at')
            ->sum('amount_before_tax');

        // ── Input tax (paid on purchases) ──────────────────────────────────
        $inputTax = (float) DB::table('bills')
            ->where('status', '!=', 'void')
            ->whereBetween('bill_date', [$start, $end])
            ->whereNull('deleted_at')
            ->sum('tax_amount');

        $billCount = (int) DB::table('bills')
            ->where('status', '!=', 'void')
            ->whereBetween('bill_date', [$start, $end])
            ->whereNull('deleted_at')
            ->count();

        $taxablePurchases = (float) DB::table('bills')
            ->where('status', '!=', 'void')
            ->whereBetween('bill_date', [$start, $end])
            ->whereNull('deleted_at')
            ->sum(DB::raw('total_amount - tax_amount'));

        // ── Breakdown by tax rate (for sales, derived from line items) ─────
        $byRate = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->select(
                'ii.tax_rate',
                DB::raw('SUM(ii.amount) as taxable'),
                DB::raw('SUM(ii.amount * (ii.tax_rate / 100)) as tax_collected'),
                DB::raw('COUNT(DISTINCT i.id) as invoice_count')
            )
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereNull('i.deleted_at')
            ->whereNull('ii.deleted_at')
            ->groupBy('ii.tax_rate')
            ->orderBy('ii.tax_rate')
            ->get()
            ->map(fn ($r) => [
                'tax_rate'      => (float) $r->tax_rate,
                'taxable'       => round((float) $r->taxable, 2),
                'tax_collected' => round((float) $r->tax_collected, 2),
                'invoice_count' => (int) $r->invoice_count,
            ])
            ->values()
            ->all();

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
                'id'             => $r->id,
                'invoice_number' => $r->invoice_number,
                'issue_date'     => $r->issue_date,
                'customer'       => $r->customer_name ?? '—',
                'taxable'        => round((float) $r->amount_before_tax, 2),
                'tax'            => round((float) $r->tax_amount, 2),
                'total'          => round((float) $r->total_amount, 2),
                'currency'       => $r->currency ?? 'MYR',
            ])
            ->all();

        $billList = DB::table('bills as b')
            ->leftJoin('suppliers as s', 's.id', '=', 'b.supplier_id')
            ->select([
                'b.id', 'b.bill_number', 'b.bill_date', 'b.total_amount',
                'b.tax_amount', 'b.currency', 's.name as supplier_name',
            ])
            ->where('b.status', '!=', 'void')
            ->whereBetween('b.bill_date', [$start, $end])
            ->whereNull('b.deleted_at')
            ->where('b.tax_amount', '>', 0)
            ->orderBy('b.bill_date')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'bill_number' => $r->bill_number,
                'bill_date'   => $r->bill_date,
                'supplier'    => $r->supplier_name ?? '—',
                'taxable'     => round((float) $r->total_amount - (float) $r->tax_amount, 2),
                'tax'         => round((float) $r->tax_amount, 2),
                'total'       => round((float) $r->total_amount, 2),
                'currency'    => $r->currency ?? 'MYR',
            ])
            ->all();

        return Inertia::render('Reports/SalesTax', [
            'filters'           => ['start_date' => $start, 'end_date' => $end],
            'output_tax'        => round($outputTax, 2),
            'input_tax'         => round($inputTax, 2),
            'net_tax'           => round($outputTax - $inputTax, 2),
            'invoice_count'     => $invoiceCount,
            'bill_count'        => $billCount,
            'taxable_sales'     => round($taxableSales, 2),
            'taxable_purchases' => round($taxablePurchases, 2),
            'by_rate'           => $byRate,
            'invoices'          => $invoiceList,
            'bills'             => $billList,
            'base_currency'     => $this->tenantBaseCurrency(),
        ]);
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = \App\Models\Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }
        return 'MYR';
    }
}
