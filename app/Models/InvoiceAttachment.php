<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAttachment extends Model
{
    protected $fillable = [
        'invoice_id',
        'original_name',
        'path',
        'mime',
        'size_bytes',
        'uploaded_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
