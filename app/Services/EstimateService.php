<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\Invoice;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Domain service for Estimates / Quotations.
 *
 * Estimates never post to the General Ledger. The only point at which an
 * estimate touches the ledger is when an admin clicks "Convert to Invoice",
 * at which point a fresh draft Invoice is created. The invoice still has to
 * be posted manually before journals appear.
 */
class EstimateService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * Suggest the next estimate number, e.g. EST-1, EST-2…
     * Falls back to EST-1 when there are none yet.
     */
    public function nextNumber(): string
    {
        return DocumentNumber::next('estimates', 'estimate_number', 'EST');
    }

    /**
     * Create a draft estimate with line items inside a transaction.
     * Returns the saved estimate with `items` already persisted.
     */
    public function create(array $data, array $items): Estimate
    {
        return DB::transaction(function () use ($data, $items) {
            $currency = strtoupper((string) ($data['currency'] ?? 'MYR'));
            $totals = $this->invoices->computeTotals(
                $items,
                (float) ($data['shipping_amount'] ?? 0),
                $currency
            );

            $estimate = Estimate::create([
                'estimate_number'     => $data['estimate_number'],
                'currency'            => $currency,
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? 1),
                'customer_id'         => $data['customer_id'],
                'issue_date'          => $data['issue_date'],
                'expiry_date'         => $data['expiry_date'] ?? null,
                'status'              => $data['status'] ?? 'draft',
                'amount_before_tax'   => $totals['subtotal'],
                'discount_total'      => $totals['discountTotal'],
                'tax_amount'          => $totals['taxTotal'],
                'shipping_amount'     => $data['shipping_amount'] ?? 0,
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'total_amount'        => $totals['roundedTotal'],
                'customer_notes'      => $data['customer_notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
                'created_by'          => $data['created_by'] ?? null,
            ]);

            $this->syncItems($estimate, $items);

            return $estimate->load('items');
        });
    }

    /**
     * Replace header + items on an existing estimate. Locked once converted.
     */
    public function update(Estimate $estimate, array $data, array $items): void
    {
        if ($estimate->isConverted()) {
            throw new \LogicException('This estimate has already been converted to an invoice and cannot be edited.');
        }

        DB::transaction(function () use ($estimate, $data, $items) {
            $currency = strtoupper((string) ($data['currency'] ?? $estimate->currency ?? 'MYR'));
            $totals = $this->invoices->computeTotals(
                $items,
                (float) ($data['shipping_amount'] ?? 0),
                $currency
            );

            $estimate->update([
                'estimate_number'     => $data['estimate_number'],
                'customer_id'         => $data['customer_id'],
                'issue_date'          => $data['issue_date'],
                'expiry_date'         => $data['expiry_date'] ?? null,
                'currency'            => $currency,
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? $estimate->exchange_rate ?? 1),
                'amount_before_tax'   => $totals['subtotal'],
                'discount_total'      => $totals['discountTotal'],
                'tax_amount'          => $totals['taxTotal'],
                'shipping_amount'     => $data['shipping_amount'] ?? 0,
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'total_amount'        => $totals['roundedTotal'],
                'customer_notes'      => $data['customer_notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
            ]);

            $estimate->items()->delete();
            $this->syncItems($estimate, $items);
        });
    }

    /**
     * Move an estimate between statuses with safety checks.
     * Allowed transitions:
     *   draft     → sent | accepted | rejected
     *   sent      → accepted | rejected | expired | draft
     *   accepted  → converted (via convertToInvoice) | rejected
     *   rejected  → draft (re-open)
     *   expired   → draft (re-open) | sent (extend)
     *   converted → no transitions
     */
    public function transition(Estimate $estimate, string $newStatus): void
    {
        $allowed = [
            'draft'    => ['sent', 'accepted', 'rejected'],
            'sent'     => ['accepted', 'rejected', 'expired', 'draft'],
            'accepted' => ['rejected'],
            'rejected' => ['draft'],
            'expired'  => ['draft', 'sent'],
        ];

        if ($estimate->status === 'converted') {
            throw new \LogicException('Converted estimates are locked and cannot change status.');
        }

        if (! in_array($newStatus, $allowed[$estimate->status] ?? [], true)) {
            throw new \LogicException("Cannot move estimate from {$estimate->status} to {$newStatus}.");
        }

        $estimate->update(['status' => $newStatus]);
    }

    /**
     * Convert an accepted estimate into a fresh DRAFT invoice. The new invoice
     * inherits header + line items but starts unposted, so the user reviews
     * it once before pushing to the GL.
     *
     * Marks the estimate `converted` and stores a back-reference. Throws when
     * the estimate is already converted or in an unallowed status.
     */
    public function convertToInvoice(Estimate $estimate, array $overrides = []): Invoice
    {
        if ($estimate->isConverted()) {
            throw new \LogicException('This estimate has already been converted.');
        }

        if (! in_array($estimate->status, ['draft', 'sent', 'accepted'], true)) {
            throw new \LogicException("Estimates with status '{$estimate->status}' cannot be converted.");
        }

        return DB::transaction(function () use ($estimate, $overrides) {
            $items = $estimate->items->map(fn ($i) => [
                'product_id'          => $i->product_id,
                'description'         => $i->description,
                'quantity'            => (float) $i->quantity,
                'unit_price'          => (float) $i->unit_price,
                'tax_rate'            => (float) $i->tax_rate,
                'discount_amount'     => (float) $i->discount_amount,
                'item_classification' => $i->item_classification ?: ($overrides['item_classification'] ?? '022'),
            ])->all();

            $invoice = $this->invoices->create([
                'invoice_number'  => $overrides['invoice_number'] ?? $this->nextInvoiceNumber(),
                'msic_code'       => $overrides['msic_code'] ?? '00000',
                'customer_id'     => $estimate->customer_id,
                'issue_date'      => $overrides['issue_date'] ?? now()->toDateString(),
                'due_date'        => $overrides['due_date'] ?? null,
                'currency'        => $estimate->currency,
                'exchange_rate'   => $estimate->exchange_rate,
                'shipping_amount' => $estimate->shipping_amount,
                'customer_notes'  => $estimate->customer_notes,
                'show_signature'  => true,
                'created_by'      => $overrides['created_by'] ?? null,
                'estimate_id'     => $estimate->id,
            ], $items);

            $estimate->update([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Walk through estimates with `expiry_date < today` and flip them to
     * `expired` so the index page shows the right state. Safe to run from
     * a cron without arguments.
     */
    public function markExpired(): int
    {
        return Estimate::query()
            ->whereIn('status', ['draft', 'sent'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->update(['status' => 'expired']);
    }

    public function duplicate(Estimate $source, ?int $createdBy = null): Estimate
    {
        $source->loadMissing('items');
        $items = $source->items->map(fn ($i) => [
            'product_id'          => $i->product_id,
            'description'         => $i->description,
            'quantity'            => (float) $i->quantity,
            'unit_price'          => (float) $i->unit_price,
            'tax_rate'            => (float) $i->tax_rate,
            'discount_amount'     => (float) ($i->discount_amount ?? 0),
            'item_classification' => $i->item_classification ?: '022',
        ])->all();

        $expiry = $source->expiry_date && $source->issue_date
            ? now()->addDays(max(1, $source->issue_date->diffInDays($source->expiry_date)))
            : now()->addDays(30);

        return $this->create([
            'estimate_number' => $this->nextNumber(),
            'currency'        => $source->currency,
            'exchange_rate'   => $source->exchange_rate,
            'customer_id'     => $source->customer_id,
            'issue_date'      => now()->toDateString(),
            'expiry_date'     => $expiry->toDateString(),
            'shipping_amount' => $source->shipping_amount,
            'customer_notes'  => $source->customer_notes,
            'private_notes'   => $source->private_notes,
            'created_by'      => $createdBy,
        ], $items);
    }

    private function syncItems(Estimate $estimate, array $items): void
    {
        foreach (array_values($items) as $idx => $item) {
            $estimate->items()->create([
                'product_id'          => $item['product_id'] ?? null,
                'item_classification' => $item['item_classification'] ?? null,
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_rate'            => $item['tax_rate'] ?? 0,
                'discount_amount'     => $item['discount_amount'] ?? 0,
                'amount'              => ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0),
                'display_order'       => $item['display_order'] ?? $idx,
            ]);
        }
    }

    private function nextInvoiceNumber(): string
    {
        return DocumentNumber::next('invoices', 'invoice_number', 'INV');
    }
}
