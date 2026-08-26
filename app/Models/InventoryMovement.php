<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'unit_cost',
        'reference_type',
        'reference_id',
        'movement_date',
    ];

    protected $casts = [
        'qty'           => 'decimal:4',
        'unit_cost'     => 'decimal:4',
        'movement_date' => 'date:Y-m-d',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
