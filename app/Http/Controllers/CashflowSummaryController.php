<?php

namespace App\Http\Controllers;

use App\Support\CashMovement;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashflowSummaryController extends Controller
{
    /**
     * Cash movement through posted bank and cash journal items.
     */
    public function index(Request $request): Response
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $chartData = CashMovement::chartForPeriod($dateFrom, $dateTo);

        return Inertia::render('CashflowSummary/Index', [
            'summary' => CashMovement::totals($chartData),
            'chartData' => $chartData,
            'filters' => [
                'preset' => $resolved['preset'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
