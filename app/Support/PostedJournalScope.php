<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PostedJournalScope
{
    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, string $alias = 'journal_entries'): void
    {
        $query->where("{$alias}.status", 'posted');
    }
}
