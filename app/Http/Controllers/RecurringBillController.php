<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\Supplier;
use App\Services\RecurringBillService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecurringBillController extends Controller
{
    public function __construct(protected RecurringBillService $templates) {}

    public function index()
    {
        return Inertia::render('RecurringBills/Index', [
            'templates' => RecurringBill::query()->with('supplier:id,name')->orderByDesc('id')->get(),
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
