<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'bank_account_code',
        'reference',
        'created_by',
        'reversed_at',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount'       => 'decimal:2',
            'reversed_at'  => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
