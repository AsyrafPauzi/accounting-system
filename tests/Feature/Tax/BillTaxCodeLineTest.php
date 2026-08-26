<?php

namespace Tests\Feature\Tax;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Services\BillService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillTaxCodeLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_line_with_sr8_tax_code_posts_dr_1110(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($tenant);
        TaxCodeDefaults::seedMissing();
        $sr8 = TaxCode::query()->where('code', 'SR-8')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Line Tax Supplier', 'code' => 'SUP-LT', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $bills = app(BillService::class);
        $bill = $bills->create([
            'bill_number' => 'BILL-LT-001',
            'supplier_id' => $supplier->id,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
        ], [[
            'account_code' => '5000',
            'description'  => 'Taxable supplies',
            'amount'       => 100,
            'tax_code_id'  => $sr8->id,
            'tax_rate'     => 8,
        ]]);

        $this->assertSame(8.0, (float) $bill->tax_amount);
        $bills->post($bill);

        $taxDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Bill')
            ->where('journal_entries.reference_id', $bill->id)
            ->where('journal_items.account_code', '1110')
            ->sum('journal_items.debit');

        $this->assertSame(8.0, $taxDebit);
        $this->assertDatabaseHas('bill_items', [
            'bill_id'     => $bill->id,
            'tax_code_id' => $sr8->id,
        ]);
    }
}
