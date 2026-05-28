<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'code',
        'name',
        'type',
        'sub_type',
        'parent_id',
        'description',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Asset accounts that hold cash/bank balances (used for receipt + payment dropdowns).
     */
    public function scopeBankOrCash(Builder $query): Builder
    {
        return $query->where('type', 'asset')->whereIn('sub_type', ['bank', 'cash']);
    }

    /**
     * Display label for account type.
     */
    public static function getTypeLabel(?string $type): string
    {
        return match ($type) {
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'income' => 'Revenue',
            'expense' => 'Expense',
            default => (string) $type,
        };
    }

    /**
     * Display label for account sub-type (currently only meaningful for asset accounts).
     */
    public static function getSubTypeLabel(?string $subType): string
    {
        return match ($subType) {
            'bank' => 'Bank',
            'cash' => 'Cash',
            null, '' => '',
            default => (string) $subType,
        };
    }

    /**
     * Sub-type choices that are valid given an account `type`.
     * Returns an empty array when the parent type has no sub-types defined.
     */
    public static function subTypeOptionsFor(?string $type): array
    {
        return match ($type) {
            'asset' => [
                ['value' => 'bank', 'label' => 'Bank'],
                ['value' => 'cash', 'label' => 'Cash'],
            ],
            default => [],
        };
    }
}
