<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebitNote extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'dn_number',
        'issue_date',
        'reason_code',
        'reason_description',
        'amount_before_tax',
        'tax_amount',
        'total_amount',
        'currency',
        'exchange_rate',
        'customer_notes',
        'status',
        'lhdn_status',
        'lhdn_uuid',
        'lhdn_long_id',
        'lhdn_submitted_at',
        'lhdn_cancelled_at',
        'lhdn_reject_reason',
        'lhdn_qr_url',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'         => 'date',
            'lhdn_submitted_at'  => 'datetime',
            'lhdn_cancelled_at'  => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(DebitNoteItem::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
