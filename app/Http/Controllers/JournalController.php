<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Support\JournalWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('journal.view');

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }
        $search = trim((string) $request->input('search', ''));
        $statusFilter = (string) $request->input('status', '');

        $query = JournalEntry::query()
            ->withCount('items')
            ->withSum('items as total_debit', 'debit')
            ->withSum('items as total_credit', 'credit')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%'.$search.'%')
                    ->orWhere('reference_number', 'like', '%'.$search.'%')
                    ->orWhere('reference_type', 'like', '%'.$search.'%');
            });
        }

        if (in_array($statusFilter, ['draft', 'posted'], true)) {
            $query->where('status', $statusFilter);
        }

        $totalCount = (clone $query)->count();
        $draftCount = (clone $query)->where('status', 'draft')->count();
        $postedCount = (clone $query)->where('status', 'posted')->count();

        $paginator = $query->paginate($perPage)->withQueryString();

        $journals = collect($paginator->items())->map(fn (JournalEntry $journal) => [
            'id' => $journal->id,
            'date' => $journal->date?->format('Y-m-d'),
            'reference_number' => $journal->reference_number,
            'reference_type' => $journal->reference_type,
            'description' => $journal->description,
            'status' => $journal->status,
            'type' => $journal->type,
            'items_count' => (int) $journal->items_count,
            'total_debit' => round((float) ($journal->total_debit ?? 0), 2),
            'total_credit' => round((float) ($journal->total_credit ?? 0), 2),
        ]);

        return Inertia::render('Journal/Index', [
            'journals' => $journals,
            'can_create' => $request->user()->can('journal.create'),
            'totalCount' => $totalCount,
            'draftCount' => $draftCount,
            'postedCount' => $postedCount,
            'paginator' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('journal.create');

        $accounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Journal/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $journal = JournalEntry::create([
                'date' => $request->date,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'type' => 'manual',
                'status' => 'draft',
            ]);

            foreach ($request->items as $item) {
                $account = Account::find($item['account_id']);
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $item['account_id'],
                    'account_code' => $account->code,
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'description' => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('journal.index')
            ->with('success', 'Journal entry created.');
    }

    public function edit(int $id): Response
    {
        $this->authorize('journal.edit');
        $journal = JournalEntry::findOrFail($id);

        if ($journal->status === 'posted') {
            return Inertia::render('Journal/Index', [
                'error' => 'Cannot edit a posted journal entry.'
            ]);
        }

        $journal->load('items');
        $accounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Journal/Edit', [
            'journal' => $journal,
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, int $id): RedirectResponse
    {
        $journal = JournalEntry::findOrFail($id);
        if ($journal->status === 'posted') {
            return redirect()->route('journal.index')
                ->with('error', 'Cannot update a posted journal entry.');
        }

        DB::transaction(function () use ($request, $journal) {
            $journal->update([
                'date' => $request->date,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
            ]);

            // Simple way: delete and recreate items
            $journal->items()->delete();

            foreach ($request->items as $item) {
                $account = Account::find($item['account_id']);
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $item['account_id'],
                    'account_code' => $account->code,
                    'debit' => $item['debit'],
                    'credit' => $item['credit'],
                    'description' => $item['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('journal.index')
            ->with('success', 'Journal entry updated.');
    }

    public function post(int $id): RedirectResponse
    {
        $this->authorize('journal.post');
        $journal = JournalEntry::findOrFail($id);

        if ($journal->status === 'posted') {
            return redirect()->back()->with('error', 'Journal entry is already posted.');
        }

        $journal->load('items');
        $lines = $journal->items->map(fn (JournalItem $item) => [
            'account_code' => $item->account_code,
            'debit'        => (float) $item->debit,
            'credit'       => (float) $item->credit,
        ])->all();

        JournalWriter::assertBalanced($lines);

        $journal->update(['status' => 'posted']);

        return redirect()->back()->with('success', 'Journal entry posted to ledger.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('journal.delete');
        $journal = JournalEntry::findOrFail($id);

        if ($journal->status === 'posted') {
            return redirect()->route('journal.index')
                ->with('error', 'Cannot delete a posted journal entry.');
        }

        $journal->delete();

        return redirect()->route('journal.index')
            ->with('success', 'Journal entry deleted.');
    }
}
