<?php

namespace App\Http\Controllers;

use App\Mail\CustomerStatementEmail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\CustomerStatementService;
use App\Support\IndexFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CustomerStatementController extends Controller
{
    public function __construct(private CustomerStatementService $statements) {}

    /**
     * Customer picker page. Shows the directory with current outstanding so
     * the user can decide whose statement to generate.
     */
    public function index(Request $request): Response
    {
        $this->authorize('customers.view');

        $filters = IndexFilters::from($request, 10);
        $search = $filters['search'];
        $status = $filters['status'];
        $openInvoice = fn ($q) => $q->whereNotIn('status', ['draft', 'void'])
            ->whereColumn('amount_paid', '<', 'total_amount');

        $customers = Customer::query()
            ->select(['id', 'name', 'email', 'tin'])
            ->withCount(['invoices as outstanding_invoices_count' => $openInvoice])
            ->withSum(['invoices as outstanding_amount' => function ($q) {
                $q->whereNotIn('status', ['draft', 'void']);
            }], DB::raw('total_amount - amount_paid'))
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tin', 'like', "%{$search}%");
            }))
            ->when($status === 'outstanding', fn ($q) => $q->whereHas('invoices', $openInvoice))
            ->when($status === 'settled', fn ($q) => $q->whereDoesntHave('invoices', $openInvoice))
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $totalCount = Customer::count();
        $outstandingCount = Customer::whereHas('invoices', $openInvoice)->count();
        $outstandingTotal = (float) Invoice::query()
            ->whereNotIn('status', ['draft', 'void'])
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as t')
            ->value('t');

        return Inertia::render('CustomerStatements/Index', [
            'customers' => $customers,
            'filters' => $filters,
            'base_currency' => $this->tenantBaseCurrency(),
            'totalCount' => $totalCount,
            'outstandingCount' => $outstandingCount,
            'settledCount' => $totalCount - $outstandingCount,
            'outstandingTotal' => round($outstandingTotal, 2),
        ]);
    }

    /**
     * The actual statement view for a given customer + date range.
     * Range defaults to start-of-month → today when not provided.
     */
    public function show(Request $request, int $customerId): Response
    {
        $this->authorize('customers.view');

        $customer = Customer::findOrFail($customerId);
        [$from, $to] = $this->resolveRange($request);

        $statement = $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));
        $openInvoices = app(\App\Services\InvoiceService::class)->openInvoicesForCustomer($customer->id);

        return Inertia::render('CustomerStatements/Show', [
            'customer'           => $customer,
            'statement'          => $statement,
            'company'            => $this->companyDetails(),
            'base_currency'      => $this->tenantBaseCurrency(),
            'open_invoices'      => $openInvoices,
            'pay_now_configured' => app(\App\Services\InvoicePayNowService::class)->isConfigured(),
        ]);
    }

    /**
     * Inline PDF preview (for opening in a new tab).
     */
    public function previewPdf(Request $request, int $customerId): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderPdf($request, $customerId, attachment: false);
    }

    /**
     * Force-download PDF.
     */
    public function downloadPdf(Request $request, int $customerId): \Symfony\Component\HttpFoundation\Response
    {
        return $this->renderPdf($request, $customerId, attachment: true);
    }

    /**
     * Email the statement (with PDF attached) to the customer's billing
     * contacts, falling back to the main `customers.email` if no billing
     * contact is set up.
     */
    public function email(Request $request, int $customerId): RedirectResponse
    {
        $this->authorize('customers.view');

        $customer = Customer::with('contacts')->findOrFail($customerId);
        [$from, $to] = $this->resolveRange($request);

        $recipients = $this->resolveRecipients($customer);
        if (empty($recipients)) {
            return redirect()->back()->with('error', 'Customer has no email or billing contact on file.');
        }

        $statement = $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));
        $company = $this->companyDetails();

        $pdfBytes = Pdf::loadView('pdf.customer_statement', compact('customer', 'statement', 'company'))
            ->setPaper('a4', 'portrait')
            ->output();

        $filename = 'Statement-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $customer->name) . '-' . $from . '-to-' . $to . '.pdf';

        try {
            Mail::to($recipients)->send(new CustomerStatementEmail($customer, $statement, $company, $pdfBytes, $filename));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()->with('error', 'Could not send the statement email: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Statement emailed to ' . implode(', ', $recipients) . '.');
    }

    private function renderPdf(Request $request, int $customerId, bool $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('customers.view');

        $customer = Customer::findOrFail($customerId);
        [$from, $to] = $this->resolveRange($request);

        $statement = $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));
        $company = $this->companyDetails();

        $pdf = Pdf::loadView('pdf.customer_statement', compact('customer', 'statement', 'company'))
            ->setPaper('a4', 'portrait');

        $filename = 'Statement-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $customer->name) . '-' . $from . '-to-' . $to . '.pdf';

        return $pdf->stream($filename, ['Attachment' => $attachment]);
    }

    /**
     * Validate + sanitise the date range from the request, falling back to
     * a sensible default (this month so far) when missing.
     *
     * @return array{0:string,1:string}
     */
    private function resolveRange(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $defaults = $this->statements->defaultWindow();
        $from = $request->input('from', $defaults['from']);
        $to = $request->input('to', $defaults['to']);

        // Cap at 5 years to avoid runaway PDFs.
        if (Carbon::parse($from)->diffInYears(Carbon::parse($to)) > 5) {
            $from = Carbon::parse($to)->subYears(5)->toDateString();
        }

        return [$from, $to];
    }

    /**
     * Email recipients: prefer billing contacts, fall back to customers.email.
     *
     * @return array<int,string>
     */
    private function resolveRecipients(Customer $customer): array
    {
        $billing = $customer->contacts
            ->where('type', 'billing')
            ->filter(fn ($c) => $c->email && filter_var($c->email, FILTER_VALIDATE_EMAIL))
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        if (! empty($billing)) {
            return $billing;
        }

        if ($customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return [$customer->email];
        }

        return [];
    }

    private function companyDetails(): array
    {
        if (function_exists('tenant') && tenant()) {
            return tenant()->getCompanyDetails();
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t) {
                return $t->getCompanyDetails();
            }
        }

        return config('invoice.company') ?? ['name' => config('app.name')];
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }
}
