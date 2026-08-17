<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteApplication extends Model
{
    protected $fillable = [
        'credit_note_id',
        'invoice_id',
        'amount',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
