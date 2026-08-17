<?php

namespace Tests\Unit\Sales;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\DeliveryOrderService;
use App\Services\SalesOrderService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class SalesOrderCancelReturnTest extends TestCase
{
    public function test_cancel_rejects_already_cancelled(): void
    {
        $so = new SalesOrder(['status' => 'cancelled']);
        $so->setRelation('items', new Collection);
        $so->setRelation('invoices', new Collection);
        $so->setRelation('deliveryOrders', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already cancelled');

        app(SalesOrderService::class)->cancel($so);
    }

    public function test_cancel_rejects_when_invoices_exist(): void
    {
        $so = new SalesOrder(['status' => 'confirmed']);
        $so->setRelation('items', new Collection);
        $so->setRelation('invoices', new Collection([new Invoice(['invoice_number' => 'INV-1'])]));
        $so->setRelation('deliveryOrders', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has invoices');

        app(SalesOrderService::class)->cancel($so);
    }

    public function test_cancel_rejects_when_qty_delivered(): void
    {
        $so = new SalesOrder(['status' => 'partially_delivered']);
        $so->setRelation('items', new Collection([
            new SalesOrderItem(['qty_delivered' => 2, 'quantity' => 5]),
        ]));
        $so->setRelation('invoices', new Collection);
        $so->setRelation('deliveryOrders', new Collection);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already delivered');

        app(SalesOrderService::class)->cancel($so);
    }

    public function test_cancel_rejects_active_delivery_orders(): void
    {
        $so = new SalesOrder(['status' => 'confirmed']);
        $so->setRelation('items', new Collection([
            new SalesOrderItem(['qty_delivered' => 0, 'quantity' => 5]),
        ]));
        $so->setRelation('invoices', new Collection);
        $so->setRelation('deliveryOrders', new Collection([
            new DeliveryOrder(['status' => 'delivered', 'do_number' => 'DO-1']),
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('active delivery orders');

        app(SalesOrderService::class)->cancel($so);
    }

    public function test_assert_editable_blocks_delivered_so(): void
    {
        $so = new SalesOrder(['status' => 'delivered']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        app(SalesOrderService::class)->assertEditable($so);
    }

    public function test_return_full_rejects_invoiced_do(): void
    {
        $do = new DeliveryOrder(['status' => 'invoiced']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Use a credit note');

        app(DeliveryOrderService::class)->returnFull($do);
    }

    public function test_return_full_rejects_already_cancelled(): void
    {
        $do = new DeliveryOrder(['status' => 'cancelled']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already returned');

        app(DeliveryOrderService::class)->returnFull($do);
    }

    public function test_return_full_rejects_when_line_qty_invoiced(): void
    {
        $do = new DeliveryOrder(['status' => 'delivered']);
        $do->setRelation('items', new Collection([
            new DeliveryOrderItem(['quantity' => 2, 'qty_invoiced' => 1]),
        ]));
        $do->setRelation('invoices', new Collection);
        $do->setRelation('salesOrder', null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Use a credit note');

        app(DeliveryOrderService::class)->returnFull($do);
    }

    public function test_assert_editable_blocks_invoiced_do(): void
    {
        $do = new DeliveryOrder(['status' => 'invoiced']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be edited');

        app(DeliveryOrderService::class)->assertEditable($do);
    }
}
