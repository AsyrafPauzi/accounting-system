<?php

namespace App\Support;

use App\Models\DocumentNumberSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentNumber
{
    /**
     * Next sequential document number for a PREFIX series.
     *
     * When `document_number_settings` exists for the doc type, uses tenant
     * prefix + atomic next counter. Otherwise scans existing numbers.
     */
    public static function next(string $table, string $column, string $prefix): string
    {
        $docType = self::resolveDocType($table, $column);

        if ($docType && Schema::hasTable('document_number_settings')) {
            DocumentNumberDefaults::seedMissing();
            $fromSettings = self::nextFromSettings($docType, $table, $column);
            if ($fromSettings !== null) {
                return $fromSettings;
            }
        }

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

    private static function resolveDocType(string $table, string $column): ?string
    {
        foreach (DocumentNumberSetting::docTypeCatalog() as $type => $meta) {
            if ($meta['table'] === $table && $meta['column'] === $column) {
                return $type;
            }
        }

        return null;
    }

    private static function nextFromSettings(string $docType, string $table, string $column): ?string
    {
        return DB::transaction(function () use ($docType, $table, $column) {
            $setting = DocumentNumberSetting::query()
                ->where('doc_type', $docType)
                ->lockForUpdate()
                ->first();

            if (! $setting) {
                return null;
            }

            if ($setting->reset_on_fy) {
                $fyStartYear = self::currentFyStartYear();
                if ($setting->last_fy_start_year !== null && $setting->last_fy_start_year !== $fyStartYear) {
                    $setting->next_number = 1;
                }
                $setting->last_fy_start_year = $fyStartYear;
            }

            $number = (int) $setting->next_number;
            $candidate = self::format($setting->prefix, $number, (int) $setting->pad_width);

            while (self::numberExists($table, $column, $candidate)) {
                $number++;
                $candidate = self::format($setting->prefix, $number, (int) $setting->pad_width);
            }

            $setting->next_number = $number + 1;
            $setting->save();

            return $candidate;
        });
    }

    private static function format(string $prefix, int $number, int $width): string
    {
        return strtoupper(trim($prefix)).'-'.str_pad((string) $number, max(2, $width), '0', STR_PAD_LEFT);
    }

    private static function numberExists(string $table, string $column, string $number): bool
    {
        $query = DB::table($table)->where($column, $number);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private static function currentFyStartYear(): int
    {
        $month = (int) (function_exists('tenant') && tenant() ? (tenant()->financial_year_start_month ?? 1) : 1);
        $today = Carbon::today();
        $year = (int) $today->year;
        if ((int) $today->month < $month) {
            $year--;
        }

        return $year;
    }
}
