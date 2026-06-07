<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;

/**
 * Re-run subscription plan definitions and plan→permission mappings on the
 * central database. Safe to run on every deploy; uses updateOrCreate so code
 * remains the source of truth for tiers, pricing, and feature gates.
 */
class SyncPlansCommand extends Command
{
    protected $signature = 'app:sync-plans';

    protected $description = 'Seed/sync subscription plans (central DB) and reset permission cache';

    public function handle(): int
    {
        $this->info('Syncing subscription plans on the central connection...');

        $this->call('db:seed', [
            '--class' => PlanSeeder::class,
            '--force' => true,
        ]);

        $this->call('permission:cache-reset');
        $this->info('Permission cache cleared.');

        return self::SUCCESS;
    }
}
