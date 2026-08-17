<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotCreditLedger extends Model
{
    protected $table = 'copilot_credit_ledger';

    public const TYPE_GRANT_INCLUDED = 'grant_included';
    public const TYPE_BURN = 'burn';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'type',
        'delta_included',
        'delta_purchased',
        'user_id',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'delta_included' => 'integer',
        'delta_purchased' => 'integer',
    ];
}
