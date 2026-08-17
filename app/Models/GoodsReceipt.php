<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'grn_number',
        'supplier_id',
        'purchase_order_id',
        'issue_date',
        'received_date',
        'status',
        'currency',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'    => 'date:Y-m-d',
            'received_date' => 'date:Y-m-d',
        ];
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('display_order');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
