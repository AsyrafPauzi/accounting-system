<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'description',
        'quantity',
        'qty_billed',
        'display_order',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function qtyOpenToBill(): float
    {
        return max(0, (float) $this->quantity - (float) $this->qty_billed);
    }
}
