<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * /api/v1/transactions
 *
 * Bank/cash transactions feed and quick-entry endpoints, mirroring the
 * shape produced by the in-app TransactionsController. Wave-style
 * model: a "transaction" is any journal item that hits a bank or cash
 * account; the counter leg points at the category (revenue, expense,
 * equity, etc.).
 *
 * Auth posture (set by the route group, not this controller):
 *   - All methods: Bearer api_key (api.key middleware).
 *   - storeDeposit/storeWithdrawal also require HMAC signature
 *     (api.signed middleware) — a leaked api_key alone cannot mutate.
 */
class TransactionController extends Controller
{
    /**
     * Read feed. Defaults to the last 90 days, capped at 500 items per
     * page. Supports start_date / end_date / search / account filters
     * matching the in-app feed for parity.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account'    => ['nullable', 'integer', 'exists:accounts,id'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'search'     => ['nullable', 'string', 'max:200'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $start = Carbon::parse($request->input('start_date', now()->subDays(90)->toDateString()))->toDateString();
        $end   = Carbon::parse($request->input('end_date', now()->toDateString()))->toDateString();
        $perPage = (int) $request->input('per_page', 100);
        $search  = trim((string) $request->input('search', ''));

        $bankAccountIds = Account::query()
            ->where('is_active', true)
            ->whereIn('sub_type', ['bank', 'cash'])
            ->when($request->input('account'), fn ($q, $id) => $q->where('id', $id))
            ->pluck('id');

        $query = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'ji.account_id')
            ->select([
                'ji.id', 'ji.journal_entry_id', 'ji.account_id', 'ji.debit', 'ji.credit', 'ji.description as item_description',
                'je.date', 'je.description as entry_description', 'je.reference_number', 'je.type', 'je.status',
                'a.code as account_code', 'a.name as account_name',
            ])
            ->whereIn('ji.account_id', $bankAccountIds)
            ->whereBetween('je.date', [$start, $end])
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->orderByDesc('je.date')
            ->orderByDesc('je.id');

        if ($search !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($needle) {
                $q->where('ji.description', 'like', $needle)
                  ->orWhere('je.description', 'like', $needle)
                  ->orWhere('je.reference_number', 'like', $needle);
            });
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => [
                'id'                => $r->id,
                'journal_entry_id'  => $r->journal_entry_id,
                'date'              => $r->date,
                'description'       => $r->item_description ?: $r->entry_description ?: null,
                'reference_number'  => $r->reference_number,
                'account' => [
                    'id'   => $r->account_id,
                    'code' => $r->account_code,
                    'name' => $r->account_name,
                ],
                // Positive = money in (debit on bank/cash);
                // Negative = money out (credit on bank/cash).
                'amount'    => round((float) $r->debit > 0 ? (float) $r->debit : -(float) $r->credit, 2),
                'direction' => (float) $r->debit > 0 ? 'in' : 'out',
                'type'      => $r->type,
                'status'    => $r->status,
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function storeDeposit(Request $request): JsonResponse
    {
        return $this->storeMovement($request, isDeposit: true);
    }

    public function storeWithdrawal(Request $request): JsonResponse
    {
        return $this->storeMovement($request, isDeposit: false);
    }

    /**
     * Shared deposit / withdrawal create. Sign convention follows
     * TransactionsController: deposit debits the bank, withdrawal
     * credits the bank.
     */
    private function storeMovement(Request $request, bool $isDeposit): JsonResponse
    {
        $data = $request->validate([
            'date'                => ['required', 'date'],
            'bank_account_id'     => ['required', Rule::exists('accounts', 'id')->where('is_active', true)],
            'category_account_id' => ['required', Rule::exists('accounts', 'id')->where('is_active', true), 'different:bank_account_id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'description'         => ['nullable', 'string', 'max:500'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
        ]);

        $bank     = Account::findOrFail($data['bank_account_id']);
        $category = Account::findOrFail($data['category_account_id']);

        // Defence in depth: refuse if the partner pointed bank_account_id
        // at a non-bank account. The exists rule above doesn't enforce
        // sub_type.
        if (! in_array($bank->sub_type, ['bank', 'cash'], true)) {
            return response()->json([
                'error'             => 'invalid_bank_account',
                'error_description' => 'bank_account_id must reference an active bank or cash account.',
            ], 422);
        }

        $amount = round((float) $data['amount'], 2);

        $entry = DB::transaction(function () use ($data, $bank, $category, $amount, $isDeposit) {
            $entry = JournalEntry::create([
                'date'             => $data['date'],
                'description'      => $data['description'] ?? (($isDeposit ? 'Deposit to ' : 'Withdrawal from ') . $bank->name),
                'reference_number' => $data['reference_number'] ?? null,
                'type'             => $isDeposit ? 'deposit' : 'withdrawal',
                'status'           => 'posted',
            ]);

            // Bank leg.
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $bank->id,
                'account_code'     => $bank->code,
                'debit'            => $isDeposit ? $amount : 0,
                'credit'           => $isDeposit ? 0       : $amount,
                'description'      => $data['description'] ?? null,
            ]);

            // Category (other side) leg.
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $category->id,
                'account_code'     => $category->code,
                'debit'            => $isDeposit ? 0       : $amount,
                'credit'           => $isDeposit ? $amount : 0,
                'description'      => $data['description'] ?? null,
            ]);

            return $entry;
        });

        return response()->json([
            'data' => [
                'journal_entry_id'  => $entry->id,
                'date'              => $entry->date->toDateString(),
                'type'              => $entry->type,
                'status'            => $entry->status,
                'description'       => $entry->description,
                'reference_number'  => $entry->reference_number,
                'amount'            => $isDeposit ? $amount : -$amount,
                'direction'         => $isDeposit ? 'in' : 'out',
                'bank_account'      => [
                    'id'   => $bank->id,
                    'code' => $bank->code,
                    'name' => $bank->name,
                ],
                'category_account'  => [
                    'id'   => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                ],
            ],
        ], 201);
    }
}
