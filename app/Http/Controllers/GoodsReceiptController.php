<?php

namespace App\Http\Controllers;

use App\Jobs\SendGoodsReceiptEmail;
use App\Models\GoodsReceipt;
use App\Services\GoodsReceiptService;
use App\Services\PurchasesDocumentTrail;
use App\Support\IndexFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function __construct(protected GoodsReceiptService $receipts) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request);
        $statuses = ['received', 'billed', 'cancelled'];

        $orders = GoodsReceipt::query()
            ->with('supplier:id,name')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('grn_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '' && in_array($filters['status'], $statuses, true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('GoodsReceipts/Index', [
            'orders'  => $orders,
            'filters' => $filters,
        ]);
    }

    public function show($id)
    {
        // Load full bill rows — partial selects omit FKs and break document trail BelongsTo.
        $order = GoodsReceipt::with(['items', 'supplier', 'purchaseOrder', 'bills'])->findOrFail($id);

        return Inertia::render('GoodsReceipts/Show', [
            'order' => $order,
            'trail' => app(PurchasesDocumentTrail::class)->forGoodsReceipt($order),
            'company' => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function update(Request $request, $id)
    {
        $grn = GoodsReceipt::findOrFail($id);
        $request->validate([
            'notes'         => 'nullable|string',
            'received_date' => 'nullable|date',
        ]);
        try {
            $this->receipts->update($grn, $request->only(['notes', 'received_date']));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Goods receipt updated.');
    }

    public function returnFull($id)
    {
        try {
            $this->receipts->returnFull(GoodsReceipt::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Goods receipt returned.');
    }

    public function emailPdf($id)
    {
        $grn = GoodsReceipt::with(['supplier'])->findOrFail($id);
        $supplier = $grn->supplier;
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
        SendGoodsReceiptEmail::dispatch($grn->id, $recipients, $company);

        return redirect()->back()->with('success', 'Goods receipt email queued.');
    }

    public function convertToBill($id)
    {
        try {
            $bill = $this->receipts->convertToBill(GoodsReceipt::findOrFail($id), auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $bill->id)->with('success', 'Draft bill created from goods receipt.');
    }

    public function downloadPdf($id)
    {
        $grn = GoodsReceipt::with(['items', 'supplier'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Goods Receipt',
            'number'     => $grn->grn_number,
            'issue_date' => optional($grn->issue_date)->toDateString(),
            'customer'   => $grn->supplier,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $grn->items,
            'tax'        => 0,
            'total'      => $grn->items->sum('quantity'),
            'currency'   => $grn->currency ?? 'MYR',
            'notes'      => $grn->notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Goods-Receipt-{$grn->grn_number}.pdf");
    }
}
