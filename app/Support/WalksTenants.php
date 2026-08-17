<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

trait WalksTenants
{
    /**
     * @param  callable(Tenant): void  $callback
     */
    protected function forEachTenant(Command $command, callable $callback, ?string $requiredTable = null): int
    {
        $ids = $command->option('tenants');
        $tenants = $ids
            ? Tenant::query()->whereIn('id', $ids)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $command->warn('No tenants matched.');

            return Command::FAILURE;
        }

        $errors = 0;
        foreach ($tenants as $tenant) {
            $command->info("Tenant: {$tenant->id}");
            tenancy()->initialize($tenant);
            try {
                if ($requiredTable && ! Schema::hasTable($requiredTable)) {
                    $command->line("  <comment>{$requiredTable} missing — skip</comment>");
                    continue;
                }
                $callback($tenant);
            } catch (\Throwable $e) {
                $errors++;
                $command->error('  Failed: '.$e->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
