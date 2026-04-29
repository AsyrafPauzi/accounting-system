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

        $totalSales = $salesByCustomer->sum('total_sales');

        return Inertia::render('Reports/Sales', [
            'sales' => $salesByCustomer,
            'total_sales' => round($totalSales, 2),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
