<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAuditLog extends Model
{
    use SoftDeletes, HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
