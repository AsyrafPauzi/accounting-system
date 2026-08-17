<?php

namespace App\Http\Controllers;

use App\Jobs\SendSalesOrderEmail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\DeliveryOrderService;
use App\Services\SalesDocumentTrail;
use App\Services\SalesOrderService;
use App\Support\IndexFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    public function __construct(
        protected SalesOrderService $orders,
        protected DeliveryOrderService $deliveries,
    ) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request);

        $orders = SalesOrder::query()
            ->with([
                'customer:id,name',
                'deliveryOrders:id,sales_order_id,do_number,status',
                'invoices:id,sales_order_id,invoice_number,status',
            ])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('so_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '' && in_array($filters['status'], SalesOrder::STATUSES, true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('SalesOrders/Index', [
            'orders'        => $orders,
            'filters'       => $filters,
            'base_currency' => tenant()?->base_currency ?? 'MYR',
        ]);
    }

    public function show($id)
    {
        $order = SalesOrder::with(['items', 'customer', 'deliveryOrders', 'invoices:id,invoice_number,status,sales_order_id,delivery_order_id,estimate_id'])->findOrFail($id);

        return Inertia::render('SalesOrders/Show', [
            'order'          => $order,
            'document_trail' => app(SalesDocumentTrail::class)->forSalesOrder($order),
            'company'        => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('SalesOrders/Create', [
            'customers'   => Customer::query()->orderBy('name')->get(['id', 'name', 'tin']),
            'products'    => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate', 'account_code']),
            'next_number' => $this->orders->nextNumber(),
            'customer_id' => $request->query('customer_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'         => 'required|exists:customers,id',
            'issue_date'          => 'required|date',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);
        $so = $this->orders->create(array_merge($request->except('items'), ['created_by' => auth()->id()]), $request->input('items'));

        return redirect()->route('sales-orders.show', $so->id)->with('success', 'Sales order saved.');
    }

    public function edit($id)
    {
        $order = SalesOrder::with(['items', 'customer'])->findOrFail($id);
        $editable = true;
        $lockReason = null;
        try {
            $this->orders->assertEditable($order);
        } catch (\LogicException $e) {
            $editable = false;
            $lockReason = $e->getMessage();
        }

        return Inertia::render('SalesOrders/Edit', [
            'order'       => $order,
            'editable'    => $editable,
            'lock_reason' => $lockReason,
            'customers'   => Customer::query()->orderBy('name')->get(['id', 'name', 'tin']),
            'products'    => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate', 'account_code']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $so = SalesOrder::findOrFail($id);
        $request->validate([
            'customer_id'         => 'required|exists:customers,id',
            'issue_date'          => 'required|date',
            'expected_date'       => 'nullable|date',
            'customer_notes'      => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);

        try {
            $this->orders->update($so, $request->except('items'), $request->input('items'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales-orders.show', $so->id)->with('success', 'Sales order updated.');
    }

    public function cancel($id)
    {
        try {
            $this->orders->cancel(SalesOrder::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Sales order cancelled.');
    }

    public function batchCreate()
    {
        return Inertia::render('SalesOrders/Batch', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'rows'                 => 'required|array|min:1|max:200',
            'rows.*.customer_id'   => 'required|exists:customers,id',
            'rows.*.description'   => 'required|string',
            'rows.*.quantity'      => 'required|numeric|min:0.01',
            'rows.*.unit_price'    => 'required|numeric',
            'rows.*.tax_rate'      => 'nullable|numeric',
            'rows.*.issue_date'    => 'nullable|date',
        ]);

        $created = 0;
        foreach ($request->input('rows') as $row) {
            $this->orders->create([
                'customer_id' => $row['customer_id'],
                'issue_date'  => $row['issue_date'] ?? now()->toDateString(),
                'currency'    => $row['currency'] ?? 'MYR',
                'status'      => 'confirmed',
                'created_by'  => auth()->id(),
            ], [[
                'description' => $row['description'],
                'quantity'    => $row['quantity'],
                'unit_price'  => $row['unit_price'],
                'tax_rate'    => $row['tax_rate'] ?? 0,
            ]]);
            $created++;
        }

        return redirect()->route('sales-orders.index')->with('success', "{$created} sales order(s) created.");
    }

    public function emailPdf($id)
    {
        $so = SalesOrder::with(['customer'])->findOrFail($id);
        $customer = $so->customer;
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
        SendSalesOrderEmail::dispatch($so->id, $recipients, $company);

        return redirect()->back()->with('success', 'Sales order email queued.');
    }

    public function deliver(Request $request, $id)
    {
        $so = SalesOrder::with('items')->findOrFail($id);
        $qtys = [];
        foreach ($request->input('quantities', []) as $itemId => $qty) {
            $qtys[(int) $itemId] = (float) $qty;
        }
        try {
            $do = $this->deliveries->fromSalesOrder($so, $qtys, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('delivery-orders.show', $do->id)->with('success', 'Delivery order created.');
    }

    public function convertToInvoice(Request $request, $id)
    {
        $so = SalesOrder::with('items')->findOrFail($id);
        $qtys = [];
        foreach ($request->input('quantities', []) as $itemId => $qty) {
            $qtys[(int) $itemId] = (float) $qty;
        }
        try {
            $invoice = $this->orders->convertToInvoice($so, $qtys, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)->with('success', 'Draft invoice created from sales order.');
    }

    public function downloadPdf($id)
    {
        $so = SalesOrder::with(['items', 'customer'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Sales Order',
            'number'     => $so->so_number,
            'issue_date' => optional($so->issue_date)->toDateString(),
            'customer'   => $so->customer,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $so->items,
            'tax'        => $so->tax_amount,
            'total'      => $so->total_amount,
            'currency'   => $so->currency,
            'notes'      => $so->customer_notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Sales-Order-{$so->so_number}.pdf");
    }
}
