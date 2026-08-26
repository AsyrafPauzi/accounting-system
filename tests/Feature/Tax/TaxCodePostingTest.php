<?php

namespace Tests\Feature\Tax;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaxCodePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_with_sr8_tax_code_posts_cr_2100(): void
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

        $customer = Customer::create([
            'name' => 'Tax Customer', 'code' => 'C-TAX', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-TAX-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Taxable sale',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'item_classification' => '011',
        ]]);

        $invoices->post($invoice);

        $taxCredit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Invoice')
            ->where('journal_entries.reference_id', $invoice->id)
            ->where('journal_items.account_code', '2100')
            ->sum('journal_items.credit');

        $this->assertSame(8.0, $taxCredit);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id'  => $invoice->id,
            'tax_code_id' => $sr8->id,
        ]);
    }
}
