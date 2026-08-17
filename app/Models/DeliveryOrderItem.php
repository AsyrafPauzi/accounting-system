<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'sales_order_item_id',
        'product_id',
        'description',
        'quantity',
        'qty_invoiced',
        'display_order',
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function qtyOpenToInvoice(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_invoiced);
    }
}
