<?php

namespace App\Services\Ocr;

use Illuminate\Contracts\Support\Arrayable;

/**
 * DTO for OCR extraction output. All fields are optional except status — providers
 * fill in what they can read. The `toLegacyArray()` shape is the contract that
 * BillController and BillService rely on; do not change it without updating callers.
 */
class OcrResult implements Arrayable
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $status,
        public string $provider,
        public ?string $vendorName = null,
        public ?string $billDate = null, // Y-m-d
        public ?float $subtotal = null,
        public ?float $taxAmount = null,
        public ?float $totalAmount = null,
        public ?string $currency = null, // 3-letter ISO
        public ?string $reference = null,
        public array $items = [], // [{description, amount, quantity?, unit_amount?}]
        public ?string $rawText = null,
        public ?float $confidence = null,
        public ?string $error = null,
        /** Free-form human-readable warnings from the validator. */
        public array $warnings = [],
    ) {}

    public static function success(string $provider, array $fields = []): self
    {
        return new self(
            status: self::STATUS_SUCCESS,
            provider: $provider,
            vendorName: $fields['vendor_name'] ?? null,
            billDate: $fields['bill_date'] ?? null,
            subtotal: isset($fields['subtotal']) ? (float) $fields['subtotal'] : null,
            taxAmount: isset($fields['tax_amount']) ? (float) $fields['tax_amount'] : null,
            totalAmount: isset($fields['total_amount']) ? (float) $fields['total_amount'] : null,
            currency: $fields['currency'] ?? null,
            reference: $fields['reference'] ?? null,
            items: $fields['items'] ?? [],
            rawText: $fields['raw_text'] ?? null,
            confidence: isset($fields['confidence']) ? (float) $fields['confidence'] : null,
        );
    }

    public static function failed(string $provider, string $error, ?string $rawText = null): self
    {
        return new self(
            status: self::STATUS_FAILED,
            provider: $provider,
            rawText: $rawText,
            error: $error,
        );
    }

    /**
     * Matches the historical mock OCRService::process() return shape:
     * [
     *   'status' => 'success'|'failed',
     *   'data'   => [supplier_name, bill_date, total_amount, tax_amount, currency, reference, items[]]
     * ]
     */
    public function toLegacyArray(): array
    {
        if ($this->status === self::STATUS_FAILED) {
            return [
                'status' => self::STATUS_FAILED,
                'data' => null,
                'error' => $this->error,
                'provider' => $this->provider,
            ];
        }

        return [
            'status' => self::STATUS_SUCCESS,
            'data' => [
                'supplier_name' => $this->vendorName,
                'bill_date' => $this->billDate,
                'subtotal' => $this->subtotal,
                'tax_amount' => $this->taxAmount,
                'total_amount' => $this->totalAmount,
                'currency' => $this->currency ?? 'MYR',
                'reference' => $this->reference,
                'items' => $this->items,
                'confidence' => $this->confidence,
                'warnings' => $this->warnings,
                'provider' => $this->provider,
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'provider' => $this->provider,
            'vendor_name' => $this->vendorName,
            'bill_date' => $this->billDate,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->taxAmount,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'items' => $this->items,
            'confidence' => $this->confidence,
            'warnings' => $this->warnings,
            'error' => $this->error,
        ];
    }
}
