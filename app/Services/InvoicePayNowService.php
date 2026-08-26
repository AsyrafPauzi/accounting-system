<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

class InvoicePayNowService
{
    public function provider(?Tenant $tenant = null): string
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        $provider = (string) ($tenant?->invoice_gateway ?? 'toyyibpay');

        return in_array($provider, ['toyyibpay', 'billplz', 'commercepay'], true)
            ? $provider
            : 'toyyibpay';
    }

    public function isConfigured(?Tenant $tenant = null): bool
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        if (! $tenant) {
            return false;
        }

        return match ($this->provider($tenant)) {
            'billplz' => filled($tenant->billplz_secret_key) && filled($tenant->billplz_collection_id),
            'commercepay' => filled($tenant->commercepay_username)
                && filled($tenant->commercepay_password)
                && filled($tenant->commercepay_secret_key),
            default => filled($tenant->toyyibpay_secret_key) && filled($tenant->toyyibpay_category_code),
        };
    }

    public function paymentUrl(Invoice $invoice, ?Tenant $tenant = null): ?string
    {
        $invoice->loadMissing('customer');
        $tenant ??= function_exists('tenant') ? tenant() : null;
        if (! $this->isConfigured($tenant)) {
            return null;
        }

        $balance = app(InvoiceService::class)->remainingBalance($invoice);
        if ($balance <= 0) {
            return null;
        }

        $ref = 'inv-'.$invoice->id.'-'.$tenant->id;
        [$returnUrl, $callbacks] = $this->publicPayUrls($invoice, $tenant);
        $provider = $this->provider($tenant);

        $url = match ($provider) {
            'billplz' => $this->billplzUrl($invoice, $tenant, $balance, $ref, $returnUrl, $callbacks['billplz']),
            'commercepay' => $this->commercepayUrl($invoice, $tenant, $balance, $ref, $returnUrl, $callbacks['commercepay']),
            default => $this->toyyibpayUrl($invoice, $tenant, $balance, $ref, $returnUrl, $callbacks['toyyibpay']),
        };

        if ($url) {
            $invoice->forceFill([
                'pay_now_provider'  => $provider,
                'pay_now_reference' => $ref,
            ])->save();
        }

        return $url;
    }

    private function toyyibpayUrl(Invoice $invoice, Tenant $tenant, float $balance, string $ref, string $returnUrl, string $callbackUrl): ?string
    {
        $secret = decrypt($tenant->toyyibpay_secret_key);
        $originalSecret = config('services.toyyibpay.secret_key');
        $originalCat = config('services.toyyibpay.category_code');
        config([
            'services.toyyibpay.secret_key'    => $secret,
            'services.toyyibpay.category_code' => $tenant->toyyibpay_category_code,
        ]);

        try {
            $url = (new ToyyibpayService())->createBill([
                'billName'                => 'Invoice '.$invoice->invoice_number,
                'billDescription'         => 'Payment for '.$invoice->invoice_number,
                'billAmount'              => $balance,
                'billReturnUrl'           => $returnUrl,
                'billCallbackUrl'         => $callbackUrl,
                'billExternalReferenceNo' => $ref,
                'billTo'                  => $invoice->customer?->name ?? 'Customer',
                'billEmail'               => $invoice->customer?->email ?? ($tenant->email ?? 'billing@example.com'),
                'billPhone'               => $invoice->customer?->phone ?? '0000000000',
            ]);
        } finally {
            config([
                'services.toyyibpay.secret_key'    => $originalSecret,
                'services.toyyibpay.category_code' => $originalCat,
            ]);
        }

        if ($url && preg_match('#/([A-Za-z0-9]+)$#', $url, $m)) {
            $invoice->forceFill(['toyyibpay_bill_code' => $m[1]])->save();
        }

        return $url;
    }

    private function billplzUrl(Invoice $invoice, Tenant $tenant, float $balance, string $ref, string $returnUrl, string $callbackUrl): ?string
    {
        $service = BillplzService::forTenant($tenant);
        if (! $service) {
            return null;
        }

        return $service->createBill([
            'description'   => 'Invoice '.$invoice->invoice_number,
            'email'         => $invoice->customer?->email ?? ($tenant->email ?? 'billing@example.com'),
            'name'          => $invoice->customer?->name ?? 'Customer',
            'amount'        => $balance,
            'callback_url'  => $callbackUrl,
            'redirect_url'  => $returnUrl,
            'reference'     => $ref,
        ]);
    }

    private function commercepayUrl(Invoice $invoice, Tenant $tenant, float $balance, string $ref, string $returnUrl, string $callbackUrl): ?string
    {
        $service = CommercePayService::forTenant($tenant);
        if (! $service) {
            return null;
        }

        return $service->requestPaymentUrl([
            'amount'      => $balance,
            'reference'   => $ref,
            'description' => 'Invoice '.$invoice->invoice_number,
            'return_url'  => $returnUrl,
            'callback_url'=> $callbackUrl,
            'ip'          => request()->ip() ?: '127.0.0.1',
            'user_agent'  => substr((string) request()->userAgent(), 0, 200) ?: 'BukuCloud',
            'name'        => $invoice->customer?->name ?? 'Customer',
            'email'       => $invoice->customer?->email ?? ($tenant->email ?? 'billing@example.com'),
            'phone'       => $invoice->customer?->phone ?? '0000000000',
        ]);
    }

    /**
     * @return array{0: string, 1: array{toyyibpay: string, billplz: string, commercepay: string}}
     */
    private function publicPayUrls(Invoice $invoice, Tenant $tenant): array
    {
        $public = rtrim((string) (config('app.public_url') ?: config('app.url')), '/');
        $previous = config('app.url');
        URL::forceRootUrl($public);
        URL::forceScheme(str_starts_with($public, 'https://') ? 'https' : 'http');

        try {
            $returnUrl = URL::temporarySignedRoute(
                'public.invoices.pay.return',
                now()->addDays(7),
                ['uuid' => $invoice->uuid, 'tenant_id' => $tenant->id]
            );
        } finally {
            URL::forceRootUrl($previous);
            URL::forceScheme(parse_url((string) $previous, PHP_URL_SCHEME) ?: 'http');
        }

        return [$returnUrl, [
            'toyyibpay'    => $public.'/pay/toyyibpay/callback',
            'billplz'      => $public.'/pay/billplz/callback',
            'commercepay'  => $public.'/pay/commercepay/callback',
        ]];
    }
}
