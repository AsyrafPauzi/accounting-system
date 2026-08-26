<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable invoice / estimate line item.
 *
 * Soft-deleted rather than hard-deleted because old invoices may reference
 * a product_id (Phase 2). Keeping the row but marking it inactive preserves
 * history while removing the product from create-form pickers.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_price',
        'account_code',
        'tax_rate',
        'classification_code',
        'is_active',
        'track_inventory',
        'qty_on_hand',
        'avg_cost',
        'display_order',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'tax_rate'      => 'decimal:2',
        'is_active'     => 'boolean',
        'track_inventory' => 'boolean',
        'qty_on_hand'   => 'decimal:4',
        'avg_cost'      => 'decimal:4',
        'display_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Eloquent relation to the default revenue account, joined on `code`.
     * Returns null if the account was renamed or deleted from the COA.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_code', 'code');
    }
}
