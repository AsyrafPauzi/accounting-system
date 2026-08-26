<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\CustomerPortalService;
use App\Services\CustomerStatementService;
use App\Services\InvoicePayNowService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class CustomerPortalController extends Controller
{
    public function __construct(
        private CustomerPortalService $portal,
        private InvoiceService $invoices,
        private CustomerStatementService $statements,
        private InvoicePayNowService $payNow,
    ) {}

    public function dashboard(Request $request, string $token): Response
    {
        $portalToken = $this->resolveToken($token);
        $customer = $portalToken->customer;
        $company = $this->companyDetails($request);

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void'])
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get()
            ->map(function (Invoice $invoice) use ($request) {
                $balance = $this->invoices->remainingBalance($invoice);

                return [
                    'uuid'           => $invoice->uuid,
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date'     => $invoice->issue_date,
                    'due_date'       => $invoice->due_date,
                    'status'         => $invoice->status,
                    'total_amount'   => (float) $invoice->total_amount,
                    'balance'        => $balance,
                    'currency'       => strtoupper($invoice->currency ?? 'MYR'),
                    'view_url'       => $this->signedInvoiceUrl($request, $invoice),
                    'pay_url'        => $balance > 0 ? $this->signedPayUrl($request, $invoice) : null,
                ];
            });

        $openBalance = $this->invoices->outstandingByCustomer()[$customer->id] ?? 0.0;

        $defaults = $this->statements->defaultWindow();
        $statementUrl = URL::temporarySignedRoute(
            'portal.statement.pdf',
            $portalToken->expires_at,
            ['token' => $token, 'tenant_id' => $request->query('tenant_id'), 'from' => $defaults['from'], 'to' => $defaults['to']],
        );

        return response()->view('portal.dashboard', [
            'customer'      => $customer,
            'company'       => $company,
            'invoices'      => $invoices,
            'open_balance'  => round($openBalance, 2),
            'currency'      => strtoupper($customer->currency ?? 'MYR'),
            'statement_url' => $statementUrl,
        ]);
    }

    public function statementPdf(Request $request, string $token): Response
    {
        $portalToken = $this->resolveToken($token);
        $customer = $portalToken->customer;

        $from = $request->query('from', $this->statements->defaultWindow()['from']);
        $to = $request->query('to', $this->statements->defaultWindow()['to']);
        $statement = $this->statements->build($customer, Carbon::parse($from), Carbon::parse($to));
        $company = $this->companyDetails($request);

        $pdf = Pdf::loadView('pdf.customer_statement', compact('customer', 'statement', 'company'))
            ->setPaper('a4', 'portrait');

        $filename = 'Statement-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $customer->name).'.pdf';

        return $pdf->download($filename);
    }

    public function payInvoice(Request $request, string $token, string $uuid): RedirectResponse
    {
        $portalToken = $this->resolveToken($token);
        $invoice = Invoice::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $portalToken->customer_id)
            ->firstOrFail();

        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            abort(404);
        }

        $url = $this->payNow->paymentUrl($invoice, $tenant);
        if (! $url) {
            return redirect()->back()->with('error', 'Online payment is not available.');
        }

        return redirect()->away($url);
    }

    private function resolveToken(string $token)
    {
        $portalToken = $this->portal->findValidToken($token);
        if (! $portalToken) {
            abort(403, 'This portal link has expired. Please contact the company for a new link.');
        }

        return $portalToken;
    }

    private function signedInvoiceUrl(Request $request, Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'public.invoices.show',
            now()->addDays(30),
            ['uuid' => $invoice->uuid, 'tenant_id' => $request->query('tenant_id')],
        );
    }

    private function signedPayUrl(Request $request, Invoice $invoice): ?string
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant || ! $this->payNow->isConfigured($tenant)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'public.invoices.pay',
            now()->addDays(30),
            ['uuid' => $invoice->uuid, 'tenant_id' => $tenant->id],
        );
    }

    private function companyDetails(Request $request): array
    {
        $tenant = $this->resolveTenant($request);

        return $tenant?->getCompanyDetails() ?? config('invoice.company', ['name' => config('app.name')]);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        if (function_exists('tenant') && tenant()) {
            return tenant();
        }

        $tenantId = $request->query('tenant_id');

        return $tenantId ? Tenant::find($tenantId) : null;
    }
}
