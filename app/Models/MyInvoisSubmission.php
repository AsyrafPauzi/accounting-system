<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyInvoisSubmission extends Model
{
    protected $table = 'myinvois_submissions';

    protected $fillable = [
        'document_type',
        'document_id',
        'request_json',
        'response_json',
        'http_status',
        'lhdn_uuid',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'request_json'  => 'array',
            'response_json' => 'array',
            'submitted_at'  => 'datetime',
        ];
    }
}
