<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCreditNoteRefund extends Model
{
    protected $fillable = [
        'supplier_credit_note_id',
        'amount',
        'payment_date',
        'bank_account_code',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount'       => 'decimal:2',
        ];
    }

    public function creditNote()
    {
        return $this->belongsTo(SupplierCreditNote::class, 'supplier_credit_note_id');
    }
}
