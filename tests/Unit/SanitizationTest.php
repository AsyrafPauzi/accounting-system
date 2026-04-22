<?php

namespace Tests\Unit;

use App\Http\Middleware\SanitizeInput;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class SanitizationTest extends TestCase
{
    /**
     * Test that the middleware trims strings but PRESERVES HTML tags.
     */
    public function test_middleware_trims_and_preserves_tags(): void
    {
        $middleware = new SanitizeInput();
        
        $request = new Request();
        $request->setMethod('POST');
        $request->merge([
            'name' => '  Account <Test> Node  ',
            'notes' => 'Preserve <signs> and > symbols',
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertEquals('Account <Test> Node', $req->input('name'));
            $this->assertEquals('Preserve <signs> and > symbols', $req->input('notes'));
            
            return new \Symfony\Component\HttpFoundation\Response();
        });
    }

    /**
     * Test that non-string values are untouched.
     */
    public function test_non_string_values_are_untouched(): void
    {
        $middleware = new SanitizeInput();
        
        $request = new Request();
        $request->setMethod('POST');
        $request->merge([
            'amount' => 123.45,
            'is_active' => true,
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertEquals(123.45, $req->input('amount'));
            $this->assertTrue($req->input('is_active'));
            
            return new \Symfony\Component\HttpFoundation\Response();
        });
    }
}
