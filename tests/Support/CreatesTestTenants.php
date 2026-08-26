<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Create Stancl tenants with a real SQLite database file + migrations.
 *
 * Many feature tests only need a central `tenants` row (use
 * Tenant::withoutEvents + forceCreate for that). Use this trait when
 * the test makes HTTP requests as an SME user (`users.tenant_id` set)
 * or otherwise initialises tenancy — InitializeTenancyByLoggedInUser
 * will try to open the tenant connection and Laravel will throw
 * SQLiteDatabaseDoesNotExistException if the file was never created.
 */
trait CreatesTestTenants
{
    protected function createTenantWithDatabase(?string $id = null): Tenant
    {
        $id ??= 't-'.Str::lower(Str::random(10));

        $this->deleteTenantDatabaseFile($id);

        return Tenant::create(['id' => $id]);
    }

    protected function deleteTenantDatabaseFile(string $tenantId): void
    {
        $prefix = (string) config('tenancy.database.prefix', 'tenant');
        $suffix = (string) config('tenancy.database.suffix', '');
        $path = database_path($prefix.$tenantId.$suffix);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
