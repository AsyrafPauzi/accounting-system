<?php

namespace Tests\Feature\Sales;

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

class InvoiceSyncJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_posted_invoice_reposts_balanced_journal_via_writer(): void
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
            'name' => 'Edit Customer', 'code' => 'C-EDIT', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-EDIT-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Original',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'item_classification' => '011',
        ]]);
        $invoices->post($invoice);

        $invoices->update($invoice, [
            'invoice_number' => 'INV-EDIT-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Updated',
            'quantity'    => 1,
            'unit_price'  => 200,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'item_classification' => '011',
        ]]);

        $journals = DB::table('journal_entries')
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $journals->count());

        $latest = DB::table('journal_items')
            ->where('journal_entry_id', $journals->last()->id)
            ->get();

        $debit = $latest->sum('debit');
        $credit = $latest->sum('credit');
        $this->assertEqualsWithDelta($debit, $credit, 0.01);

        $arDebit = (float) $latest->where('account_code', '1100')->sum('debit');
        $this->assertSame(216.0, $arDebit);
    }
}
