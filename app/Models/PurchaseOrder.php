<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'issue_date',
        'expected_date',
        'status',
        'currency',
        'exchange_rate',
        'amount_before_tax',
        'tax_amount',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'    => 'date:Y-m-d',
            'expected_date' => 'date:Y-m-d',
        ];
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('display_order');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
