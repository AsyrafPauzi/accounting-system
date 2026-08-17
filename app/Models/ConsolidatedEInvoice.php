<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ConsolidatedEInvoice extends Model
{
    use HasUuid;

    protected $fillable = [
        'document_number',
        'period_from',
        'period_to',
        'total_amount',
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
            'period_from'       => 'date',
            'period_to'         => 'date',
            'lhdn_submitted_at' => 'datetime',
            'lhdn_cancelled_at' => 'datetime',
        ];
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'consolidated_e_invoice_items');
    }
}
