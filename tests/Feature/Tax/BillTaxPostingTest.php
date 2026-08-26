<?php

namespace Tests\Feature\Tax;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\BillService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillTaxPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_bill_debits_tax_receivable_1110(): void
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

        $supplier = Supplier::create([
            'name' => 'Tax Supplier', 'code' => 'SUP-TAX', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $bills = app(BillService::class);
        $bill = $bills->create([
            'bill_number' => 'BILL-TAX-001',
            'supplier_id' => $supplier->id,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
            'tax_amount'  => 8,
        ], [[
            'account_code' => '5000', 'description' => 'Supplies', 'amount' => 100,
        ]]);
        $bills->post($bill);

        $taxDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Bill')
            ->where('journal_entries.reference_id', $bill->id)
            ->where('journal_items.account_code', '1110')
            ->sum('journal_items.debit');

        $this->assertSame(8.0, $taxDebit);
    }
}
