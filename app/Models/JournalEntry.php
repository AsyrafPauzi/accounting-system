<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    //
    protected $fillable = ['date', 'description', 'reference_type', 'reference_id'];
}
