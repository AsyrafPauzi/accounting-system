<?php

namespace Tests\Feature\Inventory;

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeightedAvgCogsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Product $product;

    private Customer $customer;

    private InventoryService $inventory;

    private InvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($this->tenant);

        $this->product = Product::create([
            'code'              => 'SKU-WAVG',
            'name'              => 'Weighted Widget',
            'unit_price'        => 20,
            'account_code'      => '4000',
            'track_inventory'   => true,
            'qty_on_hand'       => 0,
            'avg_cost'          => 0,
            'is_active'         => true,
        ]);

        $this->customer = Customer::create([
            'name'            => 'Inventory Customer',
            'code'            => 'CUST-INV-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $this->inventory = app(InventoryService::class);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_weighted_average_receive_and_invoice_cogs(): void
    {
        $today = now()->toDateString();

        $this->inventory->receive($this->product, 10, 5.0, $today);
        $this->inventory->receive($this->product->fresh(), 10, 7.0, $today);

        $this->product->refresh();
        $this->assertSame(20.0, (float) $this->product->qty_on_hand);
        $this->assertSame(6.0, (float) $this->product->avg_cost);

        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-COGS-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $today,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'product_id'          => $this->product->id,
            'description'         => $this->product->name,
            'quantity'            => 5,
            'unit_price'          => 20,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);

        $this->product->refresh();
        $this->assertSame(15.0, (float) $this->product->qty_on_hand);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id'     => $this->product->id,
            'type'           => 'issue',
            'reference_type' => 'Invoice',
            'reference_id'   => $invoice->id,
        ]);

        $cogsDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Invoice')
            ->where('journal_entries.reference_id', $invoice->id)
            ->where('journal_items.account_code', InventoryService::COGS_ACCOUNT)
            ->value('journal_items.debit');

        $inventoryCredit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Invoice')
            ->where('journal_entries.reference_id', $invoice->id)
            ->where('journal_items.account_code', InventoryService::INVENTORY_ACCOUNT)
            ->value('journal_items.credit');

        $this->assertSame(30.0, $cogsDebit);
        $this->assertSame(30.0, $inventoryCredit);
        $this->assertSame(2, InventoryMovement::where('product_id', $this->product->id)->where('type', 'receive')->count());
    }
}
