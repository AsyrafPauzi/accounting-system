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
 */
class PurchasesDocumentTrail
{
    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forBill(Bill $bill): array
    {
        $bill->loadMissing(['purchaseOrder', 'goodsReceipt']);
        $po = $bill->purchaseOrder;
        $grn = $bill->goodsReceipt;
        if (! $po && $bill->purchase_order_id) {
            $po = PurchaseOrder::query()->find($bill->purchase_order_id);
        }
        if (! $grn && $bill->goods_receipt_id) {
            $grn = GoodsReceipt::query()->find($bill->goods_receipt_id);
        }

        $steps = [];
        if ($po) {
            $steps[] = $this->step('purchase_order', $po->id, $po->po_number, 'purchase-orders.show', $po->status);
        }
        if ($grn) {
            $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);
        }
        $steps[] = $this->step('bill', $bill->id, $bill->bill_number, 'bills.show', $bill->status);

        if (Schema::hasTable('supplier_credit_notes')) {
            $cns = SupplierCreditNote::query()
                ->where(function ($q) use ($bill) {
                    $q->where('bill_id', $bill->id);
                    if (Schema::hasTable('supplier_credit_note_applications')) {
                        $ids = $bill->creditNoteApplications()->pluck('supplier_credit_note_id');
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
        $po->loadMissing(['goodsReceipts', 'bills']);
        $steps = [$this->step('purchase_order', $po->id, $po->po_number, 'purchase-orders.show', $po->status)];
        foreach ($po->goodsReceipts as $grn) {
            $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);
        }
        foreach ($po->bills as $bill) {
            $steps = array_merge($steps, $this->forBill($bill));
        }

        return $this->uniqueSteps($steps);
    }

    /**
     * @return list<array{type: string, id: int, number: string, href: string|null, status: string|null}>
     */
    public function forGoodsReceipt(GoodsReceipt $grn): array
    {
        $grn->loadMissing(['purchaseOrder', 'bills']);
        $steps = [];
        if ($grn->purchaseOrder) {
            $steps[] = $this->step('purchase_order', $grn->purchaseOrder->id, $grn->purchaseOrder->po_number, 'purchase-orders.show', $grn->purchaseOrder->status);
        }
        $steps[] = $this->step('goods_receipt', $grn->id, $grn->grn_number, 'goods-receipts.show', $grn->status);
        foreach ($grn->bills as $bill) {
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
