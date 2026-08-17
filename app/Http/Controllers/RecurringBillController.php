<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\Supplier;
use App\Services\RecurringBillService;
use App\Support\IndexFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecurringBillController extends Controller
{
    public function __construct(protected RecurringBillService $templates) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request, 10);
        $status = $filters['status'];

        $templates = RecurringBill::query()
            ->with('supplier:id,name,email')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'paused', fn ($q) => $q->where('is_active', false))
            ->when($status === 'due', fn ($q) => $q->due())
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $counts = [
            'all'    => RecurringBill::count(),
            'active' => RecurringBill::where('is_active', true)->count(),
            'paused' => RecurringBill::where('is_active', false)->count(),
            'due'    => RecurringBill::query()->due()->count(),
        ];

        return Inertia::render('RecurringBills/Index', [
            'templates' => $templates,
            'filters'   => $filters,
            'counts'    => $counts,
        ]);
    }

    public function create()
    {
        return Inertia::render('RecurringBills/Create', [
            'suppliers'       => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'expenseAccounts' => Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'              => 'required|exists:suppliers,id',
            'cadence'                  => 'required|in:weekly,monthly,quarterly,yearly',
            'start_date'               => 'required|date',
            'items'                    => 'required|array|min:1',
            'items.*.account_code'     => 'required|string',
            'items.*.description'      => 'required|string',
            'items.*.amount'           => 'required|numeric|min:0.01',
        ]);
        $template = $this->templates->create(array_merge($request->except('items'), ['created_by' => auth()->id()]), $request->input('items'));

        return redirect()->route('recurring-bills.show', $template->id)->with('success', 'Recurring bill saved.');
    }

    public function show($id)
    {
        return Inertia::render('RecurringBills/Show', [
            'template' => RecurringBill::with(['items', 'supplier'])->findOrFail($id),
            'company'  => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function runNow($id)
    {
        try {
            $bill = $this->templates->generateOne(RecurringBill::with('items')->findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $bill->id)->with('success', 'Bill generated from template.');
    }

    public function toggle($id)
    {
        $template = RecurringBill::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        return redirect()->back()->with('success', $template->is_active ? 'Template resumed.' : 'Template paused.');
    }
}
