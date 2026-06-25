<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureEmailVerifiedForOutbound;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OutboundEmailVerificationMiddlewareTest extends TestCase
{
    public function test_outbound_email_routes_require_verified_sender(): void
    {
        foreach (['invoices.email', 'estimates.email', 'customer-statements.email'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "{$routeName} route should exist.");
            $this->assertContains(
                EnsureEmailVerifiedForOutbound::class,
                $route->gatherMiddleware(),
                "{$routeName} must block outbound email until the sender verifies their email."
            );
        }
    }
}
