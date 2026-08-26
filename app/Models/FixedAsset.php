<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'asset_number',
        'name',
        'description',
        'purchase_date',
        'cost',
        'salvage_value',
        'useful_life_months',
        'accumulated_depreciation',
        'last_depreciated_month',
        'status',
        'disposed_date',
        'disposal_proceeds',
        'asset_account_code',
        'accum_dep_account_code',
        'dep_expense_account_code',
    ];

    protected $casts = [
        'purchase_date'            => 'date:Y-m-d',
        'cost'                     => 'decimal:2',
        'salvage_value'            => 'decimal:2',
        'useful_life_months'       => 'integer',
        'accumulated_depreciation' => 'decimal:2',
        'last_depreciated_month'   => 'date:Y-m-d',
        'disposed_date'            => 'date:Y-m-d',
        'disposal_proceeds'        => 'decimal:2',
    ];

    public function netBookValue(): float
    {
        return round(max(0, (float) $this->cost - (float) $this->accumulated_depreciation), 2);
    }

    public function monthlyDepreciation(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        $depreciable = max(0, (float) $this->cost - (float) $this->salvage_value);

        return round($depreciable / $this->useful_life_months, 2);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->netBookValue() <= (float) $this->salvage_value + 0.01
            || (float) $this->accumulated_depreciation >= ((float) $this->cost - (float) $this->salvage_value) - 0.01;
    }
}
