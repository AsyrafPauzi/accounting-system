<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNoteItem extends Model
{
    protected $fillable = [
        'supplier_credit_note_id',
        'account_code',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'amount',
    ];

    public function creditNote()
    {
        return $this->belongsTo(SupplierCreditNote::class, 'supplier_credit_note_id');
    }
}
