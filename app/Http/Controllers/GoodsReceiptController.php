<?php

namespace App\Http\Controllers;

use App\Jobs\SendGoodsReceiptEmail;
use App\Models\GoodsReceipt;
use App\Services\GoodsReceiptService;
use App\Services\PurchasesDocumentTrail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function __construct(protected GoodsReceiptService $receipts) {}

    public function index()
    {
        return Inertia::render('GoodsReceipts/Index', [
            'orders' => GoodsReceipt::query()->with('supplier:id,name')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show($id)
    {
        $order = GoodsReceipt::with(['items', 'supplier', 'purchaseOrder', 'bills:id,bill_number,goods_receipt_id,status'])->findOrFail($id);

        return Inertia::render('GoodsReceipts/Show', [
            'order' => $order,
            'trail' => app(PurchasesDocumentTrail::class)->forGoodsReceipt($order),
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
