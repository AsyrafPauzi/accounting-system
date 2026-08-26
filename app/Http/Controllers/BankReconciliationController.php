<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Tenant;
use App\Services\BankReconciliationService;
use App\Services\BankStatementImportService;
use App\Support\UploadDisk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('bank-rec.view');

        $statements = BankStatement::query()
            ->with('account:id,code,name')
            ->withCount([
                'lines',
                'lines as matched_lines_count' => fn ($q) => $q->where('match_status', 'matched'),
                'lines as unmatched_lines_count' => fn ($q) => $q->whereIn('match_status', ['unmatched', 'suggested']),
            ])
            ->latest('id')
            ->paginate(20)
            ->through(fn (BankStatement $s) => [
                'id' => $s->id,
                'account' => $s->account ? "{$s->account->code} — {$s->account->name}" : '',
                'period_start' => $s->period_start->toDateString(),
                'period_end' => $s->period_end->toDateString(),
                'opening_balance' => (float) $s->opening_balance,
                'closing_balance' => (float) $s->closing_balance,
                'source' => $s->source,
                'status' => $s->status,
                'line_count' => (int) $s->lines_count,
                'matched_lines_count' => (int) $s->matched_lines_count,
                'unmatched_lines_count' => (int) $s->unmatched_lines_count,
            ]);

        return Inertia::render('BankRec/Index', [
            'statements' => $statements,
            'can_match' => $request->user()->can('bank-rec.match'),
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function createImport(Request $request): Response
    {
        $this->authorize('bank-rec.match');

        return Inertia::render('BankRec/Import', [
            'bank_accounts' => $this->bankAccountOptions(),
        ]);
    }

    public function storeImport(Request $request, BankStatementImportService $importService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'file' => 'required|file|mimes:csv,txt,pdf|max:5120',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
        ]);

        $account = Account::query()
            ->whereKey($validated['account_id'])
            ->whereIn('sub_type', ['bank', 'cash'])
            ->firstOrFail();

        $path = $request->file('file')->store('bank-statements', UploadDisk::name());
        $disk = UploadDisk::disk();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $absolute = $disk->path($path);
            $result = $importService->importFromPdf(
                $absolute,
                $account,
                $path,
                isset($validated['opening_balance']) ? (float) $validated['opening_balance'] : null,
                isset($validated['closing_balance']) ? (float) $validated['closing_balance'] : null,
            );
        } else {
            $contents = $disk->get($path);
            $result = $importService->importFromCsv(
                $contents,
                $account,
                $path,
                isset($validated['opening_balance']) ? (float) $validated['opening_balance'] : null,
                isset($validated['closing_balance']) ? (float) $validated['closing_balance'] : null,
            );
        }

        return redirect()
            ->route('bank-rec.match', $result['statement'])
            ->with('success', "Imported {$result['line_count']} statement lines.");
    }

    public function match(Request $request, BankStatement $statement, BankReconciliationService $reconciliationService): Response
    {
        $this->authorize('bank-rec.view');

        $statement->load(['account:id,code,name', 'lines.matchedJournalItem.journalEntry']);

        if ($request->boolean('refresh_suggestions') && $request->user()->can('bank-rec.match')) {
            $reconciliationService->suggestMatches($statement);
            $statement->refresh()->load(['lines.matchedJournalItem.journalEntry']);
        }

        $lines = $statement->lines
            ->sortBy('transaction_date')
            ->values()
            ->map(fn (BankStatementLine $line) => [
                'id' => $line->id,
                'transaction_date' => $line->transaction_date->toDateString(),
                'description' => $line->description,
                'reference' => $line->reference,
                'amount' => (float) $line->amount,
                'match_status' => $line->match_status,
                'match_confidence' => $line->match_confidence !== null ? (float) $line->match_confidence : null,
                'matched_journal_item_id' => $line->matched_journal_item_id,
                'suggestion' => $line->matchedJournalItem ? [
                    'journal_item_id' => $line->matchedJournalItem->id,
                    'journal_date' => optional($line->matchedJournalItem->journalEntry?->date)->toDateString(),
                    'journal_description' => $line->matchedJournalItem->journalEntry?->description,
                    'reference_number' => $line->matchedJournalItem->journalEntry?->reference_number,
                ] : null,
            ]);

        $matchedTotal = $statement->lines->where('match_status', 'matched')->sum(fn ($l) => (float) $l->amount);
        $bookBalance = round((float) $statement->opening_balance + $matchedTotal, 2);

        return Inertia::render('BankRec/Match', [
            'statement' => [
                'id' => $statement->id,
                'account' => $statement->account ? "{$statement->account->code} — {$statement->account->name}" : '',
                'account_id' => $statement->account_id,
                'period_start' => $statement->period_start->toDateString(),
                'period_end' => $statement->period_end->toDateString(),
                'opening_balance' => (float) $statement->opening_balance,
                'closing_balance' => (float) $statement->closing_balance,
                'status' => $statement->status,
                'source' => $statement->source,
            ],
            'lines' => $lines,
            'summary' => [
                'matched_count' => $statement->lines->where('match_status', 'matched')->count(),
                'suggested_count' => $statement->lines->where('match_status', 'suggested')->count(),
                'unmatched_count' => $statement->lines->where('match_status', 'unmatched')->count(),
                'excluded_count' => $statement->lines->where('match_status', 'excluded')->count(),
                'book_balance' => $bookBalance,
                'difference' => round((float) $statement->closing_balance - $bookBalance, 2),
            ],
            'can_match' => $request->user()->can('bank-rec.match'),
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function suggestMatches(BankStatement $statement, BankReconciliationService $reconciliationService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        $count = $reconciliationService->suggestMatches($statement);

        return back()->with('success', $count > 0
            ? "Suggested {$count} matches."
            : 'No new matches found.');
    }

    public function confirmMatch(Request $request, BankStatementLine $line, BankReconciliationService $reconciliationService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        $validated = $request->validate([
            'journal_item_id' => 'nullable|integer|exists:journal_items,id',
        ]);

        $journalItemId = $validated['journal_item_id'] ?? $line->matched_journal_item_id;
        if (! $journalItemId) {
            return back()->withErrors(['journal_item_id' => 'Select a journal item to confirm.']);
        }

        try {
            $reconciliationService->confirmMatch($line, (int) $journalItemId);
        } catch (\LogicException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return back()->with('success', 'Match confirmed.');
    }

    public function rejectSuggestion(BankStatementLine $line, BankReconciliationService $reconciliationService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        $reconciliationService->rejectSuggestion($line);

        return back()->with('success', 'Suggestion rejected.');
    }

    public function excludeLine(BankStatementLine $line, BankReconciliationService $reconciliationService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        $reconciliationService->excludeLine($line);

        return back()->with('success', 'Line excluded from reconciliation.');
    }

    public function reconcile(BankStatement $statement, BankReconciliationService $reconciliationService): RedirectResponse
    {
        $this->authorize('bank-rec.match');

        try {
            $reconciliationService->reconcile($statement);
        } catch (\LogicException $e) {
            return back()->withErrors(['reconcile' => $e->getMessage()]);
        }

        return redirect()
            ->route('bank-rec.index')
            ->with('success', 'Statement reconciled.');
    }

    /** @return list<array{id: int, label: string}> */
    private function bankAccountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->whereIn('sub_type', ['bank', 'cash'])
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'label' => "{$a->code} — {$a->name}",
            ])
            ->all();
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenant();

            return $tenant instanceof Tenant ? ($tenant->base_currency ?? 'MYR') : 'MYR';
        }

        return 'MYR';
    }
}
