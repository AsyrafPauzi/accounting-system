<?php

namespace App\Http\Controllers;

use App\Jobs\SendDeliveryOrderEmail;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderService;
use App\Services\SalesDocumentTrail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryOrderController extends Controller
{
    public function __construct(protected DeliveryOrderService $deliveries) {}

    public function index()
    {
        $orders = DeliveryOrder::query()
            ->with([
                'customer:id,name',
                'salesOrder:id,so_number',
                'invoices:id,invoice_number,delivery_order_id,sales_order_id,estimate_id,status',
            ])
            ->orderByDesc('id')
            ->paginate(20);

        return Inertia::render('DeliveryOrders/Index', ['orders' => $orders]);
    }

    public function show($id)
    {
        $order = DeliveryOrder::with(['items', 'customer', 'salesOrder', 'invoices:id,invoice_number,delivery_order_id,sales_order_id,estimate_id,status'])->findOrFail($id);

        return Inertia::render('DeliveryOrders/Show', [
            'order'          => $order,
            'document_trail' => app(SalesDocumentTrail::class)->forDeliveryOrder($order),
        ]);
    }

    public function edit($id)
    {
        $order = DeliveryOrder::with(['items', 'customer', 'salesOrder'])->findOrFail($id);
        $editable = true;
        $lockReason = null;
        try {
            $this->deliveries->assertEditable($order);
        } catch (\LogicException $e) {
            $editable = false;
            $lockReason = $e->getMessage();
        }

        return Inertia::render('DeliveryOrders/Edit', [
            'order'       => $order,
            'editable'    => $editable,
            'lock_reason' => $lockReason,
        ]);
    }

    public function update(Request $request, $id)
    {
        $do = DeliveryOrder::findOrFail($id);
        $request->validate([
            'issue_date'     => 'nullable|date',
            'delivery_date'  => 'nullable|date',
            'customer_notes' => 'nullable|string',
        ]);

        try {
            $this->deliveries->update($do, $request->only(['issue_date', 'delivery_date', 'customer_notes']));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('delivery-orders.show', $do->id)->with('success', 'Delivery order updated.');
    }

    public function returnFull($id)
    {
        try {
            $this->deliveries->returnFull(DeliveryOrder::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Delivery order returned.');
    }

    public function emailPdf($id)
    {
        $do = DeliveryOrder::with(['customer'])->findOrFail($id);
        $customer = $do->customer;
        if (! $customer) {
            return redirect()->back()->with('error', 'Customer not found.');
        }
        $recipients = [];
        if ($customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $customer->email;
        }
        if ($recipients === []) {
            return redirect()->back()->with('error', 'Customer has no email.');
        }
        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        SendDeliveryOrderEmail::dispatch($do->id, $recipients, $company);

        return redirect()->back()->with('success', 'Delivery order email queued.');
    }

    public function convertToInvoice($id)
    {
        try {
            $invoice = $this->deliveries->convertToInvoice(DeliveryOrder::findOrFail($id), auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)->with('success', 'Draft invoice created from delivery order.');
    }

    public function downloadPdf($id)
    {
        $do = DeliveryOrder::with(['items', 'customer', 'salesOrder.items'])->findOrFail($id);
        $items = $do->pdfLineItems();
        $tax = round($items->sum(fn ($i) => $i->amount * $i->tax_rate / 100), 2);
        $total = round($items->sum('amount') + $tax, 2);

        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Delivery Order',
            'number'     => $do->do_number,
            'issue_date' => optional($do->issue_date)->toDateString(),
            'customer'   => $do->customer,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $items,
            'tax'        => $tax,
            'total'      => $total,
            'currency'   => $do->currency ?? 'MYR',
            'notes'      => $do->customer_notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Delivery-Order-{$do->do_number}.pdf");
    }
}
