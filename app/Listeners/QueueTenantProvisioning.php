<?php

namespace App\Listeners;

use App\Jobs\ProvisionTenantJob;
use Stancl\Tenancy\Events\TenantCreated;

class QueueTenantProvisioning
{
    public function handle(TenantCreated $event): void
    {
        ProvisionTenantJob::dispatch($event->tenant);
    }
}
