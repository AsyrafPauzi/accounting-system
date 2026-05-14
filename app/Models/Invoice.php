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
        'last_emailed_at',
        'last_emailed_to',
        'last_emailed_status',
        'last_emailed_error',
    ];

    protected function casts(): array
    {
        return [
            'show_signature' => 'boolean',
        ];
    }

    // This allows $invoice->items()->create() to work
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}