<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Support\AccountingPeriodResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class BankReconciliationService
{
    private const DATE_WINDOW_DAYS = 3;

    public function suggestMatches(BankStatement $statement): int
    {
        $statement->loadMissing('lines', 'account');
        $suggested = 0;

        foreach ($statement->lines as $line) {
            if ($line->match_status !== 'unmatched') {
                continue;
            }

            $match = $this->findBestMatch($line, (int) $statement->account_id);
            if ($match === null) {
                continue;
            }

            $line->update([
                'matched_journal_item_id' => $match['journal_item_id'],
                'match_status' => 'suggested',
                'match_confidence' => $match['confidence'],
            ]);

            $suggested++;
        }

        return $suggested;
    }

    public function confirmMatch(BankStatementLine $line, int $journalItemId): void
    {
        $line->loadMissing('bankStatement.account');

        AccountingPeriodResolver::assertOpenForDate($line->transaction_date->toDateString());

        $candidate = $this->findJournalCandidate(
            $journalItemId,
            (int) $line->bankStatement->account_id,
            $line->transaction_date->toDateString(),
            (float) $line->amount,
        );

        if ($candidate === null) {
            throw new \LogicException('Selected journal item is not a valid match for this statement line.');
        }

        $line->update([
            'matched_journal_item_id' => $journalItemId,
            'match_status' => 'matched',
            'match_confidence' => 1.0,
        ]);
    }

    public function rejectSuggestion(BankStatementLine $line): void
    {
        if ($line->match_status !== 'suggested') {
            return;
        }

        $line->update([
            'matched_journal_item_id' => null,
            'match_status' => 'unmatched',
            'match_confidence' => null,
        ]);
    }

    public function excludeLine(BankStatementLine $line): void
    {
        $line->update([
            'matched_journal_item_id' => null,
            'match_status' => 'excluded',
            'match_confidence' => null,
        ]);
    }

    public function reconcile(BankStatement $statement): BankStatement
    {
        $statement->loadMissing('lines');

        $pending = $statement->lines
            ->whereNotIn('match_status', ['matched', 'excluded'])
            ->count();

        if ($pending > 0) {
            throw new \LogicException('All statement lines must be matched or excluded before reconciling.');
        }

        $statement->update(['status' => 'reconciled']);

        return $statement->fresh(['lines', 'account']);
    }

    /**
     * @return array{journal_item_id: int, confidence: float, journal_description: string, journal_date: string, journal_amount: float}|null
     */
    private function findBestMatch(BankStatementLine $line, int $accountId): ?array
    {
        $targetDate = Carbon::parse($line->transaction_date);
        $targetAmount = round((float) $line->amount, 2);
        $needle = strtolower(trim((string) ($line->reference ?? $line->description ?? '')));

        $candidates = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->whereBetween('je.date', [
                $targetDate->copy()->subDays(self::DATE_WINDOW_DAYS)->toDateString(),
                $targetDate->copy()->addDays(self::DATE_WINDOW_DAYS)->toDateString(),
            ])
            ->whereNotIn('ji.id', $this->alreadyUsedJournalItemIds())
            ->select([
                'ji.id',
                'ji.debit',
                'ji.credit',
                'je.date',
                'je.description',
                'je.reference_number',
            ])
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $movement = $this->signedMovement((float) $candidate->debit, (float) $candidate->credit);
            if (round($movement, 2) !== $targetAmount) {
                continue;
            }

            $daysDiff = abs(Carbon::parse($candidate->date)->diffInDays($targetDate));
            $score = max(0.5, 0.85 - ($daysDiff * 0.05));

            $referenceHaystack = strtolower(trim(
                ($candidate->reference_number ?? '').' '.($candidate->description ?? '')
            ));

            if ($needle !== '' && $referenceHaystack !== '' && str_contains($referenceHaystack, $needle)) {
                $score = min(1.0, $score + 0.15);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'journal_item_id' => (int) $candidate->id,
                    'confidence' => round($bestScore, 2),
                    'journal_description' => (string) $candidate->description,
                    'journal_date' => (string) $candidate->date,
                    'journal_amount' => $movement,
                ];
            }
        }

        return $best;
    }

    /**
     * @return array{journal_item_id: int, journal_amount: float}|null
     */
    private function findJournalCandidate(int $journalItemId, int $accountId, string $date, float $amount): ?array
    {
        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->where('ji.id', $journalItemId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->whereBetween('je.date', [
                Carbon::parse($date)->subDays(self::DATE_WINDOW_DAYS)->toDateString(),
                Carbon::parse($date)->addDays(self::DATE_WINDOW_DAYS)->toDateString(),
            ])
            ->select(['ji.id', 'ji.debit', 'ji.credit'])
            ->first();

        if (! $row) {
            return null;
        }

        $movement = $this->signedMovement((float) $row->debit, (float) $row->credit);
        if (round($movement, 2) !== round($amount, 2)) {
            return null;
        }

        return [
            'journal_item_id' => (int) $row->id,
            'journal_amount' => $movement,
        ];
    }

    /** @return list<int> */
    private function alreadyUsedJournalItemIds(): array
    {
        return BankStatementLine::query()
            ->whereIn('match_status', ['suggested', 'matched'])
            ->whereNotNull('matched_journal_item_id')
            ->pluck('matched_journal_item_id')
            ->all();
    }

    private function signedMovement(float $debit, float $credit): float
    {
        return $debit > 0 ? $debit : -$credit;
    }
}
