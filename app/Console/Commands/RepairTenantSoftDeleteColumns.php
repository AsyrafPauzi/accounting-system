<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds deleted_at when missing so Eloquent SoftDeletes does not break queries.
 * Use when migrate says "nothing to run" but the DB predates soft-delete migrations.
 */
class RepairTenantSoftDeleteColumns extends Command
{
    protected $signature = 'tenants:repair-soft-deletes {--tenants=* : Tenant id(s); omit for all tenants}';

    protected $description = 'Add missing deleted_at columns on tenant tables (SoftDeletes)';

    /** @var list<string> */
    private array $tables = [
        'invoices',
        'invoice_items',
        'customers',
        'customer_contacts',
        'customer_audit_logs',
        'bills',
        'bill_items',
        'suppliers',
        'accounts',
        'credit_notes',
        'journal_entries',
        'journal_items',
    ];

    public function handle(): int
    {
        $ids = $this->option('tenants');
        $tenants = $ids
            ? Tenant::query()->whereIn('id', $ids)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched. Use php artisan tenants:list to see ids.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            tenancy()->initialize($tenant);

            try {
                foreach ($this->tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                    if (Schema::hasColumn($table, 'deleted_at')) {
                        continue;
                    }
                    Schema::table($table, function (Blueprint $blueprint) {
                        $blueprint->softDeletes();
                    });
                    $this->line("  <fg=green>+ deleted_at</> on <comment>{$table}</comment>");
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info('Done. Prefer running php artisan tenants:migrate on new environments so migrations stay in sync.');

        return self::SUCCESS;
    }
}
