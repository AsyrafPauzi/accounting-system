<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ConsolidatedEInvoice;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\MyInvoisSubmission;
use App\Models\Supplier;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MyInvoisService
{
    /** @var array{0: string, 1: string}|null */
    private ?array $supplierIdOverride = null;

    private ?string $addressStateOverride = null;

    /**
     * @return list<string>
     */
    public static function companyGaps(?object $tenant): array
    {
        if (! $tenant) {
            return ['Company profile is missing.'];
        }
        $gaps = [];
        if (! filled($tenant->tin ?? null)) {
            $gaps[] = 'Company TIN';
        }
        if (! filled($tenant->brn ?? null)) {
            $gaps[] = 'Company BRN';
        }
        if (! filled($tenant->legal_name ?? $tenant->display_name ?? null)) {
            $gaps[] = 'Company legal name';
        }
        $country = strtolower((string) ($tenant->country ?? ''));
        if ($country !== '' && ! str_contains($country, 'malaysia') && $country !== 'my') {
            $gaps[] = 'MyInvois is only available for Malaysian company profiles';
        }
        if (! filled($tenant->msic_code ?? null)) {
            $gaps[] = 'Company MSIC code';
        }
        if (! filled($tenant->myinvois_client_id ?? null) || ! filled($tenant->myinvois_client_secret ?? null)) {
            $gaps[] = 'MyInvois client ID and secret';
        }

        return $gaps;
    }

    /**
     * @return list<string>
     */
    public static function customerGaps(?Customer $customer): array
    {
        if (! $customer) {
            return ['Customer is missing.'];
        }
        $gaps = [];
        if (! filled($customer->name)) {
            $gaps[] = 'Customer name';
        }
        if (! filled($customer->tin)) {
            $gaps[] = 'Customer TIN';
        }
        $tin = strtoupper((string) $customer->tin);
        if ($tin !== 'EI00000000010' && ! filled($customer->brn)) {
            $gaps[] = 'Customer ID number (BRN / NRIC / passport)';
        }
        if (! filled($customer->billing_street)) {
            $gaps[] = 'Billing street';
        }
        if (! filled($customer->billing_city)) {
            $gaps[] = 'Billing city';
        }
        if (! filled($customer->billing_zip)) {
            $gaps[] = 'Billing postcode';
        }
        if (! filled($customer->billing_state)) {
            $gaps[] = 'Billing state';
        }
        if (! filled($customer->phone)) {
            $gaps[] = 'Customer phone';
        }

        return $gaps;
    }

    /**
     * @return list<string>
     */
    public function readiness(Invoice|CreditNote|DebitNote $doc): array
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $gaps = self::companyGaps($tenant);
        $customer = $doc->customer ?? ($doc->customer_id ? Customer::find($doc->customer_id) : null);
        $gaps = array_merge($gaps, self::customerGaps($customer));
        if ($doc instanceof Invoice && $doc->status === 'draft') {
            $gaps[] = 'Post the invoice before submitting to MyInvois';
        }
        if (in_array($doc->status ?? '', ['void', 'draft'], true) && ! $doc instanceof Invoice) {
            $gaps[] = 'Document must be posted';
        }

        return $gaps;
    }

    public function submit(Invoice|CreditNote|DebitNote $doc): void
    {
        $gaps = $this->readiness($doc);
        if ($gaps !== []) {
            throw new \LogicException('MyInvois is not ready: '.implode('; ', $gaps));
        }
        if (filled($doc->lhdn_uuid) && in_array($doc->lhdn_status, ['submitted', 'valid'], true)) {
            throw new \LogicException('This document is already submitted to MyInvois.');
        }

        if ($this->isLive()) {
            $this->supplierIdOverride = $this->resolveSupplierId(tenant());
        }

        try {
            $payload = $this->buildDocument($doc);
            [$json, $hash] = $this->encodeForSubmit($payload);
            $codeNumber = $this->codeNumber($doc);

            $result = $this->submitRaw($codeNumber, $json, $hash, $this->documentTypeFor($doc), (int) $doc->id);
            $this->applyResult($doc, $result);
        } finally {
            $this->supplierIdOverride = null;
        }
    }

    public function refreshStatus(Invoice|CreditNote|DebitNote|Bill $doc): void
    {
        if (! filled($doc->lhdn_uuid)) {
            throw new \LogicException('This document has not been submitted to MyInvois.');
        }

        if (! $this->isLive()) {
            if (($doc->lhdn_status ?? '') === 'submitted') {
                $doc->forceFill(['lhdn_status' => 'valid'])->save();
            }

            return;
        }

        $token = $this->token();
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->baseUrl().'/api/v1.0/documents/'.$doc->lhdn_uuid.'/details');
        if (! $response->successful()) {
            throw new \LogicException('Could not refresh MyInvois status: '.$response->body());
        }

        $raw = strtolower((string) ($response->json('status') ?? $response->json('overallStatus') ?? 'submitted'));
        $status = $this->mapLhdnStatus($raw);
        $longId = $response->json('longId') ?: $doc->lhdn_long_id;
        $qr = filled($longId)
            ? rtrim($this->qrBase(), '/').'/'.$doc->lhdn_uuid.'/share/'.$longId
            : $doc->lhdn_qr_url;
        $doc->forceFill([
            'lhdn_status'        => $status,
            'lhdn_long_id'       => $longId,
            'lhdn_qr_url'        => $qr,
            'lhdn_reject_reason' => $status === 'rejected' ? $this->formatValidationResults($response->json('validationResults')) : null,
        ])->save();
    }

    public function cancel(Invoice|CreditNote|DebitNote|ConsolidatedEInvoice|Bill $doc, string $reason): void
    {
        if (! filled($doc->lhdn_uuid)) {
            throw new \LogicException('This document has not been submitted to MyInvois.');
        }
        $submitted = $doc->lhdn_submitted_at;
        if ($submitted && now()->diffInHours($submitted) > 72) {
            throw new \LogicException('MyInvois cancellation is only allowed within 72 hours of submission.');
        }

        if ($this->isLive()) {
            $token = $this->token();
            $response = Http::withToken($token)
                ->put($this->baseUrl().'/api/v1.0/documents/url/'.$doc->lhdn_uuid.'/state', [
                    'status' => 'cancelled',
                    'reason' => $reason,
                ]);
            if (! $response->successful()) {
                throw new \LogicException('LHDN rejected the cancellation: '.$response->body());
            }
        }

        $doc->forceFill([
            'lhdn_status'       => 'cancelled',
            'lhdn_cancelled_at' => now(),
            'lhdn_reject_reason'=> $reason,
        ])->save();
    }

    /**
     * @param  list<Invoice>  $invoices
     */
    public function consolidate(array $invoices, string $from, string $to): ConsolidatedEInvoice
    {
        $invoices = array_values(array_filter($invoices));
        if ($invoices === []) {
            throw new \LogicException('Pick at least one posted invoice to consolidate.');
        }

        $batch = ConsolidatedEInvoice::create([
            'document_number' => DocumentNumber::next('consolidated_e_invoices', 'document_number', 'CEI'),
            'period_from'     => $from,
            'period_to'       => $to,
            'total_amount'    => collect($invoices)->sum(fn (Invoice $i) => (float) $i->total_amount),
            'status'          => 'posted',
            'lhdn_status'     => 'pending',
        ]);

        $batch->invoices()->sync(collect($invoices)->pluck('id')->all());

        $this->addressStateOverride = '17';
        try {
            if ($this->isLive()) {
                $this->supplierIdOverride = $this->resolveSupplierId(tenant());
            }
            $payload = $this->buildConsolidatedDocument($batch, $invoices);
            [$json, $hash] = $this->encodeForSubmit($payload);
            $result = $this->submitRaw($batch->document_number, $json, $hash, 'consolidated', (int) $batch->id);
            $this->applyResult($batch, $result);
        } finally {
            $this->supplierIdOverride = null;
            $this->addressStateOverride = null;
        }

        foreach ($invoices as $invoice) {
            $invoice->forceFill([
                'is_consolidated'            => true,
                'consolidated_e_invoice_id'  => $batch->id,
            ])->save();
        }

        return $batch->fresh();
    }

    /**
     * @return list<string>
     */
    public static function supplierGaps(?Supplier $supplier): array
    {
        if (! $supplier) {
            return ['Supplier is missing.'];
        }
        $gaps = [];
        if (! filled($supplier->name)) {
            $gaps[] = 'Supplier name';
        }
        if (! filled($supplier->tin)) {
            $gaps[] = 'Supplier TIN';
        }
        $tin = strtoupper((string) $supplier->tin);
        if ($tin !== 'EI00000000010' && ! filled($supplier->brn)) {
            $gaps[] = 'Supplier ID number (BRN / NRIC / passport)';
        }
        if (! filled($supplier->billing_street)) {
            $gaps[] = 'Supplier street';
        }
        if (! filled($supplier->billing_city)) {
            $gaps[] = 'Supplier city';
        }
        if (! filled($supplier->billing_zip)) {
            $gaps[] = 'Supplier postcode';
        }
        if (! filled($supplier->billing_state)) {
            $gaps[] = 'Supplier state';
        }
        if (! filled($supplier->phone)) {
            $gaps[] = 'Supplier phone';
        }

        return $gaps;
    }

    /**
     * @return list<string>
     */
    public function selfBilledReadiness(Bill $bill): array
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $gaps = self::companyGaps($tenant);
        $supplier = $bill->relationLoaded('supplier') ? $bill->supplier : $bill->supplier()->first();
        $gaps = array_merge($gaps, self::supplierGaps($supplier));
        if (in_array($bill->status, ['draft', 'void'], true)) {
            $gaps[] = 'Post the bill before submitting a self-billed e-invoice';
        }

        return $gaps;
    }

    public function submitSelfBilled(Bill $bill): void
    {
        $gaps = $this->selfBilledReadiness($bill);
        if ($gaps !== []) {
            throw new \LogicException('MyInvois is not ready: '.implode('; ', $gaps));
        }
        if (filled($bill->lhdn_uuid) && in_array($bill->lhdn_status, ['submitted', 'valid'], true)) {
            throw new \LogicException('This bill is already submitted as a self-billed e-invoice.');
        }

        $payload = $this->buildSelfBilledDocument($bill);
        [$json, $hash] = $this->encodeForSubmit($payload);
        $result = $this->submitRaw($bill->bill_number, $json, $hash, 'bill', (int) $bill->id);
        $this->applyResult($bill, $result);
    }

    /**
     * Self-billed invoice (type 12): buyer (tenant) issues on behalf of the supplier.
     * AccountingSupplierParty = vendor; AccountingCustomerParty = tenant.
     *
     * @return array<string, mixed>
     */
    public function buildSelfBilledDocument(Bill $bill, ?object $tenant = null): array
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        $supplier = $bill->relationLoaded('supplier') ? $bill->supplier : $bill->supplier()->first();
        $items = $bill->relationLoaded('items') ? $bill->items : $bill->items()->get();
        $currency = strtoupper((string) ($bill->currency ?? 'MYR')) ?: 'MYR';

        $issuedAt = optional($bill->bill_date)->copy() ?? now();
        if ($bill->created_at instanceof \DateTimeInterface) {
            $issuedAt = $issuedAt->setTimeFrom(\Illuminate\Support\Carbon::parse($bill->created_at));
        }
        $issuedAt = $issuedAt->utc();
        if ($issuedAt->lt(now()->utc()->subHours(72)) || $issuedAt->gt(now()->utc()->addHour())) {
            $issuedAt = now()->utc();
        }

        $lineRows = [];
        $taxable = 0.0;
        $taxTotal = 0.0;
        $taxBuckets = [];
        $remainingTax = round((float) ($bill->tax_amount ?? 0), 2);
        foreach ($items as $index => $item) {
            $qty = round((float) $item->quantity, 3);
            $unit = round((float) ($item->unit_amount ?? $item->unit_price ?? 0), 2);
            $lineNet = round((float) ($item->amount ?? ($qty * $unit)), 2);
            $isLast = $index === (count($items) - 1);
            $lineTax = $isLast ? $remainingTax : 0.0;
            if ($isLast) {
                $remainingTax = 0.0;
            }
            $rate = $lineNet > 0 && $lineTax > 0 ? round($lineTax / $lineNet * 100, 2) : 0.0;
            $taxCode = $this->taxTypeCode($rate);
            $class = '022';

            $taxable += $lineNet;
            $taxTotal += $lineTax;
            $key = $taxCode.'|'.$rate;
            if (! isset($taxBuckets[$key])) {
                $taxBuckets[$key] = ['code' => $taxCode, 'rate' => $rate, 'taxable' => 0.0, 'tax' => 0.0];
            }
            $taxBuckets[$key]['taxable'] += $lineNet;
            $taxBuckets[$key]['tax'] += $lineTax;

            $lineRows[] = [
                'ID' => $this->ubl((string) ($index + 1)),
                'InvoicedQuantity' => $this->ubl($qty ?: 1, ['unitCode' => 'C62']),
                'LineExtensionAmount' => $this->ublAmount($lineNet, $currency),
                'TaxTotal' => [[
                    'TaxAmount' => $this->ublAmount($lineTax, $currency),
                    'TaxSubtotal' => [$this->taxSubtotal($lineNet, $lineTax, $rate, $taxCode, $currency)],
                ]],
                'Item' => [[
                    'CommodityClassification' => [[
                        'ItemClassificationCode' => $this->ubl($class, ['listID' => 'CLASS']),
                    ]],
                    'Description' => $this->ubl($this->clip((string) ($item->description ?: 'Item'), 300)),
                ]],
                'Price' => [[
                    'PriceAmount' => $this->ublAmount($unit ?: $lineNet, $currency),
                ]],
                'ItemPriceExtension' => [[
                    'Amount' => $this->ublAmount($lineNet, $currency),
                ]],
            ];
        }

        if ($lineRows === []) {
            $amount = round((float) ($bill->total_amount ?? 0), 2);
            $taxable = $amount;
            $taxBuckets['06|0'] = ['code' => '06', 'rate' => 0.0, 'taxable' => $amount, 'tax' => 0.0];
            $lineRows[] = [
                'ID' => $this->ubl('1'),
                'InvoicedQuantity' => $this->ubl(1, ['unitCode' => 'C62']),
                'LineExtensionAmount' => $this->ublAmount($amount, $currency),
                'TaxTotal' => [[
                    'TaxAmount' => $this->ublAmount(0, $currency),
                    'TaxSubtotal' => [$this->taxSubtotal($amount, 0, 0, '06', $currency)],
                ]],
                'Item' => [[
                    'CommodityClassification' => [[
                        'ItemClassificationCode' => $this->ubl('022', ['listID' => 'CLASS']),
                    ]],
                    'Description' => $this->ubl($this->clip((string) $bill->bill_number, 300)),
                ]],
                'Price' => [[
                    'PriceAmount' => $this->ublAmount($amount, $currency),
                ]],
                'ItemPriceExtension' => [[
                    'Amount' => $this->ublAmount($amount, $currency),
                ]],
            ];
        }

        $taxable = round($taxable, 2);
        $payable = round((float) ($bill->total_amount ?? ($taxable + $taxTotal)), 2);

        $invoice = [
            'ID' => $this->ubl((string) $bill->bill_number),
            'IssueDate' => $this->ubl($issuedAt->toDateString()),
            'IssueTime' => $this->ubl($issuedAt->format('H:i:s').'Z'),
            'InvoiceTypeCode' => $this->ubl('12', ['listVersionID' => '1.0']),
            'DocumentCurrencyCode' => $this->ubl($currency),
            'TaxCurrencyCode' => $this->ubl($currency),
            'AccountingSupplierParty' => [$this->sellerPartyFromSupplier($supplier)],
            'AccountingCustomerParty' => [$this->buyerPartyFromTenant($tenant)],
            'TaxTotal' => [[
                'TaxAmount' => $this->ublAmount($taxTotal, $currency),
                'TaxSubtotal' => array_values(array_map(
                    fn (array $bucket) => $this->taxSubtotal(
                        round($bucket['taxable'], 2),
                        round($bucket['tax'], 2),
                        $bucket['rate'],
                        $bucket['code'],
                        $currency
                    ),
                    $taxBuckets ?: [['code' => '06', 'rate' => 0.0, 'taxable' => $taxable, 'tax' => 0.0]]
                )),
            ]],
            'LegalMonetaryTotal' => [[
                'LineExtensionAmount' => $this->ublAmount($taxable, $currency),
                'TaxExclusiveAmount' => $this->ublAmount($taxable, $currency),
                'TaxInclusiveAmount' => $this->ublAmount($payable, $currency),
                'PayableRoundingAmount' => $this->ublAmount(0, $currency),
                'PayableAmount' => $this->ublAmount($payable, $currency),
            ]],
            'InvoiceLine' => $lineRows,
        ];

        return [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [$invoice],
        ];
    }

    /**
     * LHDN MyInvois UBL 2.1 JSON (Invoice v1.1).
     *
     * @return array<string, mixed>
     */
    public function buildDocument(Invoice|CreditNote|DebitNote $doc, ?object $tenant = null): array
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        $customer = $doc->relationLoaded('customer') ? $doc->customer : $doc->customer()->first();
        $items = $doc->relationLoaded('items') ? $doc->items : $doc->items()->get();
        $currency = strtoupper((string) ($doc->currency ?? 'MYR')) ?: 'MYR';
        $type = match (true) {
            $doc instanceof CreditNote => '02',
            $doc instanceof DebitNote => '03',
            default => '01',
        };

        $issuedAt = optional($doc->issue_date)->copy() ?? now();
        if ($doc->created_at instanceof \DateTimeInterface) {
            $issuedAt = $issuedAt->setTimeFrom(\Illuminate\Support\Carbon::parse($doc->created_at));
        }
        $issuedAt = $issuedAt->utc();
        if ($issuedAt->lt(now()->utc()->subHours(72)) || $issuedAt->gt(now()->utc()->addHour())) {
            $issuedAt = now()->utc();
        }
        $issueDate = $issuedAt->toDateString();

        $lineRows = [];
        $taxable = 0.0;
        $taxTotal = 0.0;
        $taxBuckets = [];
        foreach ($items as $index => $item) {
            $qty = round((float) $item->quantity, 3);
            $unit = round((float) $item->unit_price, 2);
            $discount = round((float) ($item->discount_amount ?? 0), 2);
            $lineNet = round(((float) ($item->amount ?? (($qty * $unit) - $discount))), 2);
            $rate = round((float) ($item->tax_rate ?? 0), 2);
            $lineTax = round($lineNet * $rate / 100, 2);
            $taxCode = $this->taxTypeCode($rate);
            $class = preg_replace('/\D/', '', (string) ($item->item_classification ?: '022')) ?: '022';
            $class = str_pad(substr($class, 0, 3), 3, '0', STR_PAD_LEFT);
            $buyerTin = strtoupper((string) ($customer?->tin ?? ''));
            $buyerBrn = trim((string) ($customer?->brn ?? ''));
            if ($buyerTin === 'EI00000000010' && ! preg_match('/^\d{12}$/', $buyerBrn)) {
                $class = '004';
            }

            $taxable += $lineNet;
            $taxTotal += $lineTax;
            $key = $taxCode.'|'.$rate;
            if (! isset($taxBuckets[$key])) {
                $taxBuckets[$key] = ['code' => $taxCode, 'rate' => $rate, 'taxable' => 0.0, 'tax' => 0.0];
            }
            $taxBuckets[$key]['taxable'] += $lineNet;
            $taxBuckets[$key]['tax'] += $lineTax;

            $line = [
                'ID' => $this->ubl((string) ($index + 1)),
                'InvoicedQuantity' => $this->ubl($qty, ['unitCode' => 'C62']),
                'LineExtensionAmount' => $this->ublAmount($lineNet, $currency),
                'TaxTotal' => [[
                    'TaxAmount' => $this->ublAmount($lineTax, $currency),
                    'TaxSubtotal' => [$this->taxSubtotal($lineNet, $lineTax, $rate, $taxCode, $currency)],
                ]],
                'Item' => [[
                    'CommodityClassification' => [[
                        'ItemClassificationCode' => $this->ubl($class, ['listID' => 'CLASS']),
                    ]],
                    'Description' => $this->ubl($this->clip((string) ($item->description ?: 'Item'), 300)),
                ]],
                'Price' => [[
                    'PriceAmount' => $this->ublAmount($unit, $currency),
                ]],
                'ItemPriceExtension' => [[
                    'Amount' => $this->ublAmount($lineNet + $discount, $currency),
                ]],
            ];
            $lineRows[] = $line;
        }

        $taxable = round($taxable, 2);
        $taxTotal = round((float) ($doc->tax_amount ?? $taxTotal), 2);
        $payable = round((float) ($doc->total_amount ?? ($taxable + $taxTotal)), 2);
        $rounding = round((float) ($doc->rounding_adjustment ?? 0), 2);

        $invoice = [
            'ID' => $this->ubl($this->codeNumber($doc)),
            'IssueDate' => $this->ubl($issueDate),
            'IssueTime' => $this->ubl($issuedAt->format('H:i:s').'Z'),
            'InvoiceTypeCode' => $this->ubl($type, ['listVersionID' => '1.0']),
            'DocumentCurrencyCode' => $this->ubl($currency),
            'TaxCurrencyCode' => $this->ubl($currency),
        ];

        if ($doc instanceof CreditNote || $doc instanceof DebitNote) {
            $original = $doc->relationLoaded('invoice') ? $doc->invoice : $doc->invoice()->first();
            $ref = [
                'ID' => $this->ubl($original?->invoice_number ?: $this->codeNumber($doc)),
            ];
            if (filled($original?->lhdn_uuid)) {
                $ref['UUID'] = $this->ubl($original->lhdn_uuid);
            }
            $invoice['BillingReference'] = [[
                'InvoiceDocumentReference' => [$ref],
            ]];
        }

        $invoice['AccountingSupplierParty'] = [$this->supplierParty($tenant)];
        $invoice['AccountingCustomerParty'] = [$this->customerParty($customer)];
        $invoice['TaxTotal'] = [[
            'TaxAmount' => $this->ublAmount($taxTotal, $currency),
            'TaxSubtotal' => array_values(array_map(
                fn (array $bucket) => $this->taxSubtotal(
                    round($bucket['taxable'], 2),
                    round($bucket['tax'], 2),
                    $bucket['rate'],
                    $bucket['code'],
                    $currency
                ),
                $taxBuckets ?: [['code' => '06', 'rate' => 0.0, 'taxable' => $taxable, 'tax' => 0.0]]
            )),
        ]];
        $invoice['LegalMonetaryTotal'] = [[
            'LineExtensionAmount' => $this->ublAmount($taxable, $currency),
            'TaxExclusiveAmount' => $this->ublAmount($taxable, $currency),
            'TaxInclusiveAmount' => $this->ublAmount($payable, $currency),
            'PayableRoundingAmount' => $this->ublAmount($rounding, $currency),
            'PayableAmount' => $this->ublAmount($payable, $currency),
        ]];
        $invoice['InvoiceLine'] = $lineRows;

        return [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [$invoice],
        ];
    }

    /**
     * Consolidated e-invoice (type 11) — general public buyer, CLASS 004, state 17.
     *
     * @param  list<Invoice>  $invoices
     * @return array<string, mixed>
     */
    public function buildConsolidatedDocument(ConsolidatedEInvoice $batch, array $invoices, ?object $tenant = null): array
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        $issuedAt = now()->utc();
        $currency = 'MYR';
        $lineRows = [];
        $taxable = 0.0;
        foreach (array_values($invoices) as $index => $invoice) {
            $amount = round((float) $invoice->total_amount, 2);
            $taxable += $amount;
            $lineRows[] = [
                'ID' => $this->ubl((string) ($index + 1)),
                'InvoicedQuantity' => $this->ubl(1, ['unitCode' => 'C62']),
                'LineExtensionAmount' => $this->ublAmount($amount, $currency),
                'TaxTotal' => [[
                    'TaxAmount' => $this->ublAmount(0, $currency),
                    'TaxSubtotal' => [$this->taxSubtotal($amount, 0, 0, '06', $currency)],
                ]],
                'Item' => [[
                    'CommodityClassification' => [[
                        'ItemClassificationCode' => $this->ubl('004', ['listID' => 'CLASS']),
                    ]],
                    'Description' => $this->ubl($this->clip((string) $invoice->invoice_number, 300)),
                ]],
                'Price' => [[
                    'PriceAmount' => $this->ublAmount($amount, $currency),
                ]],
                'ItemPriceExtension' => [[
                    'Amount' => $this->ublAmount($amount, $currency),
                ]],
            ];
        }
        $taxable = round($taxable, 2);
        $buyer = new Customer([
            'name' => 'General Public',
            'tin' => 'EI00000000010',
            'brn' => 'NA',
            'identification_type' => 'NRIC',
            'email' => 'na@hasil.gov.my',
            'phone' => '+60123456789',
            'billing_street' => 'NA',
            'billing_city' => 'NA',
            'billing_zip' => '00000',
            'billing_state' => '17',
        ]);

        $invoice = [
            'ID' => $this->ubl($batch->document_number),
            'IssueDate' => $this->ubl($issuedAt->toDateString()),
            'IssueTime' => $this->ubl($issuedAt->format('H:i:s').'Z'),
            'InvoiceTypeCode' => $this->ubl('11', ['listVersionID' => '1.0']),
            'DocumentCurrencyCode' => $this->ubl($currency),
            'TaxCurrencyCode' => $this->ubl($currency),
            'InvoicePeriod' => [[
                'StartDate' => $this->ubl((string) $batch->period_from),
                'EndDate' => $this->ubl((string) $batch->period_to),
                'Description' => $this->ubl('Monthly'),
            ]],
            'AccountingSupplierParty' => [$this->supplierParty($tenant)],
            'AccountingCustomerParty' => [$this->customerParty($buyer)],
            'TaxTotal' => [[
                'TaxAmount' => $this->ublAmount(0, $currency),
                'TaxSubtotal' => [$this->taxSubtotal($taxable, 0, 0, '06', $currency)],
            ]],
            'LegalMonetaryTotal' => [[
                'LineExtensionAmount' => $this->ublAmount($taxable, $currency),
                'TaxExclusiveAmount' => $this->ublAmount($taxable, $currency),
                'TaxInclusiveAmount' => $this->ublAmount($taxable, $currency),
                'PayableRoundingAmount' => $this->ublAmount(0, $currency),
                'PayableAmount' => $this->ublAmount($taxable, $currency),
            ]],
            'InvoiceLine' => $lineRows,
        ];

        return [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [$invoice],
        ];
    }

    /**
     * @return array{uuid: string, longId: string, status: string, error?: string, submission_id?: int}
     */
    private function submitRaw(string $codeNumber, string $json, string $hash, string $documentType, int $documentId): array
    {
        $requestJson = json_decode($json, true);
        if (! is_array($requestJson)) {
            $requestJson = ['raw' => $json];
        }

        if (! $this->isLive()) {
            $uuid = (string) Str::uuid();
            $responseJson = [
                'uuid'   => $uuid,
                'longId' => Str::lower(Str::random(32)),
                'status' => 'submitted',
            ];
            $submission = $this->persistSubmission(
                $documentType,
                $documentId,
                $requestJson,
                $responseJson,
                202,
                $uuid,
                'submitted',
            );

            return [
                'uuid'           => $uuid,
                'longId'         => $responseJson['longId'],
                'status'         => 'submitted',
                'submission_id'  => $submission->id,
            ];
        }

        $token = $this->token();
        $document = base64_encode($json);
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->baseUrl().'/api/v1.0/documentsubmissions', [
                'documents' => [[
                    'format'       => 'JSON',
                    'documentHash' => $hash,
                    'codeNumber'   => $codeNumber,
                    'document'     => $document,
                ]],
            ]);

        if (! $response->successful()) {
            $responseJson = $response->json();
            if (! is_array($responseJson)) {
                $responseJson = ['body' => $response->body()];
            }
            Log::warning('MyInvois submit failed', ['body' => $response->body()]);
            $this->persistSubmission(
                $documentType,
                $documentId,
                $requestJson,
                $responseJson,
                $response->status(),
                null,
                'error',
            );
            throw new \LogicException('LHDN rejected the submission: '.$response->body());
        }

        $body = $response->json();
        if (! is_array($body)) {
            $body = ['body' => (string) $response->body()];
        }
        $accepted = $body['acceptedDocuments'][0] ?? null;
        $rejected = $body['rejectedDocuments'][0] ?? null;
        if ($rejected) {
            $this->persistSubmission(
                $documentType,
                $documentId,
                $requestJson,
                $body,
                $response->status(),
                is_array($rejected) ? ($rejected['uuid'] ?? null) : null,
                'rejected',
            );
            throw new \LogicException($this->formatLhdnError($rejected));
        }
        if (! $accepted) {
            $this->persistSubmission(
                $documentType,
                $documentId,
                $requestJson,
                $body,
                $response->status(),
                null,
                'error',
            );
            throw new \LogicException('LHDN returned no accepted document.');
        }

        $uuid = $accepted['uuid'] ?? (string) Str::uuid();
        $submission = $this->persistSubmission(
            $documentType,
            $documentId,
            $requestJson,
            $body,
            $response->status(),
            $uuid,
            'submitted',
        );

        return [
            'uuid'          => $uuid,
            'longId'        => $accepted['longId'] ?? '',
            'status'        => 'submitted',
            'submission_id' => $submission->id,
        ];
    }

    private function applyResult(object $doc, array $result): void
    {
        $uuid = $result['uuid'];
        $longId = $result['longId'] ?? '';
        $qr = rtrim($this->qrBase(), '/').'/'.$uuid.'/share/'.$longId;

        $doc->forceFill([
            'lhdn_uuid'         => $uuid,
            'lhdn_long_id'      => $longId,
            'lhdn_status'       => $result['status'] ?? 'submitted',
            'lhdn_submitted_at' => now(),
            'lhdn_reject_reason'=> $result['error'] ?? null,
            'lhdn_qr_url'       => $qr,
        ])->save();

        if (! empty($result['submission_id'])) {
            MyInvoisSubmission::query()
                ->whereKey($result['submission_id'])
                ->update(['lhdn_uuid' => $uuid]);
        }
    }

    private function documentTypeFor(object $doc): string
    {
        return match (true) {
            $doc instanceof Invoice => 'invoice',
            $doc instanceof CreditNote => 'credit_note',
            $doc instanceof DebitNote => 'debit_note',
            $doc instanceof Bill => 'bill',
            $doc instanceof ConsolidatedEInvoice => 'consolidated',
            default => class_basename($doc),
        };
    }

    /**
     * @param  array<string, mixed>  $requestJson
     * @param  array<string, mixed>|null  $responseJson
     */
    private function persistSubmission(
        string $documentType,
        int $documentId,
        array $requestJson,
        ?array $responseJson,
        ?int $httpStatus,
        ?string $lhdnUuid,
        string $status,
    ): MyInvoisSubmission {
        return MyInvoisSubmission::create([
            'document_type' => $documentType,
            'document_id'   => $documentId,
            'request_json'  => $requestJson,
            'response_json' => $responseJson,
            'http_status'   => $httpStatus,
            'lhdn_uuid'     => $lhdnUuid,
            'status'        => $status,
            'submitted_at'  => now(),
        ]);
    }

    private function codeNumber(Invoice|CreditNote|DebitNote $doc): string
    {
        return match (true) {
            $doc instanceof CreditNote => $doc->cn_number,
            $doc instanceof DebitNote => $doc->dn_number,
            default => $doc->invoice_number,
        };
    }

    public function encodeDocument(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}
     */
    public function encodeForSubmit(array $payload, ?object $tenant = null): array
    {
        $tenant ??= function_exists('tenant') ? tenant() : null;
        $signer = app(MyInvoisJsonSigner::class);
        if ($signer->canSign($tenant)) {
            $json = $signer->sign($payload, $tenant);
        } else {
            $json = $this->encodeDocument($payload);
        }

        return [$json, hash('sha256', $json)];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierParty(?object $tenant): array
    {
        $msic = preg_replace('/\D/', '', (string) (data_get($tenant, 'msic_code') ?? '00000')) ?: '00000';
        $msic = str_pad(substr($msic, 0, 5), 5, '0', STR_PAD_LEFT);
        $name = data_get($tenant, 'legal_name') ?: (data_get($tenant, 'display_name') ?: config('app.name'));
        $sst = data_get($tenant, 'sst_number') ?? '';
        [$idType, $idValue] = $this->supplierId($tenant);

        return [
            'Party' => [[
                'IndustryClassificationCode' => $this->ubl($msic, ['name' => $this->msicName($msic)]),
                'PartyIdentification' => $this->partyIds(
                    (string) (data_get($tenant, 'tin') ?? ''),
                    $idType,
                    $idValue,
                    $sst
                ),
                'PostalAddress' => [$this->postalAddress(
                    (string) (data_get($tenant, 'street') ?: config('invoice.company.address', 'Lot 1')),
                    (string) (data_get($tenant, 'city') ?: config('invoice.company.city', 'Kuala Lumpur')),
                    (string) (data_get($tenant, 'postcode') ?: config('invoice.company.zip', '50000')),
                    (string) (data_get($tenant, 'state') ?: config('invoice.company.state', 'Kuala Lumpur')),
                )],
                'PartyLegalEntity' => [[
                    'RegistrationName' => $this->ubl($this->clip((string) $name, 300)),
                ]],
                'Contact' => [$this->contact(
                    (string) (data_get($tenant, 'phone') ?: ''),
                    (string) (data_get($tenant, 'email') ?: 'hello@bukucloud.com')
                )],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerParty(?Customer $customer): array
    {
        $tin = (string) ($customer?->tin ?? '');
        $idNumber = (string) ($customer?->brn ?: '');
        $idType = strtoupper((string) ($customer?->identification_type ?? ''));
        if (in_array($idType, ['BRN', 'NRIC', 'PASSPORT', 'ARMY'], true) && $idNumber !== '') {
            [$type, $value] = [$idType, $idNumber];
        } else {
            [$type, $value] = $this->buyerId($tin, $idNumber);
        }

        return [
            'Party' => [[
                'PartyIdentification' => $this->partyIds($tin, $type, $value, (string) ($customer?->sst_number ?? '')),
                'PostalAddress' => [$this->postalAddress(
                    (string) ($customer?->billing_street ?: 'Lot 1'),
                    (string) ($customer?->billing_city ?: 'Kuala Lumpur'),
                    (string) ($customer?->billing_zip ?: '50000'),
                    (string) ($customer?->billing_state ?: 'Kuala Lumpur'),
                )],
                'PartyLegalEntity' => [[
                    'RegistrationName' => $this->ubl($this->clip((string) ($customer?->name ?: 'Buyer'), 300)),
                ]],
                'Contact' => [$this->contact(
                    (string) ($customer?->phone ?: ''),
                    (string) ($customer?->email ?: '')
                )],
            ]],
        ];
    }

    /**
     * Vendor as AccountingSupplierParty on a self-billed invoice.
     *
     * @return array<string, mixed>
     */
    private function sellerPartyFromSupplier(?Supplier $supplier): array
    {
        $tin = (string) ($supplier?->tin ?? '');
        $idNumber = (string) ($supplier?->brn ?: '');
        $idType = strtoupper((string) ($supplier?->identification_type ?? ''));
        if (in_array($idType, ['BRN', 'NRIC', 'PASSPORT', 'ARMY'], true) && $idNumber !== '') {
            [$type, $value] = [$idType, $idNumber];
        } else {
            [$type, $value] = $this->buyerId($tin, $idNumber);
        }

        return [
            'Party' => [[
                'IndustryClassificationCode' => $this->ubl('00000', ['name' => $this->msicName('00000')]),
                'PartyIdentification' => $this->partyIds($tin, $type, $value, (string) ($supplier?->sst_number ?? '')),
                'PostalAddress' => [$this->postalAddress(
                    (string) ($supplier?->billing_street ?: 'Lot 1'),
                    (string) ($supplier?->billing_city ?: 'Kuala Lumpur'),
                    (string) ($supplier?->billing_zip ?: '50000'),
                    (string) ($supplier?->billing_state ?: 'Kuala Lumpur'),
                )],
                'PartyLegalEntity' => [[
                    'RegistrationName' => $this->ubl($this->clip((string) ($supplier?->name ?: 'Supplier'), 300)),
                ]],
                'Contact' => [$this->contact(
                    (string) ($supplier?->phone ?: ''),
                    (string) ($supplier?->email ?: '')
                )],
            ]],
        ];
    }

    /**
     * Tenant as AccountingCustomerParty (buyer issuing the self-billed e-invoice).
     *
     * @return array<string, mixed>
     */
    private function buyerPartyFromTenant(?object $tenant): array
    {
        [$type, $value] = $this->supplierId($tenant);

        return [
            'Party' => [[
                'PartyIdentification' => $this->partyIds(
                    (string) (data_get($tenant, 'tin') ?? ''),
                    $type,
                    $value,
                    (string) (data_get($tenant, 'sst_number') ?? '')
                ),
                'PostalAddress' => [$this->postalAddress(
                    (string) (data_get($tenant, 'street') ?: 'Lot 1'),
                    (string) (data_get($tenant, 'city') ?: 'Kuala Lumpur'),
                    (string) (data_get($tenant, 'postcode') ?: '50000'),
                    (string) (data_get($tenant, 'state') ?: 'Kuala Lumpur'),
                )],
                'PartyLegalEntity' => [[
                    'RegistrationName' => $this->ubl($this->clip((string) (data_get($tenant, 'legal_name') ?: data_get($tenant, 'display_name') ?: 'Buyer'), 300)),
                ]],
                'Contact' => [$this->contact(
                    (string) (data_get($tenant, 'phone') ?: ''),
                    (string) (data_get($tenant, 'email') ?: 'hello@bukucloud.com')
                )],
            ]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partyIds(string $tin, string $idType, string $idValue, string $sst): array
    {
        return [
            ['ID' => $this->ubl($tin, ['schemeID' => 'TIN'])],
            ['ID' => $this->ubl($idValue !== '' ? $idValue : 'NA', ['schemeID' => $idType])],
            ['ID' => $this->ubl($sst !== '' ? $sst : 'NA', ['schemeID' => 'SST'])],
            ['ID' => $this->ubl('NA', ['schemeID' => 'TTX'])],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function supplierId(?object $tenant): array
    {
        if ($this->supplierIdOverride) {
            return $this->supplierIdOverride;
        }
        $type = strtoupper((string) (data_get($tenant, 'myinvois_id_type') ?? ''));
        $value = trim((string) (data_get($tenant, 'myinvois_id_value') ?? ''));
        if (in_array($type, ['BRN', 'NRIC', 'PASSPORT', 'ARMY'], true)) {
            if ($value === '') {
                $value = $type === 'BRN' ? (string) (data_get($tenant, 'brn') ?: 'NA') : 'NA';
            }

            return [$type, $value];
        }
        $tin = strtoupper((string) (data_get($tenant, 'tin') ?? ''));
        if (str_starts_with($tin, 'IG')) {
            return ['PASSPORT', 'NA'];
        }

        return ['BRN', (string) (data_get($tenant, 'brn') ?: 'NA')];
    }

    /**
     * Sole-prop (IG) TINs are often stored in MyInvois as NRIC/PASSPORT, not SSM BRN.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveSupplierId(?object $tenant): array
    {
        $explicit = $this->supplierId($tenant);
        $tin = (string) ($tenant?->tin ?? '');
        if ($this->taxpayerExists($tin, $explicit[0], $explicit[1])) {
            return $explicit;
        }

        $brn = (string) ($tenant?->brn ?? '');
        $candidates = array_values(array_filter([
            $brn !== '' ? ['BRN', $brn] : null,
            ['NRIC', 'NA'],
            ['PASSPORT', 'NA'],
            ['ARMY', 'NA'],
        ]));

        foreach ($candidates as [$type, $value]) {
            if ($this->taxpayerExists($tin, $type, $value)) {
                return [$type, $value];
            }
        }

        return $explicit;
    }

    private function taxpayerExists(string $tin, string $idType, string $idValue): bool
    {
        try {
            $response = Http::withToken($this->token())
                ->acceptJson()
                ->get($this->baseUrl().'/api/v1.0/taxpayer/validate/'.rawurlencode($tin), [
                    'idType'  => $idType,
                    'idValue' => $idValue,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buyerId(string $tin, string $brn): array
    {
        $tin = strtoupper($tin);
        $id = trim($brn);

        // General public TIN cannot use identification "NA" on a normal invoice (ERR205).
        if ($tin === 'EI00000000010') {
            if (preg_match('/^\d{12}$/', $id)) {
                return ['NRIC', $id];
            }

            return ['NRIC', 'NA'];
        }
        if ($tin === 'EI00000000020') {
            return ['PASSPORT', $id !== '' && strcasecmp($id, 'NA') !== 0 ? $id : 'A12345678'];
        }
        if ($id !== '' && strcasecmp($id, 'NA') !== 0) {
            return [preg_match('/^\d{12}$/', $id) ? 'NRIC' : 'BRN', $id];
        }
        if (str_starts_with($tin, 'IG')) {
            return ['NRIC', preg_match('/^\d{12}$/', $id) ? $id : '770625015324'];
        }

        return ['BRN', $id !== '' ? $id : 'NA'];
    }

    /**
     * @return array<string, mixed>
     */
    private function postalAddress(string $line, string $city, string $postcode, string $state): array
    {
        $line = $this->clip($line !== '' ? $line : 'NA', 150);
        $city = $this->clip($city !== '' ? $city : 'Kuala Lumpur', 50);
        $postcode = preg_replace('/\D/', '', $postcode) ?: '50000';
        $stateCode = $this->addressStateOverride ?: $this->stateCode($state);

        return [
            'CityName' => $this->ubl($city),
            'PostalZone' => $this->ubl($postcode),
            'CountrySubentityCode' => $this->ubl($stateCode),
            'AddressLine' => [
                ['Line' => $this->ubl($line)],
            ],
            'Country' => [[
                'IdentificationCode' => $this->ubl('MYS', [
                    'listID' => 'ISO3166-1',
                    'listAgencyID' => '6',
                ]),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contact(string $phone, string $email): array
    {
        return [
            'Telephone' => $this->ubl($this->e164($phone)),
            'ElectronicMail' => $this->ubl($email !== '' ? $email : 'hello@bukucloud.com'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxSubtotal(float $taxable, float $tax, float $rate, string $code, string $currency): array
    {
        $category = [
            'ID' => $this->ubl($code),
            'TaxScheme' => [[
                'ID' => $this->ubl('OTH', [
                    'schemeID' => 'UN/ECE 5153',
                    'schemeAgencyID' => '6',
                ]),
            ]],
        ];
        if (in_array($code, ['06', 'E'], true)) {
            $category['TaxExemptionReason'] = $this->ubl($code === 'E' ? 'Exempt' : 'Not applicable');
        }

        return [
            'TaxableAmount' => $this->ublAmount($taxable, $currency),
            'TaxAmount' => $this->ublAmount($tax, $currency),
            'Percent' => $this->ubl($rate),
            'TaxCategory' => [$category],
        ];
    }

    private function taxTypeCode(float $rate): string
    {
        if ($rate <= 0) {
            return '06';
        }
        if (abs($rate - 8.0) < 0.001 || abs($rate - 6.0) < 0.001) {
            return '02';
        }

        return '01';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ubl(mixed $value, array $attrs = []): array
    {
        return [array_merge(['_' => $value], $attrs)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ublAmount(float $amount, string $currency): array
    {
        return [['_' => round($amount, 2), 'currencyID' => $currency]];
    }

    /**
     * @param  array<string, mixed>  $rejected
     */
    private function formatLhdnError(array $rejected): string
    {
        $error = $rejected['error'] ?? [];
        $parts = [is_array($error) ? ($error['message'] ?? 'LHDN rejected the document.') : (string) $error];
        $details = is_array($error) ? ($error['details'] ?? $error['innerError'] ?? []) : [];
        foreach ((array) $details as $detail) {
            if (is_array($detail)) {
                $parts[] = trim(($detail['code'] ?? '').' '.($detail['message'] ?? ''));
            } elseif (is_string($detail)) {
                $parts[] = $detail;
            }
        }

        return trim(implode(' — ', array_filter($parts)));
    }

    private function mapLhdnStatus(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === 'invalid' || str_contains($raw, 'invalid') || str_contains($raw, 'reject')) {
            return 'rejected';
        }
        if (str_contains($raw, 'cancel')) {
            return 'cancelled';
        }
        if ($raw === 'valid') {
            return 'valid';
        }

        return 'submitted';
    }

    private function formatValidationResults(mixed $results): ?string
    {
        if (! is_array($results)) {
            return null;
        }
        $messages = [];
        foreach ($results['validationSteps'] ?? [] as $step) {
            if (($step['status'] ?? '') !== 'Invalid') {
                continue;
            }
            foreach ($step['error']['innerError'] ?? [] as $inner) {
                $messages[] = trim(($inner['errorCode'] ?? '').' '.($inner['error'] ?? ''));
            }
            if ($messages === []) {
                $messages[] = $step['error']['error'] ?? $step['name'] ?? 'Invalid';
            }
        }

        return $messages === [] ? null : implode('; ', array_filter($messages));
    }

    private function msicName(string $code): string
    {
        return match ($code) {
            '62010' => 'Computer programming activities',
            '62011' => 'Computer consultancy activities',
            '63110' => 'Data processing, hosting and related activities',
            '70200' => 'Management consultancy activities',
            '00000' => 'Not Applicable',
            default => 'Business activities',
        };
    }

    private function stateCode(string $state): string
    {
        $raw = trim($state);
        if (preg_match('/^\d{2}$/', $raw)) {
            return $raw;
        }
        $key = strtolower($raw);

        return match (true) {
            str_contains($key, 'johor') => '01',
            str_contains($key, 'kedah') => '02',
            str_contains($key, 'kelantan') => '03',
            str_contains($key, 'melaka'), str_contains($key, 'malacca') => '04',
            str_contains($key, 'sembilan') => '05',
            str_contains($key, 'pahang') => '06',
            str_contains($key, 'penang'), str_contains($key, 'pinang') => '07',
            str_contains($key, 'perak') => '08',
            str_contains($key, 'perlis') => '09',
            str_contains($key, 'selangor') => '10',
            str_contains($key, 'terengganu') => '11',
            str_contains($key, 'sabah') => '12',
            str_contains($key, 'sarawak') => '13',
            str_contains($key, 'labuan') => '15',
            str_contains($key, 'putrajaya') => '16',
            default => '14',
        };
    }

    private function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?: '60123456789';
        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '60')) {
            $digits = '60'.$digits;
        }

        return '+'.$digits;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);

        return mb_substr($value !== '' ? $value : 'NA', 0, $max);
    }

    /**
     * Hit LHDN /connect/token for the tenant's chosen environment.
     *
     * @return array{ok: bool, environment: string, message: string}
     */
    public function probeAuth(): array
    {
        $environment = $this->environment();
        try {
            $token = $this->token();
            $ok = is_string($token) && $token !== '';

            return [
                'ok'          => $ok,
                'environment' => $environment,
                'message'     => $ok
                    ? 'MyInvois '.$environment.' accepted the client ID and secret.'
                    : 'MyInvois returned an empty token.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok'          => false,
                'environment' => $environment,
                'message'     => $e->getMessage(),
            ];
        }
    }

    public function environment(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $env = strtolower((string) ($tenant?->myinvois_environment ?: config('myinvois.environment', 'preprod')));

        return $env === 'production' ? 'production' : 'preprod';
    }

    private function isLive(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant || ! filled($tenant->myinvois_client_id) || ! filled($tenant->myinvois_client_secret)) {
            return false;
        }

        return true;
    }

    private function baseUrl(): string
    {
        return $this->environment() === 'production'
            ? (string) config('myinvois.prod_url')
            : (string) config('myinvois.preprod_url');
    }

    private function qrBase(): string
    {
        return $this->environment() === 'production'
            ? (string) config('myinvois.qr_base')
            : 'https://preprod.myinvois.hasil.gov.my';
    }

    private function token(): string
    {
        $tenant = tenant();
        $clientId = $tenant?->myinvois_client_id;
        $secret = $tenant?->myinvois_client_secret
            ? decrypt($tenant->myinvois_client_secret)
            : null;
        if (! $clientId || ! $secret) {
            throw new \LogicException('Add MyInvois client ID and secret under Settings → Integrations.');
        }

        $response = Http::asForm()->post($this->baseUrl().'/connect/token', [
            'client_id'     => $clientId,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'InvoicingAPI',
        ]);
        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new \LogicException(
                'Could not authenticate with MyInvois ('.$this->environment().'): '
                .($response->json('error') ?: 'HTTP '.$response->status())
            );
        }

        return $response->json('access_token');
    }
}
