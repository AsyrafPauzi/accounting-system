<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalItem extends Model
{
    //
    protected $fillable = ['journal_entry_id', 'account_code', 'debit', 'credit'];
}
