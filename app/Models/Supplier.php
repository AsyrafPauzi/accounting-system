<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'name', 'code', 'contact_person', 'phone', 'email', 'tin', 'brn',
        'identification_type', 'sst_number',
        'payment_terms', 'currency', 'billing_street', 'billing_city', 'billing_state',
        'billing_zip', 'billing_country', 'website', 'region', 'segment',
        'is_active', 'internal_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Sum of (total_amount - amount_paid) for non-draft, non-void bills.
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->bills()
            ->whereNotIn('status', ['draft', 'void'])
            ->sum(DB::raw('total_amount - amount_paid'));
    }
}
