<?php

namespace App\Support;

use App\Models\Account;
use App\Models\JournalItem;
use Illuminate\Support\Collection;

class AccountLedger
{
    public static function isDebitPositiveType(?string $accountType): bool
    {
        return in_array($accountType, ['asset', 'expense'], true);
    }

    public static function signedMovement(?string $accountType, float $debit, float $credit): float
    {
        $delta = $debit - $credit;

        return self::isDebitPositiveType($accountType) ? $delta : -$delta;
    }

    public static function netBalance(?string $accountType, float $totalDebit, float $totalCredit): float
    {
        return round(self::signedMovement($accountType, $totalDebit, $totalCredit), 2);
    }

    public static function openingBalance(string $accountCode, ?string $beforeDate, ?string $accountType = null): float
    {
        if (! $accountType) {
            $accountType = Account::query()->where('code', $accountCode)->value('type');
        }

        $query = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_items.account_code', $accountCode)
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at');

        if ($beforeDate) {
            $query->where('journal_entries.date', '<', $beforeDate);
        }
        PostedJournalScope::apply($query, 'journal_entries');

        $totals = $query
            ->selectRaw('COALESCE(SUM(journal_items.debit), 0) as total_debit, COALESCE(SUM(journal_items.credit), 0) as total_credit')
            ->first();

        return self::netBalance(
            $accountType,
            (float) ($totals->total_debit ?? 0),
            (float) ($totals->total_credit ?? 0)
        );
    }

    /**
     * @param  array<int, string>  $accountCodes
     * @return array<string, float>
     */
    public static function balancesAsOf(array $accountCodes, string $asOfDate): array
    {
        if ($accountCodes === []) {
            return [];
        }

        $types = Account::query()
            ->whereIn('code', $accountCodes)
            ->pluck('type', 'code');

        $rows = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_items.account_code', $accountCodes)
            ->where('journal_entries.date', '<=', $asOfDate)
            ->whereNull('journal_items.deleted_at')
            ->whereNull('journal_entries.deleted_at');
        PostedJournalScope::apply($rows, 'journal_entries');
        $rows = $rows->selectRaw('journal_items.account_code, COALESCE(SUM(journal_items.debit), 0) as total_debit, COALESCE(SUM(journal_items.credit), 0) as total_credit')
            ->groupBy('journal_items.account_code')
            ->get();

        $balances = array_fill_keys($accountCodes, 0.0);

        foreach ($rows as $row) {
            $type = $types[$row->account_code] ?? 'asset';
            $balances[$row->account_code] = self::netBalance($type, (float) $row->total_debit, (float) $row->total_credit);
        }

        return $balances;
    }

    /**
     * @param  iterable<int, array{id: int|string, debit: float, credit: float}>  $linesInOrder
     * @return array<int|string, float>
     */
    public static function runningBalances(?string $accountType, float $opening, iterable $lines): array
    {
        $balance = $opening;
        $map = [];

        foreach ($lines as $line) {
            $balance = round(
                $balance + self::signedMovement($accountType, (float) $line['debit'], (float) $line['credit']),
                2
            );
            $map[$line['id']] = $balance;
        }

        return $map;
    }

    /**
     * @param  Collection<int, object{id: int|string, debit: float|int|string, credit: float|int|string}>  $lines
     */
    public static function sumSignedMovements(?string $accountType, Collection $lines): float
    {
        return round($lines->sum(function ($line) use ($accountType) {
            return self::signedMovement($accountType, (float) $line->debit, (float) $line->credit);
        }), 2);
    }
}
