<?php

namespace App\Http\Controllers;

use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer Credits report
 *
 * Lists every credit note (deposits / customer credits held on account) per
 * customer with its current outstanding balance.
 *
 *   - "Issued"   = credit notes the tenant has raised (status != void).
 *   - "Applied"  = portion already used to settle invoices. Today the system
 *                  doesn't track partial applications explicitly, so we treat
 *                  status = 'applied' as fully applied, and any other status
 *                  (open/draft/sent) as fully open.
 *   - "Open"     = sum of credit notes that are not yet applied → cash the
 *                  tenant still owes the customer (or that the customer can
 *                  spend on future invoices).
 *
 * Per Wave: the customer-facing view of "Customer Credits" is an account-by-
 * account list of how much credit the tenant is holding for each customer.
 */
class CustomerCreditsController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_of_date')
        );
        $asOf = $resolved['as_of'];

        $rows = DB::table('credit_notes as cn')
            ->leftJoin('customers as c', 'c.id', '=', 'cn.customer_id')
            ->select([
                'cn.customer_id',
                'c.name as customer_name',
                'c.email as customer_email',
                DB::raw("SUM(cn.applied_amount) as applied_amount"),
                DB::raw("SUM(cn.total_amount - cn.applied_amount) as open_amount"),
                DB::raw('SUM(cn.total_amount) as total_issued'),
                DB::raw('COUNT(cn.id) as note_count'),
                DB::raw('MAX(cn.issue_date) as last_issued_at'),
            ])
            ->where('cn.status', '!=', 'void')
            ->whereDate('cn.issue_date', '<=', $asOf)
            ->whereRaw('(cn.total_amount - cn.applied_amount) > 0')
            ->whereNull('cn.deleted_at')
            ->groupBy('cn.customer_id', 'c.name', 'c.email')
            ->orderByDesc('open_amount')
            ->get()
            ->map(fn ($r) => [
                'customer_id'     => $r->customer_id,
                'customer_name'   => $r->customer_name ?? '— Deleted customer —',
                'customer_email'  => $r->customer_email,
                'open_amount'     => round((float) $r->open_amount, 2),
                'applied_amount'  => round((float) $r->applied_amount, 2),
                'total_issued'    => round((float) $r->total_issued, 2),
                'note_count'      => (int) $r->note_count,
                'last_issued_at'  => $r->last_issued_at,
            ])
            ->all();

        $details = DB::table('credit_notes as cn')
            ->leftJoin('customers as c', 'c.id', '=', 'cn.customer_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'cn.invoice_id')
            ->select([
                'cn.id', 'cn.cn_number', 'cn.issue_date', 'cn.total_amount',
                'cn.status', 'cn.reason_code', 'cn.reason_description',
                'cn.customer_id',
                'c.name as customer_name',
                'i.invoice_number',
            ])
            ->where('cn.status', '!=', 'void')
            ->whereDate('cn.issue_date', '<=', $asOf)
            ->whereRaw('(cn.total_amount - cn.applied_amount) > 0')
            ->whereNull('cn.deleted_at')
            ->orderByDesc('cn.issue_date')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'id'              => $r->id,
                'cn_number'       => $r->cn_number,
                'issue_date'      => $r->issue_date,
                'amount'          => round((float) $r->total_amount, 2),
                'status'          => $r->status,
                'reason'          => $r->reason_description ?: $r->reason_code,
                'customer_id'     => $r->customer_id,
                'customer_name'   => $r->customer_name ?? '— Deleted customer —',
                'invoice_number'  => $r->invoice_number,
            ])
            ->all();

        $totals = [
            'open_amount'    => round(array_sum(array_column($rows, 'open_amount')), 2),
            'applied_amount' => round(array_sum(array_column($rows, 'applied_amount')), 2),
            'total_issued'   => round(array_sum(array_column($rows, 'total_issued')), 2),
            'note_count'     => (int) array_sum(array_column($rows, 'note_count')),
            'customer_count' => count($rows),
        ];

        return Inertia::render('Reports/CustomerCredits', [
            'filters'       => ['preset' => $resolved['preset'], 'as_of_date' => $asOf],
            'rows'          => $rows,
            'totals'        => $totals,
            'details'       => $details,
            'base_currency' => $this->tenantBaseCurrency(),
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
