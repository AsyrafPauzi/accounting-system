<?php

namespace App\Http\Controllers;

use App\Services\CashFlowStatementService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowStatementController extends Controller
{
    public function __construct(
        private CashFlowStatementService $cashFlow,
        private ProfitAndLossController $profitAndLoss,
    ) {}

    public function index(Request $request): Response
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];

        $pl = $this->profitAndLoss->buildPlDataPublic($dateFrom, $dateTo, 'accrual');
        $data = $this->cashFlow->build($dateFrom, $dateTo, (float) $pl['net_profit']);

        return Inertia::render('Reports/CashFlowStatement', [
            ...$data,
            'filters' => [
                'preset'    => $resolved['preset'],
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $pl = $this->profitAndLoss->buildPlDataPublic($dateFrom, $dateTo, 'accrual');
        $data = $this->cashFlow->build($dateFrom, $dateTo, (float) $pl['net_profit']);

        $filename = 'cash-flow-statement-'.$dateFrom.'-to-'.$dateTo.'.csv';

        return new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'label', 'amount']);
            fputcsv($out, ['Operating', 'Net profit', $data['net_profit']]);
            foreach ($data['operating_adjustments'] as $line) {
                fputcsv($out, ['Operating', 'Change in '.$line['name'], $line['amount']]);
            }
            fputcsv($out, ['Operating', 'Net cash from operating', $data['net_cash_operating']]);
            foreach ($data['investing_lines'] as $line) {
                fputcsv($out, ['Investing', 'Change in '.$line['name'], $line['amount']]);
            }
            fputcsv($out, ['Investing', 'Net cash from investing', $data['net_cash_investing']]);
            foreach ($data['financing_lines'] as $line) {
                fputcsv($out, ['Financing', 'Change in '.$line['name'], $line['amount']]);
            }
            fputcsv($out, ['Financing', 'Net cash from financing', $data['net_cash_financing']]);
            fputcsv($out, ['Summary', 'Net change in cash', $data['net_change_in_cash']]);
            fputcsv($out, ['Summary', 'Opening cash', $data['opening_cash']]);
            fputcsv($out, ['Summary', 'Closing cash', $data['closing_cash']]);
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $pl = $this->profitAndLoss->buildPlDataPublic($dateFrom, $dateTo, 'accrual');
        $data = $this->cashFlow->build($dateFrom, $dateTo, (float) $pl['net_profit']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cash-flow-statement', [
            ...$data,
            'company' => $this->reportCompany(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('cash-flow-statement-'.$dateFrom.'-to-'.$dateTo.'.pdf');
    }

    /**
     * @return array{name:string,address:string}
     */
    private function reportCompany(): array
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
