<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
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

        $journals = JournalEntry::with(['items.account'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Journal/Index', [
            'journals' => $journals,
            'can_create' => $request->user()->can('journal.create'),
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
