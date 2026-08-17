<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private InvoiceService $invoices) {}

    public function nextNumber(): string
    {
        return DocumentNumber::next('sales_orders', 'so_number', 'SO');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $currency = strtoupper((string) ($data['currency'] ?? 'MYR'));
            $totals = $this->invoices->computeTotals($items, (float) ($data['shipping_amount'] ?? 0), $currency);

            $so = SalesOrder::create([
                'so_number'           => $data['so_number'] ?? $this->nextNumber(),
                'customer_id'         => $data['customer_id'],
                'estimate_id'         => $data['estimate_id'] ?? null,
                'issue_date'          => $data['issue_date'],
                'expected_date'       => $data['expected_date'] ?? null,
                'status'              => $data['status'] ?? 'confirmed',
                'currency'            => $currency,
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? 1),
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

            foreach (array_values($items) as $idx => $item) {
                $so->items()->create([
                    'product_id'          => $item['product_id'] ?? null,
                    'account_code'        => $item['account_code'] ?? null,
                    'item_classification' => $item['item_classification'] ?? '022',
                    'description'         => $item['description'],
                    'quantity'            => $item['quantity'],
                    'qty_delivered'       => 0,
                    'qty_invoiced'        => 0,
                    'unit_price'          => $item['unit_price'],
                    'tax_rate'            => $item['tax_rate'] ?? 0,
                    'discount_amount'     => $item['discount_amount'] ?? 0,
                    'amount'              => ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0),
                    'display_order'       => $idx,
                ]);
            }

            return $so->load('items');
        });
    }

    public function fromEstimate(Estimate $estimate, ?int $createdBy = null): SalesOrder
    {
        if (! in_array($estimate->status, ['draft', 'sent', 'accepted'], true)) {
            throw new \LogicException("Estimates with status '{$estimate->status}' cannot become a sales order.");
        }
        $estimate->loadMissing('items');
        $items = $estimate->items->map(fn ($i) => [
            'product_id'          => $i->product_id,
            'description'         => $i->description,
            'quantity'            => (float) $i->quantity,
            'unit_price'          => (float) $i->unit_price,
            'tax_rate'            => (float) $i->tax_rate,
            'discount_amount'     => (float) ($i->discount_amount ?? 0),
            'item_classification' => $i->item_classification ?: '022',
        ])->all();

        $so = $this->create([
            'customer_id'     => $estimate->customer_id,
            'estimate_id'     => $estimate->id,
            'issue_date'      => now()->toDateString(),
            'currency'        => $estimate->currency,
            'exchange_rate'   => $estimate->exchange_rate,
            'shipping_amount' => $estimate->shipping_amount,
            'customer_notes'  => $estimate->customer_notes,
            'private_notes'   => $estimate->private_notes,
            'created_by'      => $createdBy,
        ], $items);

        $estimate->update(['status' => 'converted']);

        return $so;
    }

    public function convertToInvoice(SalesOrder $so, array $quantities = [], ?int $createdBy = null): Invoice
    {
        $so->loadMissing('items');
        $items = [];
        foreach ($so->items as $line) {
            $qty = $quantities[$line->id] ?? $line->qtyOpenToInvoice();
            $qty = min((float) $qty, $line->qtyOpenToInvoice());
            if ($qty <= 0) {
                continue;
            }
            $ratio = (float) $line->quantity > 0 ? $qty / (float) $line->quantity : 1;
            $items[] = [
                'product_id'          => $line->product_id,
                'account_code'        => $line->account_code,
                'description'         => $line->description,
                'quantity'            => $qty,
                'unit_price'          => (float) $line->unit_price,
                'tax_rate'            => (float) $line->tax_rate,
                'discount_amount'     => round((float) $line->discount_amount * $ratio, 2),
                'item_classification' => $line->item_classification ?: '022',
            ];
            $line->qty_invoiced = (float) $line->qty_invoiced + $qty;
            $line->save();
        }
        if ($items === []) {
            throw new \LogicException('Nothing left to invoice on this sales order.');
        }

        $invoice = $this->invoices->create([
            'invoice_number'  => $this->invoices->nextNumber(),
            'msic_code'       => '00000',
            'customer_id'     => $so->customer_id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => $so->currency,
            'exchange_rate'   => $so->exchange_rate,
            'shipping_amount' => $so->shipping_amount,
            'customer_notes'  => $so->customer_notes,
            'created_by'      => $createdBy,
            'sales_order_id'  => $so->id,
        ], $items);

        $this->refreshStatus($so);

        return $invoice;
    }

    public function refreshStatus(SalesOrder $so): void
    {
        $so->loadMissing('items');
        if ($so->status === 'cancelled') {
            return;
        }
        $allInvoiced = $so->items->every(fn ($i) => $i->qtyOpenToInvoice() <= 0);
        $allDelivered = $so->items->every(fn ($i) => $i->qtyOpenToDeliver() <= 0);
        $anyDelivered = $so->items->contains(fn ($i) => (float) $i->qty_delivered > 0);

        if ($allInvoiced && $anyDelivered) {
            $so->status = 'invoiced';
        } elseif ($allDelivered && $anyDelivered) {
            $so->status = 'delivered';
        } elseif ($anyDelivered) {
            $so->status = 'partially_delivered';
        } else {
            $so->status = in_array($so->status, ['draft'], true) ? 'draft' : 'confirmed';
        }
        $so->save();
    }

    public function assertEditable(SalesOrder $so): void
    {
        if (in_array($so->status, ['delivered', 'invoiced', 'cancelled'], true)) {
            throw new \LogicException("Sales order with status '{$so->status}' cannot be edited.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function update(SalesOrder $so, array $data, array $items): SalesOrder
    {
        $this->assertEditable($so);
        $so->loadMissing('items');

        return DB::transaction(function () use ($so, $data, $items) {
            foreach ($items as $item) {
                $existingId = isset($item['id']) ? (int) $item['id'] : null;
                $existing = $existingId ? $so->items->firstWhere('id', $existingId) : null;
                $delivered = $existing ? (float) $existing->qty_delivered : 0;
                if ((float) $item['quantity'] + 0.0001 < $delivered) {
                    throw new \LogicException('Line quantity cannot be below quantity already delivered.');
                }
            }

            $currency = strtoupper((string) ($data['currency'] ?? $so->currency ?? 'MYR'));
            $totals = $this->invoices->computeTotals($items, (float) ($data['shipping_amount'] ?? $so->shipping_amount ?? 0), $currency);

            $so->update([
                'customer_id'         => $data['customer_id'] ?? $so->customer_id,
                'issue_date'          => $data['issue_date'] ?? $so->issue_date,
                'expected_date'       => $data['expected_date'] ?? $so->expected_date,
                'currency'            => $currency,
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? $so->exchange_rate ?? 1),
                'amount_before_tax'   => $totals['subtotal'],
                'discount_total'      => $totals['discountTotal'],
                'tax_amount'          => $totals['taxTotal'],
                'shipping_amount'     => $data['shipping_amount'] ?? $so->shipping_amount ?? 0,
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'total_amount'        => $totals['roundedTotal'],
                'customer_notes'      => $data['customer_notes'] ?? $so->customer_notes,
                'private_notes'       => $data['private_notes'] ?? $so->private_notes,
            ]);

            $keepIds = [];
            foreach (array_values($items) as $idx => $item) {
                $existingId = isset($item['id']) ? (int) $item['id'] : null;
                $existing = $existingId ? $so->items->firstWhere('id', $existingId) : null;
                $payload = [
                    'product_id'          => $item['product_id'] ?? null,
                    'account_code'        => $item['account_code'] ?? null,
                    'item_classification' => $item['item_classification'] ?? '022',
                    'description'         => $item['description'],
                    'quantity'            => $item['quantity'],
                    'unit_price'          => $item['unit_price'],
                    'tax_rate'            => $item['tax_rate'] ?? 0,
                    'discount_amount'     => $item['discount_amount'] ?? 0,
                    'amount'              => ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0),
                    'display_order'       => $idx,
                ];
                if ($existing) {
                    $existing->update($payload);
                    $keepIds[] = $existing->id;
                } else {
                    $created = $so->items()->create(array_merge($payload, [
                        'qty_delivered' => 0,
                        'qty_invoiced'  => 0,
                    ]));
                    $keepIds[] = $created->id;
                }
            }

            $so->items()->whereNotIn('id', $keepIds)->get()->each(function ($line) {
                if ((float) $line->qty_delivered > 0 || (float) $line->qty_invoiced > 0) {
                    throw new \LogicException('Cannot remove a line that has deliveries or invoices.');
                }
                $line->delete();
            });

            $this->refreshStatus($so->fresh('items'));

            return $so->fresh('items');
        });
    }

    public function cancel(SalesOrder $so): void
    {
        if ($so->status === 'cancelled') {
            throw new \LogicException('Sales order is already cancelled.');
        }
        $so->loadMissing(['items', 'invoices', 'deliveryOrders']);
        if ($so->invoices->isNotEmpty()) {
            throw new \LogicException('Cannot cancel: sales order has invoices. Use a credit note if needed.');
        }
        if ($so->items->contains(fn ($i) => (float) $i->qty_delivered > 0)) {
            throw new \LogicException('Cannot cancel: goods already delivered. Return the delivery order first.');
        }
        $activeDos = $so->deliveryOrders->whereIn('status', ['delivered', 'invoiced']);
        if ($activeDos->isNotEmpty()) {
            throw new \LogicException('Cannot cancel: active delivery orders exist. Return them first.');
        }

        $so->update(['status' => 'cancelled']);
    }
}
