<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use App\Services\CreditNoteService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'cn_number',
        'issue_date',
        'reason_code',
        'reason_description',
        'amount_before_tax',
        'tax_amount',
        'total_amount',
        'applied_amount',
        'refunded_amount',
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
        'last_emailed_at',
        'last_emailed_to',
        'last_emailed_status',
        'last_emailed_error',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'        => 'date',
            'lhdn_submitted_at' => 'datetime',
            'lhdn_cancelled_at' => 'datetime',
            'last_emailed_at'   => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function applications()
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    public function refunds()
    {
        return $this->hasMany(CreditNoteRefund::class);
    }

    public function openAmount(): float
    {
        return CreditNoteService::unappliedAmount(
            (float) $this->total_amount,
            (float) $this->applied_amount,
            (float) ($this->refunded_amount ?? 0)
        );
    }
}