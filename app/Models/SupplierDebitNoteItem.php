<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDebitNoteItem extends Model
{
    protected $fillable = [
        'supplier_debit_note_id',
        'account_code',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'amount',
    ];

    public function debitNote()
    {
        return $this->belongsTo(SupplierDebitNote::class, 'supplier_debit_note_id');
    }
}
