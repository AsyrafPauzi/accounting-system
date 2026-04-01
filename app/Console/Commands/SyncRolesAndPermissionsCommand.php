<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;

/**
 * Re-run Spatie role/permission definitions on the central database and clear
 * permission cache. Fixes 403 when "admin" exists but has no linked permissions,
 * or when permission rows were never seeded.
 */
class SyncRolesAndPermissionsCommand extends Command
{
    protected $signature = 'app:sync-roles-permissions';

    protected $description = 'Seed/sync Spatie roles & permissions (central DB) and reset permission cache';

    public function handle(): int
    {
        $this->info('Syncing roles and permissions on the central connection...');

        $this->call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);

        $this->call('permission:cache-reset');
        $this->info('Permission cache cleared.');

        $this->newLine();
        $this->comment('If you still see 403, log out and back in, or run: php artisan optimize:clear');

        return self::SUCCESS;
    }
}
