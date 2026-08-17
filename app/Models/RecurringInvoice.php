<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recurring invoice template + schedule.
 *
 * Materialises into actual `invoices` records on a daily cron. Each generated
 * invoice is a fresh draft and only debits the GL when the user posts it.
 */
class RecurringInvoice extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    /** @var list<string> */
    public const CADENCES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'name',
        'customer_id',
        'cadence',
        'interval',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'last_generated_invoice_id',
        'generated_count',
        'is_active',
        'currency',
        'exchange_rate',
        'shipping_amount',
        'payment_terms_days',
        'msic_code',
        'customer_notes',
        'private_notes',
        'auto_email',
        'auto_post',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'date',
            'end_date'          => 'date',
            'next_run_date'     => 'date',
            'last_run_date'     => 'date',
            'is_active'         => 'boolean',
            'auto_email'        => 'boolean',
            'auto_post'         => 'boolean',
            'interval'          => 'integer',
            'generated_count'   => 'integer',
            'payment_terms_days'=> 'integer',
            'shipping_amount'   => 'decimal:2',
            'exchange_rate'     => 'decimal:6',
        ];
    }

    public function items()
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('display_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lastGeneratedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'last_generated_invoice_id');
    }

    /**
     * Templates that are due to run today (or earlier).
     * Used by the daily cron to find what to materialise.
     */
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

    /**
     * Compute the date for the *next* run after a given anchor, given the
     * template's cadence + interval.
     *
     * Examples:
     *   cadence=monthly,  interval=1, anchor=2026-01-15 → 2026-02-15
     *   cadence=quarterly,interval=1, anchor=2026-01-15 → 2026-04-15
     *   cadence=weekly,   interval=2, anchor=2026-01-01 → 2026-01-15
     */
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

    /**
     * Human-readable cadence summary, e.g. "Every month" or "Every 2 weeks".
     */
    public function getCadenceLabelAttribute(): string
    {
        $interval = max(1, (int) $this->interval);
        $unit = match ($this->cadence) {
            'weekly'    => $interval === 1 ? 'week' : 'weeks',
            'monthly'   => $interval === 1 ? 'month' : 'months',
            'quarterly' => $interval === 1 ? 'quarter' : 'quarters',
            'yearly'    => $interval === 1 ? 'year' : 'years',
            default     => 'cycles',
        };

        return $interval === 1 ? "Every {$unit}" : "Every {$interval} {$unit}";
    }
}
