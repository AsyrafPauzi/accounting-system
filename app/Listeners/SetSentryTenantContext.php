<?php

namespace App\Listeners;

use Stancl\Tenancy\Events\TenancyInitialized;

class SetSentryTenantContext
{
    public function handle(TenancyInitialized $event): void
    {
        if (! filled(config('sentry.dsn'))) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
            $scope->setTag('tenant_id', (string) tenant('id'));

            if ($user = auth()->user()) {
                $scope->setUser(['id' => (string) $user->getAuthIdentifier()]);
            }
        });
    }
}
