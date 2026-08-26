<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(
        private BillService $bills,
        private PurchaseOrderService $orders,
        private InventoryService $inventory,
    ) {}

    public function nextNumber(): string
    {
        return DocumentNumber::next('goods_receipts', 'grn_number', 'GRN');
    }

    /**
     * @param  array<int, float>  $quantities
     */
    public function fromPurchaseOrder(PurchaseOrder $po, array $quantities = [], ?int $createdBy = null): GoodsReceipt
    {
        $po->loadMissing('items');

        return DB::transaction(function () use ($po, $quantities, $createdBy) {
            $grn = GoodsReceipt::create([
                'grn_number'        => $this->nextNumber(),
                'supplier_id'       => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'issue_date'        => now()->toDateString(),
                'received_date'     => now()->toDateString(),
                'status'            => 'received',
                'currency'          => $po->currency,
                'notes'             => $po->notes,
                'created_by'        => $createdBy,
            ]);

            $any = false;
            foreach ($po->items as $idx => $line) {
                $qty = $quantities[$line->id] ?? $line->qtyOpenToReceive();
                $qty = min((float) $qty, $line->qtyOpenToReceive());
                if ($qty <= 0) {
                    continue;
                }
                $any = true;
                $grn->items()->create([
                    'purchase_order_item_id' => $line->id,
                    'product_id'             => $line->product_id,
                    'description'            => $line->description,
                    'quantity'               => $qty,
                    'qty_billed'             => 0,
                    'display_order'          => $idx,
                ]);
                if ($line->product_id) {
                    $product = \App\Models\Product::query()->find($line->product_id);
                    if ($product?->track_inventory) {
                        $this->inventory->receive(
                            $product,
                            $qty,
                            (float) ($line->unit_price ?? 0),
                            $grn->received_date,
                            'GoodsReceipt',
                            (int) $grn->id,
                        );
                    }
                }
                $line->qty_received = (float) $line->qty_received + $qty;
                $line->save();
            }
            if (! $any) {
                throw new \LogicException('Nothing left to receive on this purchase order.');
            }

            $this->orders->refreshStatus($po);

            return $grn->load('items');
        });
    }

    public function assertEditable(GoodsReceipt $grn): void
    {
        if (in_array($grn->status, ['billed', 'cancelled'], true)) {
            throw new \LogicException('This goods receipt cannot be edited.');
        }
    }

    public function update(GoodsReceipt $grn, array $data): GoodsReceipt
    {
        $this->assertEditable($grn);
        $grn->update([
            'notes'         => $data['notes'] ?? $grn->notes,
            'received_date' => $data['received_date'] ?? $grn->received_date,
            'issue_date'    => $data['issue_date'] ?? $grn->issue_date,
        ]);

        return $grn->fresh();
    }

    public function returnFull(GoodsReceipt $grn): void
    {
        if ($grn->status === 'cancelled') {
            throw new \LogicException('Goods receipt is already returned.');
        }
        if ($grn->status === 'billed') {
            throw new \LogicException('Cannot return a billed goods receipt. Use a supplier credit note.');
        }
        $grn->loadMissing(['items', 'purchaseOrder.items', 'bills']);
        if ($grn->bills->whereNotIn('status', ['void', 'draft'])->isNotEmpty()
            || $grn->items->contains(fn ($i) => (float) $i->qty_billed > 0)) {
            throw new \LogicException('Cannot return a billed goods receipt. Use a supplier credit note.');
        }

        DB::transaction(function () use ($grn) {
            $po = $grn->purchaseOrder;
            foreach ($grn->items as $line) {
                if (! $po) {
                    continue;
                }
                $poItem = $po->items->firstWhere('id', $line->purchase_order_item_id);
                if (! $poItem) {
                    continue;
                }
                $poItem->qty_received = max(0, (float) $poItem->qty_received - (float) $line->quantity);
                $poItem->save();
            }
            $grn->update(['status' => 'cancelled']);
            if ($po) {
                $this->orders->refreshStatus($po->fresh('items'));
            }
        });
    }

    public function convertToBill(GoodsReceipt $grn, ?int $createdBy = null): Bill
    {
        $grn->loadMissing(['items', 'purchaseOrder.items']);
        $po = $grn->purchaseOrder;
        $items = [];
        $tax = 0.0;
        foreach ($grn->items as $line) {
            $qty = $line->qtyOpenToBill();
            if ($qty <= 0) {
                continue;
            }
            $poItem = $po?->items->firstWhere('id', $line->purchase_order_item_id);
            $amount = round((float) ($poItem?->unit_price ?? 0) * $qty, 2);
            $tax += $amount * ((float) ($poItem?->tax_rate ?? 0)) / 100;
            $items[] = [
                'account_code' => $poItem?->account_code ?: '5000',
                'description'  => $line->description,
                'quantity'     => $qty,
                'unit_amount'  => (float) ($poItem?->unit_price ?? 0),
                'amount'       => $amount,
            ];
            $line->qty_billed = (float) $line->qty_billed + $qty;
            $line->save();
            if ($poItem) {
                $poItem->qty_billed = (float) $poItem->qty_billed + $qty;
                $poItem->save();
            }
        }
        if ($items === []) {
            throw new \LogicException('Nothing left to bill on this goods receipt.');
        }

        $bill = $this->bills->create([
            'bill_number'       => $this->bills->nextNumber(),
            'supplier_id'       => $grn->supplier_id,
            'purchase_order_id' => $grn->purchase_order_id,
            'goods_receipt_id'  => $grn->id,
            'bill_date'         => now()->toDateString(),
            'due_date'          => now()->addDays(30)->toDateString(),
            'tax_amount'        => round($tax, 2),
            'created_by'        => $createdBy,
        ], $items);

        $grn->update(['status' => 'billed']);
        if ($po) {
            $this->orders->refreshStatus($po);
        }

        return $bill;
    }
}
