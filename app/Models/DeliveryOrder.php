<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    public const STATUSES = ['draft', 'delivered', 'invoiced', 'cancelled'];

    protected $fillable = [
        'do_number',
        'customer_id',
        'sales_order_id',
        'issue_date',
        'delivery_date',
        'status',
        'currency',
        'customer_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'     => 'date',
            'delivery_date'  => 'date',
        ];
    }

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class)->orderBy('display_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Lines for shared sales-document PDF (prices come from linked SO items).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function pdfLineItems()
    {
        $this->loadMissing(['items', 'salesOrder.items']);

        return $this->items->map(function (DeliveryOrderItem $line) {
            $soItem = $this->salesOrder?->items->firstWhere('id', $line->sales_order_item_id);
            $qty = (float) $line->quantity;
            $price = (float) ($soItem?->unit_price ?? 0);
            $taxRate = (float) ($soItem?->tax_rate ?? 0);

            return (object) [
                'description' => $line->description,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'tax_rate'    => $taxRate,
                'amount'      => round($qty * $price, 2),
            ];
        });
    }
}
