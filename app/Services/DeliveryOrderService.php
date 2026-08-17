<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class DeliveryOrderService
{
    public function __construct(
        private InvoiceService $invoices,
        private SalesOrderService $salesOrders,
    ) {}

    public function nextNumber(): string
    {
        return DocumentNumber::next('delivery_orders', 'do_number', 'DO');
    }

    /**
     * @param  array<int, float>  $quantities  sales_order_item_id => qty
     */
    public function fromSalesOrder(SalesOrder $so, array $quantities = [], ?int $createdBy = null): DeliveryOrder
    {
        $so->loadMissing('items');

        return DB::transaction(function () use ($so, $quantities, $createdBy) {
            $do = DeliveryOrder::create([
                'do_number'      => $this->nextNumber(),
                'customer_id'    => $so->customer_id,
                'sales_order_id' => $so->id,
                'issue_date'     => now()->toDateString(),
                'delivery_date'  => now()->toDateString(),
                'status'         => 'delivered',
                'currency'       => $so->currency,
                'customer_notes' => $so->customer_notes,
                'created_by'     => $createdBy,
            ]);

            $any = false;
            foreach ($so->items as $idx => $line) {
                $qty = $quantities[$line->id] ?? $line->qtyOpenToDeliver();
                $qty = min((float) $qty, $line->qtyOpenToDeliver());
                if ($qty <= 0) {
                    continue;
                }
                $any = true;
                $do->items()->create([
                    'sales_order_item_id' => $line->id,
                    'product_id'          => $line->product_id,
                    'description'         => $line->description,
                    'quantity'            => $qty,
                    'qty_invoiced'        => 0,
                    'display_order'       => $idx,
                ]);
                $line->qty_delivered = (float) $line->qty_delivered + $qty;
                $line->save();
            }
            if (! $any) {
                throw new \LogicException('Nothing left to deliver on this sales order.');
            }

            $this->salesOrders->refreshStatus($so);

            return $do->load('items');
        });
    }

    public function convertToInvoice(DeliveryOrder $do, ?int $createdBy = null): Invoice
    {
        $do->loadMissing(['items', 'salesOrder.items']);
        $so = $do->salesOrder;
        $items = [];
        foreach ($do->items as $line) {
            $qty = $line->qtyOpenToInvoice();
            if ($qty <= 0) {
                continue;
            }
            $soItem = $so?->items->firstWhere('id', $line->sales_order_item_id);
            $items[] = [
                'product_id'          => $line->product_id ?? $soItem?->product_id,
                'account_code'        => $soItem?->account_code,
                'description'         => $line->description,
                'quantity'            => $qty,
                'unit_price'          => (float) ($soItem?->unit_price ?? 0),
                'tax_rate'            => (float) ($soItem?->tax_rate ?? 0),
                'discount_amount'     => 0,
                'item_classification' => $soItem?->item_classification ?: '022',
            ];
            $line->qty_invoiced = (float) $line->qty_invoiced + $qty;
            $line->save();
            if ($soItem) {
                $soItem->qty_invoiced = (float) $soItem->qty_invoiced + $qty;
                $soItem->save();
            }
        }
        if ($items === []) {
            throw new \LogicException('Nothing left to invoice on this delivery order.');
        }

        $invoice = $this->invoices->create([
            'invoice_number'    => $this->invoices->nextNumber(),
            'msic_code'         => '00000',
            'customer_id'       => $do->customer_id,
            'issue_date'        => now()->toDateString(),
            'due_date'          => now()->addDays(30)->toDateString(),
            'currency'          => $do->currency ?? 'MYR',
            'exchange_rate'     => $so?->exchange_rate ?? 1,
            'shipping_amount'   => $so?->shipping_amount ?? 0,
            'customer_notes'    => $do->customer_notes,
            'created_by'        => $createdBy,
            'sales_order_id'    => $do->sales_order_id,
            'delivery_order_id' => $do->id,
        ], $items);

        $do->update(['status' => 'invoiced']);
        if ($so) {
            $this->salesOrders->refreshStatus($so);
        }

        return $invoice;
    }

    public function assertEditable(DeliveryOrder $do): void
    {
        if (in_array($do->status, ['invoiced', 'cancelled'], true)) {
            throw new \LogicException("Delivery order with status '{$do->status}' cannot be edited.");
        }
        if ($do->status !== 'delivered') {
            throw new \LogicException('Only delivered (uninvoiced) delivery orders can be edited.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DeliveryOrder $do, array $data): DeliveryOrder
    {
        $this->assertEditable($do);

        $do->update([
            'issue_date'     => $data['issue_date'] ?? $do->issue_date,
            'delivery_date'  => $data['delivery_date'] ?? $do->delivery_date,
            'customer_notes' => array_key_exists('customer_notes', $data) ? $data['customer_notes'] : $do->customer_notes,
        ]);

        return $do->fresh(['items', 'customer', 'salesOrder']);
    }

    /**
     * Full return of an uninvoiced delivery order. Restores SO qty_delivered.
     */
    public function returnFull(DeliveryOrder $do): void
    {
        if ($do->status === 'cancelled') {
            throw new \LogicException('Delivery order is already returned/cancelled.');
        }
        if ($do->status === 'invoiced') {
            throw new \LogicException('Cannot return an invoiced delivery order. Use a credit note.');
        }
        $do->loadMissing(['items', 'salesOrder.items', 'invoices']);
        if ($do->invoices->isNotEmpty() || $do->items->contains(fn ($i) => (float) $i->qty_invoiced > 0)) {
            throw new \LogicException('Cannot return an invoiced delivery order. Use a credit note.');
        }

        DB::transaction(function () use ($do) {
            $so = $do->salesOrder;
            foreach ($do->items as $line) {
                if (! $so) {
                    continue;
                }
                $soItem = $so->items->firstWhere('id', $line->sales_order_item_id);
                if (! $soItem) {
                    continue;
                }
                $soItem->qty_delivered = max(0, (float) $soItem->qty_delivered - (float) $line->quantity);
                $soItem->save();
            }
            $do->update(['status' => 'cancelled']);
            if ($so) {
                $this->salesOrders->refreshStatus($so->fresh('items'));
            }
        });
    }
}
