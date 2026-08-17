<?php

namespace App\Http\Controllers;

use App\Models\ConsolidatedEInvoice;
use App\Models\Invoice;
use App\Services\MyInvoisService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConsolidatedEInvoiceController extends Controller
{
    public function __construct(protected MyInvoisService $myinvois) {}

    public function index()
    {
        return Inertia::render('MyInvois/Consolidated', [
            'gaps'     => MyInvoisService::companyGaps(tenant()),
            'batches'  => ConsolidatedEInvoice::query()->orderByDesc('id')->get(),
            'invoices' => Invoice::query()
                ->whereNotIn('status', ['draft', 'void'])
                ->where(function ($q) {
                    $q->whereNull('lhdn_uuid')->orWhere('lhdn_status', 'pending');
                })
                ->where('is_consolidated', false)
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn (Invoice $inv) => [
                    'id'             => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'customer_name'  => $inv->customer?->name,
                    'total_amount'   => $inv->total_amount,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
        ]);
        $invoices = Invoice::query()->whereIn('id', $request->input('invoice_ids'))->get()->all();
        try {
            $batch = $this->myinvois->consolidate($invoices, $request->input('period_from'), $request->input('period_to'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Consolidated e-invoice '.$batch->document_number.' submitted.');
    }

    public function cancel(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            $this->myinvois->cancel(ConsolidatedEInvoice::findOrFail($id), $request->input('reason'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Consolidated e-invoice cancelled.');
    }
}
