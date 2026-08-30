<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillDocumentVersion extends Model
{
    protected $fillable = [
        'bill_id',
        'slot',
        'path',
        'original_filename',
        'mime',
        'size_bytes',
        'action',
        'reason',
        'uploaded_by',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
