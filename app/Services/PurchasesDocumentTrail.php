<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierCreditNote;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only PO → GR → Bill → SCN chain. No posting.
 *
 * Intentionally avoids BelongsTo / loadMissing on Bill. Controllers often
 * eager-load bills with a column subset; missing FKs throw
 * MissingAttributeException under Laravel's preventAccessingMissingAttributes.
 */
class PurchasesDocumentTrail
{
    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forBill(Bill $bill): array
    {
        $row = Bill::query()
            ->whereKey($bill->getKey())
            ->first(['id', 'bill_number', 'status', 'purchase_order_id', 'goods_receipt_id']);

        if (! $row) {
            return [];
        }

        $po = $row->purchase_order_id
            ? PurchaseOrder::query()->find($row->purchase_order_id, ['id', 'po_number', 'status'])
            : null;
        $grn = $row->goods_receipt_id
            ? GoodsReceipt::query()->find($row->goods_receipt_id, ['id', 'grn_number', 'status'])
            : null;

        $steps = [];
        if ($po) {
            $steps[] = $this->step('purchase_order', $po->id, $po->po_number, 'purchase-orders.show', $po->status);
        }
        if ($grn) {
            $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);
        }
        $steps[] = $this->step('bill', $row->id, $row->bill_number, 'bills.show', $row->status);

        if (Schema::hasTable('supplier_credit_notes')) {
            $cns = SupplierCreditNote::query()
                ->where(function ($q) use ($row) {
                    $q->where('bill_id', $row->id);
                    if (Schema::hasTable('supplier_credit_note_applications')) {
                        $ids = $row->creditNoteApplications()->pluck('supplier_credit_note_id');
                        if ($ids->isNotEmpty()) {
                            $q->orWhereIn('id', $ids);
                        }
                    }
                })
                ->whereNull('deleted_at')
                ->get(['id', 'scn_number', 'status']);
            foreach ($cns as $cn) {
                $steps[] = $this->step('supplier_credit_note', $cn->id, $cn->scn_number, 'supplier-credit-notes.show', $cn->status);
            }
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forPurchaseOrder(PurchaseOrder $po): array
    {
        $steps = [$this->step('purchase_order', $po->id, $po->po_number, 'purchase-orders.show', $po->status)];

        $receipts = GoodsReceipt::query()
            ->where('purchase_order_id', $po->id)
            ->get(['id', 'grn_number', 'status']);
        foreach ($receipts as $grn) {
            $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);
        }

        $bills = Bill::query()
            ->where('purchase_order_id', $po->id)
            ->get(['id', 'bill_number', 'status', 'purchase_order_id', 'goods_receipt_id']);
        foreach ($bills as $bill) {
            $steps = array_merge($steps, $this->forBill($bill));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forGoodsReceipt(GoodsReceipt $grn): array
    {
        $steps = [];

        if ($grn->purchase_order_id) {
            $po = PurchaseOrder::query()->find($grn->purchase_order_id, ['id', 'po_number', 'status']);
            if ($po) {
                $steps[] = $this->step('purchase_order', $po->id, $po->po_number, 'purchase-orders.show', $po->status);
            }
        }

        $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);

        $bills = Bill::query()
            ->where('goods_receipt_id', $grn->id)
            ->get(['id', 'bill_number', 'status', 'purchase_order_id', 'goods_receipt_id']);
        foreach ($bills as $bill) {
            $steps = array_merge($steps, $this->forBill($bill));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return array{type: string, id: int, number: string, href: string|null, status: string|null}
     */
    private function step(string $type, int $id, string $number, string $route, ?string $status): array
    {
        $href = Route::has($route) ? route($route, $id) : null;

        return compact('type', 'id', 'number', 'href', 'status');
    }

    /**
     * @param  list<array{type: string, id: int, number: string, href: string|null, status: string|null}>  $steps
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    private function uniqueSteps(array $steps): array
    {
        $seen = [];
        $out = [];
        foreach ($steps as $step) {
            $key = $step['type'].':'.$step['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $step;
        }

        return $out;
    }
}
