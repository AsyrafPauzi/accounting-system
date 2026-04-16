<?php

namespace Tests\Unit;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\User;
use Tests\TestCase;
use Mockery;

class RequestAuthorizationTest extends TestCase
{
    /**
     * Test StoreInvoiceRequest denies access without permission.
     */
    public function test_store_invoice_request_denies_unauthorized(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('invoices.create')->andReturn(false);

        $request = new StoreInvoiceRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    /**
     * Test StoreInvoiceRequest allows access with permission.
     */
    public function test_store_invoice_request_allows_authorized(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('invoices.create')->andReturn(true);

        $request = new StoreInvoiceRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    /**
     * Test StoreCustomerRequest denies access without permission.
     */
    public function test_store_customer_request_denies_unauthorized(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('customers.create')->andReturn(false);

        $request = new StoreCustomerRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    /**
     * Test StoreCustomerRequest allows access with permission.
     */
    public function test_store_customer_request_allows_authorized(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('customers.create')->andReturn(true);

        $request = new StoreCustomerRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
