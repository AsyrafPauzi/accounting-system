<?php

namespace Tests\Feature\Tax;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreditNoteTaxCodePostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_note_with_sr8_posts_dr_output_tax_account(): void
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
            'name' => 'CN Tax Customer', 'code' => 'C-CN', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-CN-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Sale',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'item_classification' => '011',
        ]]);
        $invoices->post($invoice);

        $cn = app(CreditNoteService::class)->issue([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cn_number' => 'CN-TAX-001',
            'issue_date' => now()->toDateString(),
            'reason_code' => '02',
        ], [[
            'description' => 'Return',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'account_code' => '4000',
        ]]);

        $taxDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Credit Note')
            ->where('journal_entries.reference_id', $cn->id)
            ->where('journal_items.account_code', '2100')
            ->sum('journal_items.debit');

        $this->assertSame(8.0, $taxDebit);
    }
}
