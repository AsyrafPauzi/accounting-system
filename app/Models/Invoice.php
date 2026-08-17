<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'invoice_number', 
        'msic_code',       // ADD THIS
        'customer_id', 
        'amount_before_tax', 
        'discount_total',  // ADD THIS
        'tax_amount', 
        'total_amount', 
        'amount_paid',
        'status', 
        'issue_date',
        'due_date',
        'currency',
        'exchange_rate',
        'shipping_amount',
        'rounding_adjustment',
        'customer_notes',
        'show_signature',
        'created_by',
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
        'last_viewed_at',
        'view_count',
        'last_reminded_at',
        'reminder_stage',
        'reminder_overrides',
        'is_cash_sale',
        'payment_terms_days',
        'sales_order_id',
        'delivery_order_id',
        'estimate_id',
        'source_invoice_id',
        'is_late_fee',
        'is_consolidated',
        'consolidated_e_invoice_id',
        'toyyibpay_bill_code',
        'pay_now_provider',
        'pay_now_reference',
    ];

    protected function casts(): array
    {
        return [
            'show_signature'      => 'boolean',
            'is_cash_sale'        => 'boolean',
            'is_consolidated'     => 'boolean',
            'is_late_fee'         => 'boolean',
            'reminder_overrides'  => 'array',
            'last_viewed_at'      => 'datetime',
            'last_reminded_at'    => 'datetime',
            'lhdn_submitted_at'   => 'datetime',
            'lhdn_cancelled_at'   => 'datetime',
            'issue_date'          => 'date',
            'due_date'            => 'date',
        ];
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function attachments()
    {
        return $this->hasMany(InvoiceAttachment::class);
    }

    public function creditNoteApplications()
    {
        return $this->hasMany(CreditNoteApplication::class);
    }
}