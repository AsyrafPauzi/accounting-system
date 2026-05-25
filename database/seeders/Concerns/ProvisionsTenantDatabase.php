<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

trait ProvisionsTenantDatabase
{
    /**
     * DatabaseSeeder uses WithoutModelEvents, so TenantCreated does not fire during seeding.
     * Create the tenant row and its MySQL database + migrations explicitly.
     */
    protected function createTenantWithDatabase(string $companyId): Tenant
    {
        $tenant = Tenant::create(['id' => $companyId]);

        CreateDatabase::dispatchSync($tenant);
        MigrateDatabase::dispatchSync($tenant);

        return $tenant;
    }
}
