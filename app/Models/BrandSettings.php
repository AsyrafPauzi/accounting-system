<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class BrandSettings extends Model
{
    // Pin queries to the central DB even when a tenant is initialized — this
    // table lives only in the central database (one row, platform-wide settings).
    use CentralConnection;

    protected $table = 'brand_settings';

    protected $fillable = [
        'product_name',
        'product_tagline',
        'logo_path',
        'favicon_path',
        'color_terracotta',
        'color_forest',
        'color_mustard',
    ];

    /**
     * Per-request memo. We deliberately don't use the Cache facade because
     * Stancl Tenancy wraps it with a tag-based store wrapper, and the
     * database cache driver doesn't support tagging. Reading one row from
     * the central DB once per request is cheap.
     */
    protected static ?self $memo = null;

    /**
     * Always returns the single config row, creating it if missing.
     */
    public static function current(): self
    {
        if (static::$memo) {
            return static::$memo;
        }

        $row = static::query()->find(1);
        if (! $row) {
            // Avoid mass-assigning `id` (not in $fillable). Create the row by hand.
            $row = new self();
            $row->id = 1;
            $row->save();
            $row->refresh();
        }

        return static::$memo = $row;
    }

    public static function flushCache(): void
    {
        static::$memo = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
