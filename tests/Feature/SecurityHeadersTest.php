<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * Test that common security headers are present in the response.
     */
    public function test_common_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /**
     * Test that the Content-Security-Policy header is correctly configured for local environment.
     */
    public function test_csp_header_is_correctly_configured_for_local(): void
    {
        // Correctly mock the environment for the application instance
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.env' => 'local']);

        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        
        $this->assertNotNull($csp, 'Content-Security-Policy header is missing.');
        $this->assertStringContainsString("script-src * 'unsafe-inline' 'unsafe-eval'", $csp);
    }

    /**
     * Test that the Content-Security-Policy header is strict in production environment.
     */
    public function test_csp_header_is_strict_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.env' => 'production']);

        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy');
        
        $this->assertNotNull($csp, 'Content-Security-Policy header is missing.');
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("connect-src 'self';", $csp);
        $this->assertStringNotContainsString("ws:", $csp);
    }

    /**
     * Test that information disclosure headers are removed.
     */
    public function test_information_disclosure_headers_are_removed(): void
    {
        $response = $this->get('/');

        $this->assertFalse($response->headers->has('X-Powered-By'), 'The X-Powered-By header should be removed.');
    }
}
