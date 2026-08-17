<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'account_code',
        'description',
        'quantity',
        'qty_received',
        'qty_billed',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'amount',
        'display_order',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function qtyOpenToReceive(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_received);
    }

    public function qtyOpenToBill(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_billed);
    }
}
