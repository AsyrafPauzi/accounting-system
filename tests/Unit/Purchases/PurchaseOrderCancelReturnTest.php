<?php

namespace Tests\Unit\Purchases;

use App\Models\Bill;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PurchaseOrderCancelReturnTest extends TestCase
{
    public function test_cancel_rejects_already_cancelled(): void
    {
        $po = new PurchaseOrder(['status' => 'cancelled']);
        $po->setRelation('items', new Collection);
        $po->setRelation('bills', new Collection);
        $po->setRelation('goodsReceipts', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already cancelled');

        app(PurchaseOrderService::class)->cancel($po);
    }

    public function test_cancel_rejects_when_bills_exist(): void
    {
        $po = new PurchaseOrder(['status' => 'confirmed']);
        $po->setRelation('items', new Collection);
        $po->setRelation('bills', new Collection([new Bill(['bill_number' => 'BILL-1', 'status' => 'unpaid'])]));
        $po->setRelation('goodsReceipts', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has bills');

        app(PurchaseOrderService::class)->cancel($po);
    }

    public function test_cancel_rejects_when_qty_received(): void
    {
        $po = new PurchaseOrder(['status' => 'partially_received']);
        $po->setRelation('items', new Collection([
            new PurchaseOrderItem(['qty_received' => 2, 'qty_billed' => 0, 'quantity' => 5]),
        ]));
        $po->setRelation('bills', new Collection);
        $po->setRelation('goodsReceipts', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already received');

        app(PurchaseOrderService::class)->cancel($po);
    }

    public function test_cancel_rejects_active_goods_receipts(): void
    {
        $po = new PurchaseOrder(['status' => 'confirmed']);
        $po->setRelation('items', new Collection([
            new PurchaseOrderItem(['qty_received' => 0, 'qty_billed' => 0, 'quantity' => 5]),
        ]));
        $po->setRelation('bills', new Collection);
        $po->setRelation('goodsReceipts', new Collection([
            new GoodsReceipt(['status' => 'received', 'grn_number' => 'GRN-1']),
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('active goods receipts');

        app(PurchaseOrderService::class)->cancel($po);
    }

    public function test_assert_editable_blocks_billed_po(): void
    {
        $po = new PurchaseOrder(['status' => 'billed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        app(PurchaseOrderService::class)->assertEditable($po);
    }

    public function test_return_full_rejects_billed_grn(): void
    {
        $grn = new GoodsReceipt(['status' => 'billed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Use a supplier credit note');

        app(GoodsReceiptService::class)->returnFull($grn);
    }

    public function test_return_full_rejects_already_cancelled(): void
    {
        $grn = new GoodsReceipt(['status' => 'cancelled']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already returned');

        app(GoodsReceiptService::class)->returnFull($grn);
    }

    public function test_return_full_rejects_when_line_qty_billed(): void
    {
        $grn = new GoodsReceipt(['status' => 'received']);
        $grn->setRelation('items', new Collection([
            new GoodsReceiptItem(['quantity' => 2, 'qty_billed' => 1]),
        ]));
        $grn->setRelation('bills', new Collection);
        $grn->setRelation('purchaseOrder', null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Use a supplier credit note');

        app(GoodsReceiptService::class)->returnFull($grn);
    }

    public function test_assert_editable_blocks_billed_grn(): void
    {
        $grn = new GoodsReceipt(['status' => 'billed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        app(GoodsReceiptService::class)->assertEditable($grn);
    }

    public function test_normalize_kind_defaults_to_credit(): void
    {
        $this->assertSame('credit', app(\App\Services\BillService::class)->normalizeKind('nope'));
        $this->assertSame('cash', app(\App\Services\BillService::class)->normalizeKind('CASH'));
        $this->assertSame('claim', app(\App\Services\BillService::class)->normalizeKind('claim'));
    }
}
