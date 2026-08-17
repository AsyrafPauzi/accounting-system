<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'account_code',
        'item_classification',
        'description',
        'quantity',
        'qty_delivered',
        'qty_invoiced',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'amount',
        'display_order',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function qtyOpenToDeliver(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_delivered);
    }

    public function qtyOpenToInvoice(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_invoiced);
    }
}
