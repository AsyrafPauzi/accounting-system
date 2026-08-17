<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    public const STATUSES = ['draft', 'confirmed', 'partially_delivered', 'delivered', 'invoiced', 'cancelled'];

    protected $fillable = [
        'so_number',
        'customer_id',
        'estimate_id',
        'issue_date',
        'expected_date',
        'status',
        'currency',
        'exchange_rate',
        'amount_before_tax',
        'discount_total',
        'tax_amount',
        'shipping_amount',
        'rounding_adjustment',
        'total_amount',
        'customer_notes',
        'private_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'    => 'date',
            'expected_date' => 'date',
        ];
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('display_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
