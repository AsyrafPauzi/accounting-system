<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebitNoteItem extends Model
{
    protected $fillable = [
        'debit_note_id',
        'product_id',
        'account_code',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'item_classification',
        'amount',
    ];

    public function debitNote()
    {
        return $this->belongsTo(DebitNote::class);
    }
}
