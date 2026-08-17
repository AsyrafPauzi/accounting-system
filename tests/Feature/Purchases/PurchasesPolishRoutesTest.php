<?php

namespace Tests\Feature\Purchases;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PurchasesPolishRoutesTest extends TestCase
{
    public function test_purchases_polish_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('bills.batch'));
        $this->assertTrue(Route::has('bills.batch.store'));
        $this->assertTrue(Route::has('purchase-orders.edit'));
        $this->assertTrue(Route::has('purchase-orders.update'));
        $this->assertTrue(Route::has('purchase-orders.cancel'));
        $this->assertTrue(Route::has('purchase-orders.email'));
        $this->assertTrue(Route::has('goods-receipts.update'));
        $this->assertTrue(Route::has('goods-receipts.return'));
        $this->assertTrue(Route::has('goods-receipts.email'));
    }

    public function test_purchase_order_and_grn_email_use_verify_middleware(): void
    {
        foreach (['purchase-orders.email', 'goods-receipts.email'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name.' should exist');
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => is_string($m) && str_contains($m, 'EnsureEmailVerifiedForOutbound')),
                $name.' must require verified email for outbound mail.'
            );
        }
    }
}
