<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepositRequest;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Tenant;
use App\Support\AccountLedger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank/Cash transactions feed and quick-entry forms.
 *
 * Concept (Wave-style):
 *   • A "transaction" is any journal item that hits a bank or cash account.
 *   • A "deposit" is money INTO a bank/cash account.
 *     → debit  bank/cash    credit  category (revenue, equity, transfer-in…)
 *   • A "withdrawal" is money OUT of a bank/cash account.
 *     → debit  category (expense, asset purchase, drawing…)
 *       credit bank/cash
 *
 * Both quick-entry forms post immediately (status = posted) so the user
 * doesn't have to think in debits/credits — they just pick "money in"
 * or "money out" and the system writes a balanced two-sided journal.
 *
 * The full Add Journal Entry flow remains available for power users via
 * the existing `journal.create` route.
 */
class TransactionsController extends Controller
{
    /**
     * Unified feed of bank/cash account movements.
     */
    public function index(Request $request): Response
    {
        $this->authorize('journal.view');

        $request->validate([
            'account' => 'nullable|exists:accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'search' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $start = Carbon::parse($request->input('start_date', now()->startOfMonth()->subMonths(2)->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end_date', now()->toDateString()))->toDateString();
        $search = trim((string) $request->input('search', ''));
        $accountFilter = $request->input('account');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));

        $bankAccountIds = Account::query()
            ->where('is_active', true)
            ->whereIn('sub_type', ['bank', 'cash'])
            ->when($accountFilter, fn ($q) => $q->where('id', $accountFilter))
            ->pluck('id');

        if ($bankAccountIds->isEmpty()) {
            return Inertia::render('Transactions/Index', [
                'transactions' => [],
                'totals' => ['in' => 0, 'out' => 0, 'net' => 0, 'count' => 0],
                'filters' => [
                    'account' => $accountFilter,
                    'start_date' => $start,
                    'end_date' => $end,
                    'search' => $search,
                    'page' => $page,
                    'per_page' => $perPage,
                ],
                'paginator' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
                'show_running_balance' => false,
                'bank_accounts' => $this->bankAccountOptions(withBalances: true),
                'base_currency' => $this->tenantBaseCurrency(),
                'can_create' => $request->user()->can('journal.create'),
            ]);
        }

        $baseQuery = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
            ->whereIn('ji.account_id', $bankAccountIds)
            ->whereBetween('je.date', [$start, $end])
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at');

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $baseQuery->where(function ($q) use ($needle) {
                $q->where('je.description', 'like', $needle)
                    ->orWhere('ji.description', 'like', $needle)
                    ->orWhere('je.reference_number', 'like', $needle)
                    ->orWhere('a.code', 'like', $needle)
                    ->orWhere('a.name', 'like', $needle);
            });
        }

        $total = (clone $baseQuery)->count();

        $rawItems = (clone $baseQuery)
            ->select([
                'ji.id', 'ji.journal_entry_id', 'ji.account_id', 'ji.debit', 'ji.credit', 'ji.description as item_description',
                'je.date', 'je.description as entry_description', 'je.reference_number', 'je.type', 'je.status', 'je.reference_type', 'je.reference_id',
                'a.code as account_code', 'a.name as account_name',
            ])
            ->orderByDesc('je.date')
            ->orderByDesc('je.id')
            ->orderByDesc('ji.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $entryIds = $rawItems->pluck('journal_entry_id')->unique()->values();
        $journalEntries = JournalEntry::query()
            ->whereIn('id', $entryIds)
            ->get()
            ->keyBy('id');

        $allItems = DB::table('journal_items as ji')
            ->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
            ->select(['ji.journal_entry_id', 'ji.account_id', 'ji.debit', 'ji.credit', 'a.code', 'a.name'])
            ->whereIn('ji.journal_entry_id', $entryIds)
            ->whereNull('ji.deleted_at')
            ->get()
            ->groupBy('journal_entry_id');

        $showRunningBalance = filled($accountFilter) && $bankAccountIds->count() === 1;
        $runningBalanceMap = [];

        if ($showRunningBalance) {
            $account = Account::findOrFail($accountFilter);
            $allForBalance = (clone $baseQuery)
                ->select(['ji.id', 'ji.debit', 'ji.credit'])
                ->orderBy('je.date')
                ->orderBy('je.id')
                ->orderBy('ji.id')
                ->get();

            $runningBalanceMap = AccountLedger::runningBalances(
                $account->type,
                0.0,
                $allForBalance->map(fn ($line) => [
                    'id' => $line->id,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])
            );
        }

        $rows = $rawItems->map(function ($r) use ($allItems, $journalEntries, $runningBalanceMap, $showRunningBalance) {
            $movement = (float) $r->debit > 0 ? (float) $r->debit : -(float) $r->credit;

            $counterparts = collect($allItems[$r->journal_entry_id] ?? [])
                ->reject(fn ($it) => (int) $it->account_id === (int) $r->account_id)
                ->map(fn ($it) => $it->code.' — '.$it->name)
                ->unique()
                ->values()
                ->all();

            $entry = $journalEntries->get($r->journal_entry_id);

            return [
                'id' => $r->id,
                'journal_entry_id' => $r->journal_entry_id,
                'date' => $r->date,
                'description' => $r->item_description ?: $r->entry_description ?: '—',
                'reference_number' => $r->reference_number,
                'account_code' => $r->account_code,
                'account_name' => $r->account_name,
                'category' => implode(', ', $counterparts) ?: '—',
                'amount' => round($movement, 2),
                'direction' => $movement >= 0 ? 'in' : 'out',
                'type' => $r->type,
                'status' => $r->status,
                'reference_type' => $r->reference_type,
                'reference_id' => $r->reference_id,
                'source_route' => $entry?->getSourceRoute() ?? route('general-ledger.show', $r->journal_entry_id),
                'source_label' => $entry?->getSourceLabel(),
                'running_balance' => $showRunningBalance ? ($runningBalanceMap[$r->id] ?? null) : null,
            ];
        });

        $allMovements = (clone $baseQuery)
            ->select(['ji.debit', 'ji.credit'])
            ->get();

        $in = 0.0;
        $out = 0.0;
        foreach ($allMovements as $line) {
            $movement = (float) $line->debit > 0 ? (float) $line->debit : -(float) $line->credit;
            if ($movement >= 0) {
                $in += $movement;
            } else {
                $out += abs($movement);
            }
        }

        $totals = [
            'in' => round($in, 2),
            'out' => round($out, 2),
            'count' => $total,
        ];
        $totals['net'] = round($totals['in'] - $totals['out'], 2);

        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = min($page * $perPage, $total);

        return Inertia::render('Transactions/Index', [
            'transactions' => $rows->values()->all(),
            'totals' => $totals,
            'filters' => [
                'account' => $accountFilter,
                'start_date' => $start,
                'end_date' => $end,
                'search' => $search,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'paginator' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
            'show_running_balance' => $showRunningBalance,
            'bank_accounts' => $this->bankAccountOptions(withBalances: true),
            'base_currency' => $this->tenantBaseCurrency(),
            'can_create' => $request->user()->can('journal.create'),
        ]);
    }

    public function createDeposit(): Response
    {
        $this->authorize('journal.create');

        return Inertia::render('Transactions/Deposit', [
            'bank_accounts' => $this->bankAccountOptions(),
            'category_accounts' => $this->categoryAccountOptions(forDeposit: true),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeDeposit(StoreDepositRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $bank = Account::findOrFail($data['bank_account_id']);
        $category = Account::findOrFail($data['category_account_id']);
        $amount = (float) $data['amount'];

        DB::transaction(function () use ($data, $bank, $category, $amount) {
            $entry = JournalEntry::create([
                'date' => $data['date'],
                'description' => $data['description'] ?? ('Deposit to '.$bank->name),
                'reference_number' => $data['reference_number'] ?? null,
                'type' => 'deposit',
                'status' => 'posted',
            ]);

            // Debit bank/cash (money coming in)
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $bank->id,
                'account_code' => $bank->code,
                'debit' => $amount,
                'credit' => 0,
                'description' => $data['description'] ?? null,
            ]);

            // Credit category account (where it came from)
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $category->id,
                'account_code' => $category->code,
                'debit' => 0,
                'credit' => $amount,
                'description' => $data['description'] ?? null,
            ]);
        });

        return redirect()->route('transactions.index')
            ->with('success', 'Deposit recorded and posted.');
    }

    public function createWithdrawal(): Response
    {
        $this->authorize('journal.create');

        return Inertia::render('Transactions/Withdrawal', [
            'bank_accounts' => $this->bankAccountOptions(),
            'category_accounts' => $this->categoryAccountOptions(forDeposit: false),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeWithdrawal(StoreWithdrawalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $bank = Account::findOrFail($data['bank_account_id']);
        $category = Account::findOrFail($data['category_account_id']);
        $amount = (float) $data['amount'];

        DB::transaction(function () use ($data, $bank, $category, $amount) {
            $entry = JournalEntry::create([
                'date' => $data['date'],
                'description' => $data['description'] ?? ('Withdrawal from '.$bank->name),
                'reference_number' => $data['reference_number'] ?? null,
                'type' => 'withdrawal',
                'status' => 'posted',
            ]);

            // Debit category account (expense / asset purchase)
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $category->id,
                'account_code' => $category->code,
                'debit' => $amount,
                'credit' => 0,
                'description' => $data['description'] ?? null,
            ]);

            // Credit bank/cash (money going out)
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $bank->id,
                'account_code' => $bank->code,
                'debit' => 0,
                'credit' => $amount,
                'description' => $data['description'] ?? null,
            ]);
        });

        return redirect()->route('transactions.index')
            ->with('success', 'Withdrawal recorded and posted.');
    }

    /**
     * Active bank + cash accounts only.
     *
     * @return array<int, array{id: int, code: string, name: string, sub_type: string|null, label: string, balance?: float}>
     */
    private function bankAccountOptions(bool $withBalances = false): array
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('sub_type', ['bank', 'cash'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'sub_type', 'type']);

        $balances = $withBalances
            ? AccountLedger::balancesAsOf($accounts->pluck('code')->all(), now()->toDateString())
            : [];

        return $accounts
            ->map(function ($a) use ($withBalances, $balances) {
                $row = [
                    'id' => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'sub_type' => $a->sub_type,
                    'label' => $a->code.' — '.$a->name,
                ];

                if ($withBalances) {
                    $row['balance'] = $balances[$a->code] ?? 0.0;
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Category account options for a deposit or withdrawal.
     *
     *   For deposit (money in, credit side): typically Income, Equity, or
     *      another Asset / Liability. We exclude bank/cash to keep the
     *      counter side clean (transfers between bank accounts will be
     *      a separate flow later).
     *   For withdrawal (money out, debit side): typically Expense, an
     *      Asset purchase, or paying down a Liability.
     *
     * Returns all accounts grouped by type so the dropdown can show
     * sensible group headings.
     */
    private function categoryAccountOptions(bool $forDeposit): array
    {
        return Account::query()
            ->where('is_active', true)
            // Exclude bank / cash accounts from the *category* dropdown so the
            // counter side of the entry stays clean. SQL's three-valued logic
            // means `WHERE sub_type NOT IN (...)` silently drops rows where
            // sub_type IS NULL — and most chart-of-accounts seeds leave the
            // sub_type empty for non-bank rows. Explicitly OR-ing in the NULL
            // case keeps Revenue / Expense / Equity / Liability / Receivable
            // / Payable etc. visible in the dropdown.
            ->where(function ($q) {
                $q->whereNull('sub_type')
                    ->orWhereNotIn('sub_type', ['bank', 'cash']);
            })
            ->orderBy('type')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'label' => $a->code.' — '.$a->name,
            ])
            ->values()
            ->all();
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }
}
