<?php

namespace Tests\Feature\Copilot;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CopilotRoutesTest extends TestCase
{
    public function test_copilot_routes_are_gated_by_permission_and_plan(): void
    {
        $this->assertTrue(Route::has('copilot.show'));
        $this->assertTrue(Route::has('copilot.chat'));
        $this->assertTrue(Route::has('copilot.confirm'));
        $this->assertTrue(Route::has('copilot.cancel'));
        $this->assertTrue(Route::has('copilot.clear'));

        foreach (['copilot.show', 'copilot.chat', 'copilot.confirm', 'copilot.cancel', 'copilot.clear'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('permission:copilot.use', $middleware);
            $this->assertContains('plan.permission:copilot.use', $middleware);
        }
    }

    public function test_statement_pdf_tool_is_read_not_email(): void
    {
        $this->assertSame(\App\Services\Copilot\CopilotCatalog::RISK_READ, \App\Services\Copilot\CopilotCatalog::risk('download_customer_statement_pdf'));
        $this->assertSame(\App\Services\Copilot\CopilotCatalog::RISK_HIGH, \App\Services\Copilot\CopilotCatalog::risk('email_customer_statement'));
        $this->assertArrayHasKey('download_customer_statement_pdf', \App\Services\Copilot\CopilotCatalog::tools());
    }
}
