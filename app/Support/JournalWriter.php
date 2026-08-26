<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

final class JournalWriter
{
    /**
     * Insert a balanced system journal (status=posted, type=system).
     *
     * @param  array{date: string, description: string, reference_type?: ?string, reference_id?: ?int}  $header
     * @param  list<array{account_code: string, debit: float|int, credit: float|int, account_id?: ?int, description?: ?string}>  $lines
     */
    public static function postSystem(array $header, array $lines): int
    {
        self::assertBalanced($lines);

        $now = now();

        $journalId = DB::table('journal_entries')->insertGetId([
            'date'           => $header['date'],
            'description'    => $header['description'],
            'reference_type' => $header['reference_type'] ?? null,
            'reference_id'   => $header['reference_id'] ?? null,
            'type'           => 'system',
            'status'         => 'posted',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        self::insertLines($journalId, $lines, $now);

        return $journalId;
    }

    /**
     * Find the latest posted journal for a reference and post its reversal.
     */
    public static function postReversalByReference(
        string $referenceType,
        int $referenceId,
        string $description,
        string $date,
    ): bool {
        $journal = DB::table('journal_entries')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->latest('id')
            ->first();

        if (! $journal) {
            return false;
        }

        self::postReversalFromJournal(
            (int) $journal->id,
            $description,
            $date,
            $referenceType,
            $referenceId,
        );

        return true;
    }

    /**
     * Swap debits/credits from an existing journal into a new posted reversal.
     */
    public static function postReversalFromJournal(
        int $journalId,
        string $description,
        string $date,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): int {
        $items = DB::table('journal_items')->where('journal_entry_id', $journalId)->get();
        if ($items->isEmpty()) {
            throw new LogicException('Cannot reverse journal with no lines.');
        }

        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'account_code' => $item->account_code,
                'account_id'   => $item->account_id,
                'debit'        => (float) $item->credit,
                'credit'       => (float) $item->debit,
            ];
        }

        return self::postSystem([
            'date'           => $date,
            'description'    => $description,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
        ], $lines);
    }

    /**
     * @param  list<array{account_code: string, debit: float|int, credit: float|int, account_id?: ?int, description?: ?string}>  $lines
     */
    public static function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw new LogicException('Journal must have at least one line.');
        }

        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        if (abs($debit - $credit) >= 0.01) {
            throw new LogicException(sprintf(
                'Journal is not balanced (debit %.2f, credit %.2f).',
                $debit,
                $credit
            ));
        }
    }

    /**
     * @param  list<array{account_code: string, debit: float|int, credit: float|int, account_id?: ?int, description?: ?string}>  $lines
     */
    private static function insertLines(int $journalId, array $lines, $now): void
    {
        $codes = array_values(array_unique(array_column($lines, 'account_code')));
        $accountMap = DB::table('accounts')->whereIn('code', $codes)->pluck('id', 'code');

        $rows = [];
        foreach ($lines as $line) {
            $code = $line['account_code'];
            $rows[] = [
                'journal_entry_id' => $journalId,
                'account_id'       => $line['account_id'] ?? ($accountMap[$code] ?? null),
                'account_code'     => $code,
                'debit'            => (float) ($line['debit'] ?? 0),
                'credit'           => (float) ($line['credit'] ?? 0),
                'description'      => $line['description'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        DB::table('journal_items')->insert($rows);
    }
}
