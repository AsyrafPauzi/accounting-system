<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

trait ProvisionsTenantDatabase
{
    /**
     * Create a tenant row plus its physical database and run tenant
     * migrations. The tricky bit is that this trait is called from two
     * contexts:
     *
     *   - From {@see \Database\Seeders\DatabaseSeeder} which uses
     *     WithoutModelEvents, suppressing Stancl's TenantCreated listener.
     *     In that case we have to create + migrate the database manually,
     *     because nothing else will.
     *
     *   - From standalone runs like `php artisan db:seed --class=Foo` where
     *     model events DO fire. Stancl's listener creates the database
     *     automatically as part of Tenant::create(); calling CreateDatabase
     *     again would throw TenantDatabaseAlreadyExistsException.
     *
     * Detecting which mode we're in by checking whether the schema already
     * exists is cheaper and more reliable than trying to introspect the
     * dispatcher state, and it makes the trait safe in either context.
     */
    protected function createTenantWithDatabase(string $companyId): Tenant
    {
        $tenant = Tenant::create(['id' => $companyId]);

        if (! $this->tenantDatabaseAlreadyProvisioned($tenant)) {
            CreateDatabase::dispatchSync($tenant);
            MigrateDatabase::dispatchSync($tenant);
        }

        // Seeders often run with WithoutModelEvents, so QueueTenantProvisioning
        // never fires. Mark ready here or the login screen polls forever.
        $tenant->update([
            'provision_status' => 'ready',
            'provision_error' => null,
            'provisioned_at' => now(),
        ]);

        return $tenant;
    }

    private function tenantDatabaseAlreadyProvisioned(Tenant $tenant): bool
    {
        $dbName = $tenant->database()->getName();

        return DB::connection(config('tenancy.database.central_connection', 'mysql'))
            ->selectOne(
                'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
                [$dbName]
            ) !== null;
    }
}
