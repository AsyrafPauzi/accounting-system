<?php

namespace Tests\Feature\Licensing;

use App\Models\Permission;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pins the contract for the "Email estimates" Solo+ bullet:
 *   - The route surface (estimates.pdf / estimates.email /
 *     public.estimates.download) is wired.
 *   - The middleware stack on estimates.email gates by both Spatie
 *     permission AND plan permission so a higher-permission user on
 *     Startup can't bypass the bullet.
 *   - The `estimates.email` permission exists in the DB.
 */
class EstimateEmailRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    public function test_estimate_pdf_email_and_public_download_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('estimates.pdf'),
            'GET /estimates/{id}/pdf must be wired so users can download a PDF copy.');
        $this->assertTrue(Route::has('estimates.email'),
            'POST /estimates/{id}/email must be wired so Solo+ users can email customers.');
        $this->assertTrue(Route::has('public.estimates.download'),
            'GET /public/estimates/{uuid}/download must be wired so the email link works.');
    }

    public function test_estimates_email_route_is_gated_by_permission_and_plan_permission(): void
    {
        $route = Route::getRoutes()->getByName('estimates.email');
        $this->assertNotNull($route, 'estimates.email route should exist');

        $middleware = $route->gatherMiddleware();
        $this->assertContains(
            'permission:estimates.email',
            $middleware,
            'estimates.email must enforce the Spatie permission so revoked staff can\'t hit it.'
        );
        $this->assertContains(
            'plan.permission:estimates.email',
            $middleware,
            'estimates.email must enforce the plan-level permission so Startup users can\'t bypass the Solo+ bullet via direct POST.'
        );
    }

    public function test_public_estimates_download_route_requires_signed_url(): void
    {
        $route = Route::getRoutes()->getByName('public.estimates.download');
        $this->assertNotNull($route);

        // Public download must be signed — the URL is the auth, so an
        // attacker enumerating UUIDs without a signature should get 403.
        $this->assertContains('signed', $route->gatherMiddleware());
    }

    public function test_estimates_email_permission_exists_in_central_registry(): void
    {
        $this->assertTrue(
            Permission::where('name', 'estimates.email')->where('guard_name', 'web')->exists(),
            'estimates.email permission must be registered so Spatie can grant / check it.'
        );
    }
}
