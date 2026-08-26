<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes, HasUuid, Auditable;
    protected $fillable = [
        'invoice_id',
        'product_id',
        'account_code',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_code_id',
        'discount_amount',
        'item_classification',
        'amount',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}