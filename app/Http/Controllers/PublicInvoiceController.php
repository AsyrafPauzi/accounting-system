<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoicePayNowService;
use App\Services\InvoiceService;
use App\Support\ShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class PublicInvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoices,
        private InvoicePayNowService $payNow,
    ) {}

    public function show(Request $request, string $uuid): Response
    {
        $invoice = $this->resolveInvoice($uuid);
        $tenant = $this->resolveTenant($request);
        $company = $tenant?->getCompanyDetails() ?? config('invoice.company', ['name' => config('app.name')]);
        $balance = $this->invoices->remainingBalance($invoice);

        $tenantId = $tenant?->id;
        $share = ShareLink::publicSigned(
            'public.invoices.show',
            ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId],
            'Invoice '.$invoice->invoice_number
        );
        $pdfShare = ShareLink::publicSigned(
            'public.invoices.download',
            ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId],
            'Invoice '.$invoice->invoice_number
        );
        $payUrl = null;
        if ($balance > 0 && $tenant && $this->payNow->isConfigured($tenant) && ! in_array($invoice->status, ['draft', 'void', 'paid'], true)) {
            $public = rtrim((string) (config('app.public_url') ?: config('app.url')), '/');
            $previous = config('app.url');
            URL::forceRootUrl($public);
            URL::forceScheme(str_starts_with($public, 'https://') ? 'https' : 'http');
            try {
                $payUrl = URL::temporarySignedRoute(
                    'public.invoices.pay',
                    now()->addDays(30),
                    ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId]
                );
            } finally {
                URL::forceRootUrl($previous);
                URL::forceScheme(parse_url((string) $previous, PHP_URL_SCHEME) ?: 'http');
            }
        }

        $this->invoices->markViewed($invoice);

        return response()->view('public.invoice', [
            'invoice'       => $invoice,
            'company'       => $company,
            'balance'       => $balance,
            'currency'      => strtoupper($invoice->currency ?? 'MYR'),
            'payConfigured' => $tenant ? $this->payNow->isConfigured($tenant) : false,
            'canPay'        => $balance > 0 && ! in_array($invoice->status, ['draft', 'void', 'paid'], true),
            'htmlUrl'       => $share['public_url'],
            'pdfUrl'        => $pdfShare['public_url'],
            'payUrl'        => $payUrl,
            'whatsappUrl'   => $share['whatsapp_url'],
        ]);
    }

    public function pay(Request $request, string $uuid): RedirectResponse
    {
        $invoice = $this->resolveInvoice($uuid);
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            abort(404);
        }

        $url = $this->payNow->paymentUrl($invoice, $tenant);
        if (! $url) {
            return redirect()->back()->with('error', 'Online payment is not available for this invoice.');
        }

        return redirect()->away($url);
    }

    private function resolveInvoice(string $uuid): Invoice
    {
        return Invoice::with(['items', 'customer'])->where('uuid', $uuid)->firstOrFail();
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
