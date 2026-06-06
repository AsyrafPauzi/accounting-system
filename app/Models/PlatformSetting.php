<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform-wide key/value settings.
 *
 * Always lives on the central connection — these settings are
 * cross-tenant by definition (e.g. "latest release version" is one
 * value for the whole product, not per-tenant).
 *
 * Caching strategy: per-request memo only. We deliberately do *not*
 * use the Cache facade because Stancl Tenancy decorates it with
 * tenant tags that the database cache driver can't support, and
 * because these settings are platform-wide — caching them under a
 * tenant key would be wrong anyway. The settings table holds < 10
 * rows total, so a per-request DB hit is cheaper than the cache
 * round-trip.
 */
class PlatformSetting extends Model
{
    use CentralConnection;

    protected $fillable = ['key', 'value'];

    /** @var array<string, ?string>|null */
    private static ?array $memo = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$memo === null) {
            self::$memo = self::query()
                ->get(['key', 'value'])
                ->pluck('value', 'key')
                ->all();
        }
        return self::$memo[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        // Empty string is treated like "unset" so the form can clear a
        // setting without us writing literal '' rows.
        if ($value === '' || $value === null) {
            self::where('key', $key)->delete();
        } else {
            self::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        // Bust the per-request memo so subsequent get() calls in the
        // same request see the new value (e.g. a controller that
        // updates a setting and then renders a page using it).
        self::$memo = null;
    }

    public static function asArray(): array
    {
        return self::query()
            ->get(['key', 'value'])
            ->pluck('value', 'key')
            ->all();
    }
}
