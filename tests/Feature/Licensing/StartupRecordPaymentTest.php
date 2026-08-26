<?php

namespace Tests\Feature\Licensing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupRecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_user_can_record_invoice_payment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('slug', 'startup')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $user->assignRole('admin');
        }

        tenancy()->initialize($tenant);
        $customer = Customer::create([
            'name'            => 'Startup Customer',
            'code'            => 'C-STARTUP',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number'  => 'INV-STARTUP-001',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(14)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Starter service',
            'quantity'            => 1,
            'unit_price'          => 50,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $invoices->post($invoice);
        tenancy()->end();

        $this->actingAs($user)
            ->post(route('invoices.record-payment', $invoice->id), [
                'amount'            => 50,
                'payment_date'      => now()->toDateString(),
                'bank_account_code' => '1200',
                'reference'         => 'STARTUP-PAY-001',
            ])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(50.0, (float) $invoice->amount_paid);
    }

    public function test_startup_plan_includes_record_payment_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $perms = Plan::where('slug', 'startup')->firstOrFail()
            ->permissions->pluck('name')->all();

        $this->assertContains('invoices.record-payment', $perms);
        $this->assertNotContains('bills.record-payment', $perms);
    }
}
