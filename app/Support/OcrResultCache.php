<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Tenant-safe OCR poll cache.
 *
 * Stancl's CacheTenancyBootstrapper wraps Cache::has/get/put with tags.
 * Production uses CACHE_STORE=database, which does not support tagging, so
 * those facade calls 500. HandleInertiaRequests / OcrSettings already avoid
 * the Cache facade for the same reason.
 *
 * CacheManager::driver() is a real method (not __call), so it returns the
 * underlying untagged store. Tenant isolation is the key prefix instead.
 */
class OcrResultCache
{
    public static function put(string $path, array $result, int $minutes = 15): void
    {
        self::store()->put(self::key($path), $result, now()->addMinutes($minutes));
    }

    public static function get(string $path): ?array
    {
        $value = self::store()->get(self::key($path));

        return is_array($value) ? $value : null;
    }

    public static function key(string $path): string
    {
        $tenantId = (function_exists('tenant') && tenant()) ? (string) tenant('id') : 'central';

        return 'ocr-result:'.$tenantId.':'.$path;
    }

    private static function store(): Repository
    {
        return Cache::getFacadeRoot()->driver();
    }
}
