<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use App\Support\PayrollRemittance;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRemittanceController extends Controller
{
    private const PAYABLE_KEYS = [
        'epf_payable',
        'socso_payable',
        'eis_payable',
        'pcb_payable',
        'hrd_payable',
    ];

    public function index(Request $request): Response
    {
        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_of_date')
        );
        $asOf = $resolved['as_of'];

        $accounts = (new PayrollService())->ensureAccounts();
        $payableAccounts = collect(self::PAYABLE_KEYS)
            ->mapWithKeys(fn (string $key) => [$accounts[$key]->code => $accounts[$key]]);

        $movements = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'ji.journal_entry_id', '=', 'je.id')
            ->whereIn('ji.account_code', $payableAccounts->keys())
            ->where('je.status', 'posted')
            ->whereDate('je.date', '<=', $asOf)
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->select([
                'ji.account_code',
                DB::raw('SUM(ji.debit) as total_debit'),
                DB::raw('SUM(ji.credit) as total_credit'),
            ])
            ->groupBy('ji.account_code')
            ->get()
            ->keyBy('account_code');

        $rows = $payableAccounts
            ->map(function ($account, string $code) use ($movements, $asOf) {
                $movement = $movements->get($code);
                $creditBalance = PayrollRemittance::creditBalance(
                    (float) ($movement->total_debit ?? 0),
                    (float) ($movement->total_credit ?? 0)
                );

                return [
                    'code' => $code,
                    'name' => $account->name,
                    'credit_balance' => $creditBalance,
                    'ledger_url' => route('general-ledger.report', [
                        'account_code' => $code,
                        'date_to' => $asOf,
                        'from' => 'pr',
                    ]),
                ];
            })
            ->filter(fn (array $row) => $row['credit_balance'] > 0)
            ->values();

        return Inertia::render('Reports/PayrollRemittance', [
            'remittances' => $rows,
            'total' => round($rows->sum('credit_balance'), 2),
            'filters' => [
                'preset' => $resolved['preset'],
                'as_of_date' => $asOf,
            ],
        ]);
    }
}
