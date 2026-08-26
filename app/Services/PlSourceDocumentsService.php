<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\PostedJournalScope;
use Illuminate\Support\Facades\DB;

final class PlSourceDocumentsService
{
    /**
     * @return list<array{entry_id: int, date: string, label: string, source_route: string, amount: float, reference_type: ?string}>
     */
    public function forAccount(string $accountCode, string $dateFrom, string $dateTo): array
    {
        $account = Account::query()->where('code', $accountCode)->firstOrFail();
        $isIncome = $account->type === 'income';
        $netSql = $isIncome
            ? 'SUM(journal_items.credit - journal_items.debit)'
            : 'SUM(journal_items.debit - journal_items.credit)';

        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_items.account_code', $accountCode)
            ->whereBetween('journal_entries.date', [$dateFrom, $dateTo]);
        PostedJournalScope::apply($rows, 'journal_entries');
        $rows = $rows
            ->select(
                'journal_entries.id as entry_id',
                'journal_entries.date',
                'journal_entries.reference_type',
                'journal_entries.reference_id',
                'journal_entries.reference_number',
                'journal_entries.description',
                DB::raw("{$netSql} as amount")
            )
            ->groupBy(
                'journal_entries.id',
                'journal_entries.date',
                'journal_entries.reference_type',
                'journal_entries.reference_id',
                'journal_entries.reference_number',
                'journal_entries.description'
            )
            ->orderByDesc('journal_entries.date')
            ->orderByDesc('journal_entries.id')
            ->get()
            ->filter(fn ($row) => abs((float) $row->amount) >= 0.01);

        $sources = [];
        foreach ($rows as $row) {
            $entry = JournalEntry::query()->find($row->entry_id);
            if (! $entry) {
                continue;
            }

            $sources[] = [
                'entry_id' => (int) $row->entry_id,
                'date' => (string) $row->date,
                'label' => $entry->getSourceLabel(),
                'source_route' => $entry->getSourceRoute(),
                'amount' => round((float) $row->amount, 2),
                'reference_type' => $row->reference_type,
            ];
        }

        return $sources;
    }
}
