<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNoteApplication extends Model
{
    protected $fillable = [
        'supplier_credit_note_id',
        'bill_id',
        'amount',
    ];

    public function creditNote()
    {
        return $this->belongsTo(SupplierCreditNote::class, 'supplier_credit_note_id');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
