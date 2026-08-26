<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    use Auditable;

    protected $fillable = [
        'credit_note_id',
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

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }
}
