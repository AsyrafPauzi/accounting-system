<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotMessage extends Model
{
    protected $fillable = ['copilot_thread_id', 'role', 'content', 'tool_traces'];

    protected $casts = [
        'tool_traces' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CopilotThread::class, 'copilot_thread_id');
    }
}
