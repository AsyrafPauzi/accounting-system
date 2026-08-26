<?php

namespace App\Services;

use App\Support\CashMovement;
use App\Support\PostedJournalScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CashFlowStatementService
{
    /** @var list<string> */
    private const OPERATING_ASSET_CODES = [
        '1100', '1110', '1220', '1300', '1310', '1400',
    ];

    /** @var list<string> */
    private const OPERATING_LIABILITY_CODES = [
        '2100', '2110', '2250', '2300',
    ];

    /** @var list<string> */
    private const FINANCING_LIABILITY_CODES = [
        '2310',
    ];

    /** @var list<string> */
    private const FINANCING_EQUITY_CODES = [
        '3000', '3200',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(string $dateFrom, string $dateTo, float $netProfit): array
    {
        $operatingAdjustments = [];
        $operatingTotal = 0.0;

        foreach (self::OPERATING_ASSET_CODES as $code) {
            $change = $this->balanceChange($code, 'asset', $dateFrom, $dateTo);
            if (abs($change) < 0.01) {
                continue;
            }
            $adjustment = round(-$change, 2);
            $operatingAdjustments[] = $this->adjustmentLine($code, $adjustment, 'asset');
            $operatingTotal += $adjustment;
        }

        foreach (self::OPERATING_LIABILITY_CODES as $code) {
            $change = $this->balanceChange($code, 'liability', $dateFrom, $dateTo);
            if (abs($change) < 0.01) {
                continue;
            }
            $adjustment = round($change, 2);
            $operatingAdjustments[] = $this->adjustmentLine($code, $adjustment, 'liability');
            $operatingTotal += $adjustment;
        }

        $netOperating = round($netProfit + $operatingTotal, 2);

        $investingLines = $this->investingLines($dateFrom, $dateTo);
        $netInvesting = round(array_sum(array_column($investingLines, 'amount')), 2);

        $financingLines = array_merge(
            $this->sectionFromCodes($dateFrom, $dateTo, self::FINANCING_LIABILITY_CODES, 'liability'),
            $this->sectionFromCodes($dateFrom, $dateTo, self::FINANCING_EQUITY_CODES, 'equity'),
        );
        $netFinancing = round(array_sum(array_column($financingLines, 'amount')), 2);

        $netChange = round($netOperating + $netInvesting + $netFinancing, 2);
        $openingCash = CashMovement::netAsOf(Carbon::parse($dateFrom)->subDay()->toDateString());
        $closingCash = CashMovement::netAsOf($dateTo);
        $actualChange = round($closingCash - $openingCash, 2);

        return [
            'date_from'              => $dateFrom,
            'date_to'                => $dateTo,
            'net_profit'             => round($netProfit, 2),
            'operating_adjustments'  => $operatingAdjustments,
            'net_cash_operating'     => $netOperating,
            'investing_lines'        => $investingLines,
            'net_cash_investing'     => $netInvesting,
            'financing_lines'        => $financingLines,
            'net_cash_financing'     => $netFinancing,
            'net_change_in_cash'     => $netChange,
            'opening_cash'           => $openingCash,
            'closing_cash'           => $closingCash,
            'actual_change_in_cash'  => $actualChange,
            'reconciled'             => abs($netChange - $actualChange) < 0.05,
        ];
    }

    /**
     * @return array{code:string,name:string,amount:float,change:float}
     */
    private function adjustmentLine(string $code, float $adjustment, string $type): array
    {
        $account = DB::table('accounts')->where('code', $code)->first();

        return [
            'code'   => $code,
            'name'   => $account->name ?? $code,
            'amount' => $adjustment,
            'change' => $type === 'asset' ? -$adjustment : $adjustment,
        ];
    }

    /**
     * @return list<array{code:string,name:string,amount:float,change:float}>
     */
    private function sectionFromCodes(string $dateFrom, string $dateTo, array $codes, string $type): array
    {
        $lines = [];
        foreach ($codes as $code) {
            $change = $this->balanceChange($code, $type, $dateFrom, $dateTo);
            if (abs($change) < 0.01) {
                continue;
            }
            $amount = $type === 'asset' ? round(-$change, 2) : round($change, 2);
            $lines[] = $this->adjustmentLine($code, $amount, $type);
        }

        return $lines;
    }

    /**
     * Non-current assets (PPE band 1500–1999) excluding working-capital assets.
     *
     * @return list<array{code:string,name:string,amount:float,change:float}>
     */
    private function investingLines(string $dateFrom, string $dateTo): array
    {
        $accounts = DB::table('accounts')
            ->where('type', 'asset')
            ->whereNotIn('sub_type', ['bank', 'cash'])
            ->whereNotIn('code', self::OPERATING_ASSET_CODES)
            ->orderBy('code')
            ->get(['code', 'name']);

        $lines = [];
        foreach ($accounts as $account) {
            $numeric = (int) preg_replace('/\D/', '', (string) $account->code);
            if ($numeric < 1500 || $numeric > 1999) {
                continue;
            }
            $change = $this->balanceChange($account->code, 'asset', $dateFrom, $dateTo);
            if (abs($change) < 0.01) {
                continue;
            }
            $lines[] = $this->adjustmentLine($account->code, round(-$change, 2), 'asset');
        }

        return $lines;
    }

    private function balanceChange(string $code, string $type, string $dateFrom, string $dateTo): float
    {
        $opening = $this->accountBalanceAsOf($code, $type, Carbon::parse($dateFrom)->subDay()->toDateString());
        $closing = $this->accountBalanceAsOf($code, $type, $dateTo);

        return round($closing - $opening, 2);
    }

    private function accountBalanceAsOf(string $code, string $type, string $asOf): float
    {
        $query = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_items.account_code', $code)
            ->where('journal_entries.date', '<=', $asOf);
        PostedJournalScope::apply($query);

        $row = $query->selectRaw('COALESCE(SUM(journal_items.debit), 0) as debit, COALESCE(SUM(journal_items.credit), 0) as credit')
            ->first();

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return match ($type) {
            'asset' => round($debit - $credit, 2),
            default => round($credit - $debit, 2),
        };
    }
}
