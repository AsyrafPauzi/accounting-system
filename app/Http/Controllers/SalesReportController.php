<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    /**
     * Sales Report: Summary of sales by customer and product/service.
     */
    public function index(Request $request): Response
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $salesByCustomer = Invoice::with('customer')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->select('customer_id', DB::raw('SUM(total_amount) as total_sales'), DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('customer_id')
            ->get()
            ->map(function ($item) {
                return [
                    'customer_name' => $item->customer->name ?? 'Unknown',
                    'total_sales' => round($item->total_sales, 2),
                    'invoice_count' => $item->invoice_count,
                ];
            });

        $salesByProduct = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->whereBetween('i.issue_date', [$startDate, $endDate])
            ->where('i.status', '!=', 'void')
            ->whereNull('i.deleted_at')
            ->whereNull('ii.deleted_at')
            ->groupBy('ii.product_id', 'p.name', 'ii.description')
            ->select([
                DB::raw("COALESCE(p.name, ii.description, 'Uncategorised') as product_name"),
                DB::raw('SUM(ii.amount) as total_sales'),
                DB::raw('SUM(ii.quantity) as quantity'),
                DB::raw('COUNT(DISTINCT i.id) as invoice_count'),
            ])
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($r) => [
                'product_name'  => $r->product_name,
                'total_sales'   => round((float) $r->total_sales, 2),
                'quantity'      => round((float) $r->quantity, 2),
                'invoice_count' => (int) $r->invoice_count,
            ]);

        $totalSales = $salesByCustomer->sum('total_sales');

        return Inertia::render('Reports/Sales', [
            'sales' => $salesByCustomer,
            'sales_by_product' => $salesByProduct,
            'total_sales' => round($totalSales, 2),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
