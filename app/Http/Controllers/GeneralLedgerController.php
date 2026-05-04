<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerController extends Controller
{
    protected const REFERENCE_TYPES = ['Invoice', 'Invoice Payment', 'Credit Note', 'Bill', 'Bill Payment', 'Manual'];

    /**
     * General Ledger Report: all transactions (journal item lines) in list format.
     */
    public function report(Request $request): Response
    {
        $query = JournalItem::with('journalEntry')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_items.*')
            ->orderBy('journal_entries.date', 'desc')
            ->orderBy('journal_entries.id', 'desc')
            ->orderBy('journal_items.id', 'asc');

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');
        $accountCode = $request->input('account_code');

        if ($dateFrom) {
            $query->whereDate('journal_entries.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('journal_entries.date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('journal_entries.reference_type', $referenceType);
        }
        if ($accountCode) {
            $query->where('journal_items.account_code', $accountCode);
        }

        $paginator = $query->paginate(50)->withQueryString();

        $transactions = collect($paginator->items())->map(function (JournalItem $item) {
            $entry = $item->journalEntry;

            return [
                'id' => $item->id,
                'date' => $entry->date->format('Y-m-d'),
                'entry_id' => $entry->id,
                'description' => $entry->description,
                'account_code' => $item->account_code,
                'debit' => (float) $item->debit,
                'credit' => (float) $item->credit,
                'reference_type' => $entry->reference_type,
                'source_route' => $entry->getSourceRoute(),
            ];
        });

        $allCodes = collect($paginator->items())->pluck('account_code')->unique()->filter()->values()->all();
        $accountsMap = $allCodes ? Account::whereIn('code', $allCodes)->pluck('name', 'code')->toArray() : [];

        $statsQuery = JournalItem::join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->when($dateFrom, fn ($q) => $q->whereDate('journal_entries.date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('journal_entries.date', '<=', $dateTo))
            ->when($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true), fn ($q) => $q->where('journal_entries.reference_type', $referenceType))
            ->when($accountCode, fn ($q) => $q->where('journal_items.account_code', $accountCode));

        $totalDebits = (float) (clone $statsQuery)->sum('journal_items.debit');
        $totalCredits = (float) (clone $statsQuery)->sum('journal_items.credit');
        $transactionsCount = (clone $statsQuery)->count();

        $accountOptions = Account::orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('GeneralLedger/Report', [
            'transactions' => $transactions,
            'accountsMap' => $accountsMap,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'reference_type' => $referenceType,
                'account_code' => $accountCode,
            ],
            'stats' => [
                'transactions_count' => $transactionsCount,
                'total_debits' => round($totalDebits, 2),
                'total_credits' => round($totalCredits, 2),
            ],
            'accountOptions' => $accountOptions,
            'paginator' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'prev_url' => $paginator->previousPageUrl(),
                'next_url' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $query = JournalEntry::with('items')->orderBy('date', 'desc')->orderBy('id', 'desc');

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('reference_type', $referenceType);
        }

        $paginator = $query->paginate(25)->withQueryString();

        $entries = collect($paginator->items())->map(function (JournalEntry $entry) {
            $totalDebit = $entry->items->sum(fn ($i) => (float) $i->debit);
            $totalCredit = $entry->items->sum(fn ($i) => (float) $i->credit);
            $balanced = abs($totalDebit - $totalCredit) < 0.01;

            return [
                'id' => $entry->id,
                'date' => $entry->date->format('Y-m-d'),
                'description' => $entry->description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'items_count' => $entry->items->count(),
                'balanced' => $balanced,
                'source_route' => $entry->getSourceRoute(),
            ];
        });

        $allCodes = collect($paginator->items())->pluck('items')->flatten()->pluck('account_code')->unique()->filter()->values()->all();
        $accountsMap = $allCodes ? Account::whereIn('code', $allCodes)->pluck('name', 'code')->toArray() : [];

        $statsQuery = JournalEntry::with('items')
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->when($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true), fn ($q) => $q->where('reference_type', $referenceType));

        $entriesForStats = $statsQuery->get();
        $totalDebits = $entriesForStats->sum(fn ($e) => $e->items->sum(fn ($i) => (float) $i->debit));
        $totalCredits = $entriesForStats->sum(fn ($e) => $e->items->sum(fn ($i) => (float) $i->credit));
        $balancedCount = $entriesForStats->filter(function ($e) {
            $d = $e->items->sum(fn ($i) => (float) $i->debit);
            $c = $e->items->sum(fn ($i) => (float) $i->credit);

            return abs($d - $c) < 0.01;
        })->count();

        return Inertia::render('GeneralLedger/Index', [
            'entries' => $entries,
            'accountsMap' => $accountsMap,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'reference_type' => $referenceType,
            ],
            'stats' => [
                'entries_count' => $entriesForStats->count(),
                'total_debits' => round($totalDebits, 2),
                'total_credits' => round($totalCredits, 2),
                'balanced_count' => $balancedCount,
            ],
            'paginator' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'prev_url' => $paginator->previousPageUrl(),
                'next_url' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $entry = JournalEntry::with('items')->findOrFail($id);

        $items = $entry->items->map(fn ($i) => [
            'id' => $i->id,
            'account_code' => $i->account_code,
            'debit' => (float) $i->debit,
            'credit' => (float) $i->credit,
        ]);

        $codes = $entry->items->pluck('account_code')->unique()->filter()->values()->all();
        $accountsMap = $codes ? Account::whereIn('code', $codes)->pluck('name', 'code')->toArray() : [];

        $totalDebit = $entry->items->sum(fn ($i) => (float) $i->debit);
        $totalCredit = $entry->items->sum(fn ($i) => (float) $i->credit);

        $entryData = [
            'id' => $entry->id,
            'date' => $entry->date->format('Y-m-d'),
            'description' => $entry->description,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'source_route' => $entry->getSourceRoute(),
        ];

        return Inertia::render('GeneralLedger/Show', [
            'entry' => $entryData,
            'items' => $items,
            'accountsMap' => $accountsMap,
        ]);
    }

    protected const EXPORT_LIMIT = 10000;

    /**
     * Export general ledger entries as CSV (same filters as index).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = JournalEntry::with('items')->orderBy('date', 'desc')->orderBy('id', 'desc');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('reference_type', $referenceType);
        }
        $entries = $query->limit(self::EXPORT_LIMIT)->get();

        $filename = 'general-ledger-entries-' . ($dateFrom ?: 'all') . '-to-' . ($dateTo ?: 'all') . '.csv';
        $filename = preg_replace('/[^a-z0-9\-\.]/i', '-', $filename);
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'id', 'description', 'reference_type', 'reference_id', 'total_debit', 'total_credit', 'items_count']);
            foreach ($entries as $entry) {
                $totalDebit = $entry->items->sum(fn ($i) => (float) $i->debit);
                $totalCredit = $entry->items->sum(fn ($i) => (float) $i->credit);
                fputcsv($out, [
                    $entry->date->format('Y-m-d'),
                    $entry->id,
                    $entry->description,
                    $entry->reference_type ?? '',
                    $entry->reference_id ?? '',
                    round($totalDebit, 2),
                    round($totalCredit, 2),
                    $entry->items->count(),
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export general ledger entries as PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = JournalEntry::with('items')->orderBy('date', 'desc')->orderBy('id', 'desc');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('reference_type', $referenceType);
        }
        $entries = $query->limit(self::EXPORT_LIMIT)->get()->map(function (JournalEntry $entry) {
            $totalDebit = $entry->items->sum(fn ($i) => (float) $i->debit);
            $totalCredit = $entry->items->sum(fn ($i) => (float) $i->credit);
            return [
                'date' => $entry->date->format('Y-m-d'),
                'id' => $entry->id,
                'description' => $entry->description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'items_count' => $entry->items->count(),
            ];
        });

        $company = $this->reportCompany();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.general-ledger-entries', [
            'entries' => $entries,
            'company' => $company,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ])->setPaper('a4', 'landscape');
        $filename = 'general-ledger-entries-' . ($dateFrom ?: 'all') . '-to-' . ($dateTo ?: 'all') . '.pdf';
        $filename = preg_replace('/[^a-z0-9\-\.]/i', '-', $filename);
        return $pdf->download($filename);
    }

    /**
     * Export general ledger report (transaction lines) as CSV.
     */
    public function exportReportCsv(Request $request): StreamedResponse
    {
        $query = JournalItem::with('journalEntry')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_items.*')
            ->orderBy('journal_entries.date', 'desc')
            ->orderBy('journal_entries.id', 'desc')
            ->orderBy('journal_items.id', 'asc');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');
        $accountCode = $request->input('account_code');
        if ($dateFrom) {
            $query->whereDate('journal_entries.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('journal_entries.date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('journal_entries.reference_type', $referenceType);
        }
        if ($accountCode) {
            $query->where('journal_items.account_code', $accountCode);
        }
        $items = $query->limit(self::EXPORT_LIMIT)->get();
        $accountCodes = $items->pluck('account_code')->unique()->filter()->values()->all();
        $accountsMap = $accountCodes ? Account::whereIn('code', $accountCodes)->pluck('name', 'code')->toArray() : [];

        $filename = 'general-ledger-report-' . ($dateFrom ?: 'all') . '-to-' . ($dateTo ?: 'all') . '.csv';
        $filename = preg_replace('/[^a-z0-9\-\.]/i', '-', $filename);
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($items, $accountsMap) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['date', 'entry_id', 'description', 'account_code', 'account_name', 'debit', 'credit', 'reference_type']);
            foreach ($items as $item) {
                $entry = $item->journalEntry;
                fputcsv($out, [
                    $entry->date->format('Y-m-d'),
                    $entry->id,
                    $entry->description,
                    $item->account_code,
                    $accountsMap[$item->account_code] ?? '',
                    (float) $item->debit,
                    (float) $item->credit,
                    $entry->reference_type ?? '',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Export general ledger report as PDF.
     */
    public function exportReportPdf(Request $request)
    {
        $query = JournalItem::with('journalEntry')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_items.*')
            ->orderBy('journal_entries.date', 'desc')
            ->orderBy('journal_entries.id', 'desc')
            ->orderBy('journal_items.id', 'asc');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $referenceType = $request->input('reference_type');
        $accountCode = $request->input('account_code');
        if ($dateFrom) {
            $query->whereDate('journal_entries.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('journal_entries.date', '<=', $dateTo);
        }
        if ($referenceType && in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $query->where('journal_entries.reference_type', $referenceType);
        }
        if ($accountCode) {
            $query->where('journal_items.account_code', $accountCode);
        }
        $items = $query->limit(self::EXPORT_LIMIT)->get();
        $accountCodes = $items->pluck('account_code')->unique()->filter()->values()->all();
        $accountsMap = $accountCodes ? Account::whereIn('code', $accountCodes)->pluck('name', 'code')->toArray() : [];
        $transactions = $items->map(function (JournalItem $item) use ($accountsMap) {
            $entry = $item->journalEntry;
            return [
                'date' => $entry->date->format('Y-m-d'),
                'entry_id' => $entry->id,
                'description' => $entry->description,
                'account_code' => $item->account_code,
                'account_name' => $accountsMap[$item->account_code] ?? '',
                'debit' => (float) $item->debit,
                'credit' => (float) $item->credit,
                'reference_type' => $entry->reference_type ?? '',
            ];
        });

        $company = $this->reportCompany();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.general-ledger-report', [
            'transactions' => $transactions,
            'company' => $company,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ])->setPaper('a4', 'landscape');
        $filename = 'general-ledger-report-' . ($dateFrom ?: 'all') . '-to-' . ($dateTo ?: 'all') . '.pdf';
        $filename = preg_replace('/[^a-z0-9\-\.]/i', '-', $filename);
        return $pdf->download($filename);
    }

    protected function reportCompany(): array
    {
        $user = request()->user();
        if ($user && $user->tenant_id) {
            $tenant = \App\Models\Tenant::find($user->tenant_id);
            $data = $tenant?->data ?? [];
            $c = $data['company'] ?? [];
            $name = $c['display_name'] ?? $c['legal_name'] ?? config('invoice.company.name');
            $addressParts = array_filter([$c['street'] ?? '', $c['city'] ?? '', $c['state'] ?? '', $c['postcode'] ?? '', $c['country'] ?? '']);
            $address = implode(', ', $addressParts);
            return ['name' => $name ?: config('invoice.company.name'), 'address' => $address ?: config('invoice.company.address')];
        }
        return config('invoice.company');
    }
}
