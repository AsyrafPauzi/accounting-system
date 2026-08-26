<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPortalToken;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CustomerPortalService
{
    public function issueToken(Customer $customer, ?int $createdBy = null, int $daysValid = 90): CustomerPortalToken
    {
        return CustomerPortalToken::create([
            'customer_id' => $customer->id,
            'token'       => Str::random(48),
            'expires_at'  => now()->addDays($daysValid),
            'created_by'  => $createdBy,
        ]);
    }

    public function findValidToken(string $token): ?CustomerPortalToken
    {
        $row = CustomerPortalToken::query()->where('token', $token)->first();
        if (! $row || ! $row->isValid()) {
            return null;
        }

        $row->update(['last_used_at' => now()]);

        return $row->fresh(['customer']);
    }

    public function signedDashboardUrl(CustomerPortalToken $portalToken): string
    {
        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;

        return URL::temporarySignedRoute(
            'portal.dashboard',
            $portalToken->expires_at,
            ['token' => $portalToken->token, 'tenant_id' => $tenantId],
        );
    }

    public function urlForCustomer(Customer $customer, ?int $createdBy = null): ?string
    {
        $existing = CustomerPortalToken::query()
            ->where('customer_id', $customer->id)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        $portalToken = $existing ?? $this->issueToken($customer, $createdBy);

        return $this->signedDashboardUrl($portalToken);
    }
}
