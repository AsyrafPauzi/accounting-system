<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringBill extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    public const CADENCES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'name',
        'supplier_id',
        'cadence',
        'interval',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'last_generated_bill_id',
        'generated_count',
        'is_active',
        'auto_post',
        'payment_terms_days',
        'tax_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'         => 'date:Y-m-d',
            'end_date'           => 'date:Y-m-d',
            'next_run_date'      => 'date:Y-m-d',
            'last_run_date'      => 'date:Y-m-d',
            'is_active'          => 'boolean',
            'auto_post'          => 'boolean',
            'interval'           => 'integer',
            'generated_count'    => 'integer',
            'payment_terms_days' => 'integer',
            'tax_amount'         => 'decimal:2',
        ];
    }

    public function items()
    {
        return $this->hasMany(RecurringBillItem::class)->orderBy('sort_order');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeDue(Builder $query, ?Carbon $asOf = null): Builder
    {
        $asOf = $asOf ?? now();

        return $query
            ->where('is_active', true)
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $asOf->toDateString());
            });
    }

    public function advanceFrom(Carbon $anchor): Carbon
    {
        $interval = max(1, (int) $this->interval);

        return match ($this->cadence) {
            'weekly'    => $anchor->copy()->addWeeks($interval),
            'monthly'   => $anchor->copy()->addMonthsNoOverflow($interval),
            'quarterly' => $anchor->copy()->addMonthsNoOverflow($interval * 3),
            'yearly'    => $anchor->copy()->addYearsNoOverflow($interval),
            default     => $anchor->copy()->addMonthsNoOverflow(1),
        };
    }
}
