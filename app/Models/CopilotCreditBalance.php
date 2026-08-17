<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotCreditBalance extends Model
{
    protected $table = 'copilot_credit_balances';

    protected $fillable = [
        'included_remaining',
        'purchased_remaining',
        'included_quota',
        'period_ym',
        'included_used_this_month',
    ];

    protected $casts = [
        'included_remaining' => 'integer',
        'purchased_remaining' => 'integer',
        'included_quota' => 'integer',
        'included_used_this_month' => 'integer',
    ];

    public function remaining(): int
    {
        return max(0, (int) $this->included_remaining) + max(0, (int) $this->purchased_remaining);
    }
}
