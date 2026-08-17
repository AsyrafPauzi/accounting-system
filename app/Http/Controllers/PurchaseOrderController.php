<?php

namespace App\Http\Controllers;

use App\Jobs\SendPurchaseOrderEmail;
use App\Models\Account;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\PurchasesDocumentTrail;
use App\Support\IndexFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $orders,
        protected GoodsReceiptService $receipts,
    ) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request);
        $statuses = ['draft', 'confirmed', 'partially_received', 'received', 'billed', 'cancelled'];

        $orders = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('po_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '' && in_array($filters['status'], $statuses, true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('PurchaseOrders/Index', [
            'orders'        => $orders,
            'filters'       => $filters,
            'base_currency' => tenant()?->base_currency ?? 'MYR',
        ]);
    }

    public function show($id)
    {
        // Load full bill rows — partial selects omit FKs and break document trail BelongsTo.
        $order = PurchaseOrder::with(['items', 'supplier', 'goodsReceipts', 'bills'])->findOrFail($id);
        $lockReason = null;
        try {
            $this->orders->assertEditable($order);
        } catch (\LogicException $e) {
            $lockReason = $e->getMessage();
        }

        return Inertia::render('PurchaseOrders/Show', [
            'order' => $order,
            'trail' => app(PurchasesDocumentTrail::class)->forPurchaseOrder($order),
            'editable' => $lockReason === null,
            'lock_reason' => $lockReason,
            'company' => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function edit($id)
    {
        $order = PurchaseOrder::with(['items', 'supplier'])->findOrFail($id);
        $lockReason = null;
        try {
            $this->orders->assertEditable($order);
        } catch (\LogicException $e) {
            $lockReason = $e->getMessage();
        }

        return Inertia::render('PurchaseOrders/Edit', [
            'order' => $order,
            'editable' => $lockReason === null,
            'lock_reason' => $lockReason,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'unit_price', 'tax_rate', 'account_code']),
            'expenseAccounts' => Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $request->validate([
            'supplier_id'         => 'required|exists:suppliers,id',
            'issue_date'          => 'required|date',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);
        try {
            $this->orders->update($po, $request->except('items'), $request->input('items'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase-orders.show', $po->id)->with('success', 'Purchase order updated.');
    }

    public function cancel($id)
    {
        try {
            $this->orders->cancel(PurchaseOrder::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Purchase order cancelled.');
    }

    public function emailPdf($id)
    {
        $po = PurchaseOrder::with(['supplier'])->findOrFail($id);
        $supplier = $po->supplier;
        if (! $supplier) {
            return redirect()->back()->with('error', 'Supplier not found.');
        }
        $recipients = [];
        if ($supplier->email && filter_var($supplier->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $supplier->email;
        }
        if ($recipients === []) {
            return redirect()->back()->with('error', 'Supplier has no email.');
        }
        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        SendPurchaseOrderEmail::dispatch($po->id, $recipients, $company);

        return redirect()->back()->with('success', 'Purchase order email queued.');
    }

    public function create()
    {
        return Inertia::render('PurchaseOrders/Create', [
            'suppliers'        => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products'         => Product::query()->active()->orderBy('name')->get(['id', 'name', 'unit_price', 'tax_rate', 'account_code']),
            'expenseAccounts'  => Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']),
            'next_number'      => $this->orders->nextNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'         => 'required|exists:suppliers,id',
            'issue_date'          => 'required|date',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);
        $po = $this->orders->create(array_merge($request->except('items'), ['created_by' => auth()->id()]), $request->input('items'));

        return redirect()->route('purchase-orders.show', $po->id)->with('success', 'Purchase order saved.');
    }

    public function receive(Request $request, $id)
    {
        $qtys = [];
        foreach ($request->input('quantities', []) as $itemId => $qty) {
            $qtys[(int) $itemId] = (float) $qty;
        }
        try {
            $grn = $this->receipts->fromPurchaseOrder(PurchaseOrder::with('items')->findOrFail($id), $qtys, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('goods-receipts.show', $grn->id)->with('success', 'Goods receipt created.');
    }

    public function convertToBill(Request $request, $id)
    {
        $qtys = [];
        foreach ($request->input('quantities', []) as $itemId => $qty) {
            $qtys[(int) $itemId] = (float) $qty;
        }
        try {
            $bill = $this->orders->convertToBill(PurchaseOrder::with('items')->findOrFail($id), $qtys, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $bill->id)->with('success', 'Draft bill created from purchase order.');
    }

    public function downloadPdf($id)
    {
        $po = PurchaseOrder::with(['items', 'supplier'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Purchase Order',
            'number'     => $po->po_number,
            'issue_date' => optional($po->issue_date)->toDateString(),
            'customer'   => $po->supplier,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $po->items,
            'tax'        => $po->tax_amount,
            'total'      => $po->total_amount,
            'currency'   => $po->currency,
            'notes'      => $po->notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Purchase-Order-{$po->po_number}.pdf");
    }
}
