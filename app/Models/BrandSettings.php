<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BrandSettings extends Model
{
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

    public const CACHE_KEY = 'brand_settings.current';

    /**
     * Always returns the single config row, creating it if missing.
     * Cached forever; flushed via flushCache() on save.
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->firstOrCreate(['id' => 1]);
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
