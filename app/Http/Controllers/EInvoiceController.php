<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\LhdnMyInvoisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EInvoiceController extends Controller
{
    public function __construct(protected LhdnMyInvoisService $lhdn) {}

    /**
     * Display the E-Invoice dashboard with LHDN submission statuses.
     */
    public function index(Request $request)
    {
        $perPage      = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 15;
        }
        $search       = $request->input('search', '');
        $lhdnFilter   = $request->input('lhdn_status', '');

        $query = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->select(
                'invoices.id',
                'invoices.invoice_number',
                'invoices.status',
                'invoices.lhdn_status',
                'invoices.lhdn_uuid',
                'invoices.lhdn_submission_uid',
                'invoices.lhdn_long_id',
                'invoices.lhdn_submitted_at',
                'invoices.lhdn_error_message',
                'invoices.total_amount',
                'invoices.issue_date',
                'invoices.tax_amount',
                'customers.name as customer_name',
                'customers.tin as customer_tin',
            )
            ->whereNull('invoices.deleted_at')
            ->orderBy('invoices.created_at', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoices.invoice_number', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%");
            });
        }

        if ($lhdnFilter !== '') {
            $query->where('invoices.lhdn_status', $lhdnFilter);
        }

        // KPIs
        $allBase = DB::table('invoices')->whereNull('deleted_at');
        $kpis = [
            'total'     => (clone $allBase)->count(),
            'submitted' => (clone $allBase)->whereIn('lhdn_status', ['submitted', 'valid'])->count(),
            'valid'     => (clone $allBase)->where('lhdn_status', 'valid')->count(),
            'pending'   => (clone $allBase)->where('lhdn_status', 'pending')->count(),
            'invalid'   => (clone $allBase)->whereIn('lhdn_status', ['invalid', 'cancelled'])->count(),
        ];

        $paginator = $query->paginate($perPage)->withQueryString();

        return Inertia::render('EInvoice/Index', [
            'invoices'     => $paginator->items(),
            'paginator'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'filters'      => [
                'search'      => $search,
                'lhdn_status' => $lhdnFilter,
                'per_page'    => $perPage,
            ],
            'kpis'         => $kpis,
            'isConfigured' => $this->lhdn->isConfigured(),
            'lhdnEnv'      => config('lhdn.env', 'sandbox'),
        ]);
    }

    /**
     * Submit an invoice to LHDN MyInvoice.
     */
    public function submit(int $id)
    {
        $invoice = Invoice::with(['customer', 'items'])->findOrFail($id);
        $result  = $this->lhdn->submitDocument($invoice);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Refresh the LHDN status for a submitted invoice.
     */
    public function refresh(int $id)
    {
        $invoice = Invoice::findOrFail($id);
        $result  = $this->lhdn->refreshDocumentStatus($invoice);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Cancel a submitted/valid document at LHDN.
     */
    public function cancel(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:300',
        ]);

        $invoice = Invoice::findOrFail($id);
        $result  = $this->lhdn->cancelDocument(
            $invoice,
            $request->input('reason', 'Cancelled by user')
        );

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }
}
