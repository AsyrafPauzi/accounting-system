<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Support\AccountingPeriodResolver;
use App\Support\DocumentNumberDefaults;
use App\Support\TaxCodeDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Tenant $tenant) {}

    public function handle(): void
    {
        $this->tenant->update([
            'provision_status' => 'provisioning',
            'provision_error' => null,
        ]);

        try {
            CreateDatabase::dispatchSync($this->tenant);
            MigrateDatabase::dispatchSync($this->tenant);

            tenancy()->initialize($this->tenant);

            try {
                TaxCodeDefaults::seedMissing();
                DocumentNumberDefaults::seedMissing();
                AccountingPeriodResolver::ensurePeriodsExist();
            } finally {
                tenancy()->end();
            }

            $this->tenant->update([
                'provision_status' => 'ready',
                'provision_error' => null,
                'provisioned_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Tenant provisioning failed', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);

            $this->tenant->update([
                'provision_status' => 'failed',
                'provision_error' => $e->getMessage(),
            ]);
        }
    }
}
