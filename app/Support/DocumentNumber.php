<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentNumber
{
    /**
     * Next sequential document number for a PREFIX series.
     *
     * Demo / labelled numbers (INV-DEMO-0008, INV-OVERDUE-001) still
     * count: we take the highest trailing integer among PREFIX-* and
     * emit PREFIX-0009 so live documents do not inherit seeder tags.
     */
    public static function next(string $table, string $column, string $prefix): string
    {
        $query = DB::table($table)->where($column, 'like', $prefix.'-%');
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return self::nextFromList($query->pluck($column)->all(), $prefix);
    }

    /**
     * @param  list<string|null>  $numbers
     */
    public static function nextFromList(array $numbers, string $prefix): string
    {
        $max = 0;
        $width = 4;

        foreach ($numbers as $number) {
            if (preg_match('/(\d+)$/', (string) $number, $m)) {
                $n = (int) $m[1];
                if ($n >= $max) {
                    $max = $n;
                    $width = max(4, strlen($m[1]));
                }
            }
        }

        return $prefix.'-'.str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT);
    }
}
