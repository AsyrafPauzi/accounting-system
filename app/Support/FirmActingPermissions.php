<?php

namespace App\Support;

use App\Models\Role;

final class FirmActingPermissions
{
    /** @var array<string, list<string>>|null */
    private static ?array $byLevel = null;

    /**
     * Tenant-scoped abilities a firm user may exercise when acting on a
     * client, keyed by FirmClient.permission_level.
     *
     * @return list<string>
     */
    public static function allowedForLevel(string $level): array
    {
        self::bootCache();

        return self::$byLevel[$level] ?? self::$byLevel['viewer'];
    }

    private static function bootCache(): void
    {
        if (self::$byLevel !== null) {
            return;
        }

        self::$byLevel = [
            'viewer' => self::permissionsForRole('viewer'),
            'editor' => self::permissionsForRole('accountant'),
            'admin'  => array_values(array_filter(
                self::permissionsForRole('admin'),
                fn (string $name) => ! str_starts_with($name, 'admin.')
            )),
        ];
    }

    /**
     * @return list<string>
     */
    private static function permissionsForRole(string $roleName): array
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
        if (! $role) {
            return [];
        }

        return $role->permissions()->pluck('name')->all();
    }
}
