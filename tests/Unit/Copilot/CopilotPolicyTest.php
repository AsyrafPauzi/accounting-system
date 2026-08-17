<?php

namespace Tests\Unit\Copilot;

use App\Models\CopilotPendingAction;
use App\Models\User;
use App\Services\Copilot\CopilotCatalog;
use App\Services\Copilot\CopilotService;
use App\Services\Copilot\CopilotTools;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopilotPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_helper_returns_only_past_due_open_invoices(): void
    {
        $rows = [
            (object) [
                'id' => 1,
                'invoice_number' => 'INV-1',
                'status' => 'unpaid',
                'due_date' => now()->subDays(10)->toDateString(),
                'total_amount' => 100,
                'amount_paid' => 20,
                'customer_name' => 'Ali',
            ],
            (object) [
                'id' => 2,
                'invoice_number' => 'INV-2',
                'status' => 'unpaid',
                'due_date' => now()->addDays(3)->toDateString(),
                'total_amount' => 50,
                'amount_paid' => 0,
                'customer_name' => 'Not overdue',
            ],
            (object) [
                'id' => 3,
                'invoice_number' => 'INV-3',
                'status' => 'paid',
                'due_date' => now()->subDays(40)->toDateString(),
                'total_amount' => 80,
                'amount_paid' => 80,
                'customer_name' => 'Paid',
            ],
        ];

        $overdue = CopilotTools::summariseOverdue($rows);

        $this->assertCount(1, $overdue);
        $this->assertSame('INV-1', $overdue[0]['invoice_number']);
        $this->assertEquals(80.0, $overdue[0]['balance']);
        $this->assertSame(10, $overdue[0]['days_overdue']);
    }

    public function test_confirm_refuses_high_tools_without_pending_row(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Confirm refused: no pending action.');

        CopilotService::requirePending(null);
    }

    public function test_confirm_refuses_non_pending_status(): void
    {
        $pending = new CopilotPendingAction(['status' => CopilotPendingAction::STATUS_CANCELLED]);

        $this->expectException(\RuntimeException::class);
        CopilotService::requirePending($pending);
    }

    public function test_draft_bill_from_receipt_requires_vendor_name(): void
    {
        $tool = CopilotCatalog::tools()['draft_bill_from_receipt'] ?? null;
        $this->assertIsArray($tool);
        $this->assertSame(CopilotCatalog::RISK_DRAFT, CopilotCatalog::risk('draft_bill_from_receipt'));
        $this->assertSame('bills.create', CopilotCatalog::permission('draft_bill_from_receipt'));
        $required = $tool['parameters']['required'] ?? [];
        $this->assertContains('vendor_name', $required);
        $this->assertContains('items', $required);
        $this->assertArrayHasKey('vendor_name', $tool['parameters']['properties'] ?? []);
    }

    public function test_post_invoice_is_high_risk_and_needs_invoices_post(): void
    {
        $this->assertSame(CopilotCatalog::RISK_HIGH, CopilotCatalog::risk('post_invoice'));
        $this->assertSame('invoices.post', CopilotCatalog::permission('post_invoice'));
        $this->assertSame(CopilotCatalog::RISK_READ, CopilotCatalog::risk('overdue_invoices'));
        $this->assertSame(CopilotCatalog::RISK_DRAFT, CopilotCatalog::risk('draft_invoice'));
    }

    public function test_permission_denied_without_invoices_post(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('invoices.view');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('invoices.post');

        app(CopilotTools::class)->assertAllowed($user, 'post_invoice');
    }

    public function test_lhdn_suggestion_does_not_write(): void
    {
        $out = CopilotTools::suggestClassification('Monthly accounting retainer SST');

        $this->assertFalse($out['wrote']);
        $this->assertArrayHasKey('item_classification', $out);
        $this->assertGreaterThan(0, $out['suggested_tax_rate']);
    }
}
