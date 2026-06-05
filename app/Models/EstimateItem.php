<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EstimateItem extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'estimate_id',
        'product_id',
        'item_classification',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'amount',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'decimal:2',
            'unit_price'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate'        => 'decimal:2',
            'amount'          => 'decimal:2',
        ];
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
