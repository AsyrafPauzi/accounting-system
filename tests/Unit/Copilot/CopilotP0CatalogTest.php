<?php

namespace Tests\Unit\Copilot;

use App\Services\Copilot\CopilotCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CopilotP0CatalogTest extends TestCase
{
    /**
     * @return list<list<string>>
     */
    public static function p0ToolNames(): array
    {
        return [
            ['draft_owner_expense_claim'],
            ['list_team_members'],
            ['invite_team_member'],
            ['show_sales_order'],
            ['show_delivery_order'],
            ['list_ar_deposits'],
            ['deliver_sales_order'],
            ['cancel_sales_order'],
            ['convert_sales_order_to_invoice'],
            ['convert_delivery_order_to_invoice'],
            ['return_delivery_order'],
            ['email_sales_order'],
            ['email_delivery_order'],
            ['email_debit_note'],
        ];
    }

    #[DataProvider('p0ToolNames')]
    public function test_p0_tool_exists_with_risk_and_permission(string $name): void
    {
        $this->assertTrue(CopilotCatalog::exists($name), "Expected catalog entry for {$name}");
        $this->assertNotSame('', CopilotCatalog::risk($name));
        $this->assertNotEmpty(CopilotCatalog::permission($name));
    }
}
