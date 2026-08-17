<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private BillService $bills) {}

    public function nextNumber(): string
    {
        return DocumentNumber::next('purchase_orders', 'po_number', 'PO');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function create(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $net = 0.0;
            $tax = 0.0;
            foreach ($items as $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $net += $line;
                $tax += ($line * (float) ($item['tax_rate'] ?? 0)) / 100;
            }

            $po = PurchaseOrder::create([
                'po_number'         => $data['po_number'] ?? $this->nextNumber(),
                'supplier_id'       => $data['supplier_id'],
                'issue_date'        => $data['issue_date'],
                'expected_date'     => $data['expected_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed',
                'currency'          => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'exchange_rate'     => (float) ($data['exchange_rate'] ?? 1),
                'amount_before_tax' => round($net, 2),
                'tax_amount'        => round($tax, 2),
                'total_amount'      => round($net + $tax, 2),
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
            ]);

            foreach (array_values($items) as $idx => $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $po->items()->create([
                    'product_id'      => $item['product_id'] ?? null,
                    'account_code'    => $item['account_code'] ?? '5000',
                    'description'     => $item['description'],
                    'quantity'        => $item['quantity'],
                    'qty_received'    => 0,
                    'qty_billed'      => 0,
                    'unit_price'      => $item['unit_price'],
                    'tax_rate'        => $item['tax_rate'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount'          => $line,
                    'display_order'   => $idx,
                ]);
            }

            return $po->load('items');
        });
    }

    public function assertEditable(PurchaseOrder $po): void
    {
        if (in_array($po->status, ['received', 'billed', 'cancelled'], true)) {
            throw new \LogicException('This purchase order cannot be edited.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function update(PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        $this->assertEditable($po);
        $po->loadMissing('items');

        return DB::transaction(function () use ($po, $data, $items) {
            foreach ($items as $item) {
                $existingId = isset($item['id']) ? (int) $item['id'] : null;
                $existing = $existingId ? $po->items->firstWhere('id', $existingId) : null;
                $floor = $existing ? max((float) $existing->qty_received, (float) $existing->qty_billed) : 0;
                if ((float) $item['quantity'] + 0.0001 < $floor) {
                    throw new \LogicException('Line quantity cannot be below quantity already received or billed.');
                }
            }

            $net = 0.0;
            $tax = 0.0;
            foreach ($items as $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $net += $line;
                $tax += ($line * (float) ($item['tax_rate'] ?? 0)) / 100;
            }

            $po->update([
                'supplier_id'       => $data['supplier_id'] ?? $po->supplier_id,
                'issue_date'        => $data['issue_date'] ?? $po->issue_date,
                'expected_date'     => $data['expected_date'] ?? $po->expected_date,
                'notes'             => $data['notes'] ?? $po->notes,
                'amount_before_tax' => round($net, 2),
                'tax_amount'        => round($tax, 2),
                'total_amount'      => round($net + $tax, 2),
            ]);

            $keepIds = [];
            foreach (array_values($items) as $idx => $item) {
                $existingId = isset($item['id']) ? (int) $item['id'] : null;
                $existing = $existingId ? $po->items->firstWhere('id', $existingId) : null;
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $payload = [
                    'product_id'      => $item['product_id'] ?? null,
                    'account_code'    => $item['account_code'] ?? '5000',
                    'description'     => $item['description'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'tax_rate'        => $item['tax_rate'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount'          => $line,
                    'display_order'   => $idx,
                ];
                if ($existing) {
                    $existing->update($payload);
                    $keepIds[] = $existing->id;
                } else {
                    $created = $po->items()->create(array_merge($payload, [
                        'qty_received' => 0,
                        'qty_billed'   => 0,
                    ]));
                    $keepIds[] = $created->id;
                }
            }

            $po->items()->whereNotIn('id', $keepIds)->get()->each(function ($line) {
                if ((float) $line->qty_received > 0 || (float) $line->qty_billed > 0) {
                    throw new \LogicException('Cannot remove a line that has receipts or bills.');
                }
                $line->delete();
            });

            $this->refreshStatus($po->fresh('items'));

            return $po->fresh('items');
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        if ($po->status === 'cancelled') {
            throw new \LogicException('Purchase order is already cancelled.');
        }
        $po->loadMissing(['items', 'goodsReceipts', 'bills']);
        if ($po->bills->whereNotIn('status', ['void'])->isNotEmpty()) {
            throw new \LogicException('Cannot cancel: purchase order has bills. Use a supplier credit note if needed.');
        }
        if ($po->items->contains(fn ($i) => (float) $i->qty_received > 0 || (float) $i->qty_billed > 0)) {
            throw new \LogicException('Cannot cancel: goods already received. Return the goods receipt first.');
        }
        $activeGrn = $po->goodsReceipts->whereIn('status', ['received', 'billed']);
        if ($activeGrn->isNotEmpty()) {
            throw new \LogicException('Cannot cancel: active goods receipts exist. Return them first.');
        }

        $po->update(['status' => 'cancelled']);
    }

    /**
     * @param  array<int, float>  $quantities
     */
    public function convertToBill(PurchaseOrder $po, array $quantities = [], ?int $createdBy = null): Bill
    {
        $po->loadMissing('items');
        $items = [];
        $tax = 0.0;
        foreach ($po->items as $line) {
            $qty = $quantities[$line->id] ?? $line->qtyOpenToBill();
            $qty = min((float) $qty, $line->qtyOpenToBill());
            if ($qty <= 0) {
                continue;
            }
            $ratio = (float) $line->quantity > 0 ? $qty / (float) $line->quantity : 1;
            $amount = round((((float) $line->unit_price * $qty) - ((float) $line->discount_amount * $ratio)), 2);
            $tax += $amount * ((float) $line->tax_rate) / 100;
            $items[] = [
                'account_code' => $line->account_code ?: '5000',
                'description'  => $line->description,
                'quantity'     => $qty,
                'unit_amount'  => (float) $line->unit_price,
                'amount'       => $amount,
            ];
            $line->qty_billed = (float) $line->qty_billed + $qty;
            $line->save();
        }
        if ($items === []) {
            throw new \LogicException('Nothing left to bill on this purchase order.');
        }

        $bill = $this->bills->create([
            'bill_number'       => $this->bills->nextNumber(),
            'supplier_id'       => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'bill_date'         => now()->toDateString(),
            'due_date'          => now()->addDays(30)->toDateString(),
            'tax_amount'        => round($tax, 2),
            'created_by'        => $createdBy,
        ], $items);

        $this->refreshStatus($po);

        return $bill;
    }

    public function refreshStatus(PurchaseOrder $po): void
    {
        if ($po->status === 'cancelled') {
            return;
        }
        $po->loadMissing('items');
        $allBilled = $po->items->every(fn ($i) => $i->qtyOpenToBill() <= 0);
        $allReceived = $po->items->every(fn ($i) => $i->qtyOpenToReceive() <= 0);
        $anyReceived = $po->items->contains(fn ($i) => (float) $i->qty_received > 0);

        if ($allBilled) {
            $po->status = 'billed';
        } elseif ($allReceived) {
            $po->status = 'received';
        } elseif ($anyReceived) {
            $po->status = 'partially_received';
        } else {
            $po->status = 'confirmed';
        }
        $po->save();
    }
}
