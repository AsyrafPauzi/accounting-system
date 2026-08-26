<?php

namespace Tests\Feature\Onboarding;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OnboardingChecklist;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_sees_checklist_with_company_step(): void
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
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole?->id]);
        if ($adminRole) {
            $user->assignRole('admin');
        }

        tenancy()->initialize($tenant);
        $checklist = OnboardingChecklist::forUser($user, $tenant);

        $this->assertTrue($checklist['visible']);
        $this->assertSame(4, $checklist['total']);
        $this->assertSame('company', $checklist['steps'][0]['key']);
    }

    public function test_dismiss_checklist_hides_widget(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->post(route('onboarding.checklist.dismiss'))
            ->assertRedirect();

        $user->refresh();
        $checklist = OnboardingChecklist::forUser($user, $tenant);
        $this->assertFalse($checklist['visible']);
    }

    public function test_collect_step_uses_emailed_and_viewed_columns(): void
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

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);

        $customer = Customer::create([
            'name' => 'Collect Customer', 'code' => 'C-ONB', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
        Invoice::create([
            'invoice_number'     => 'INV-ONB-001',
            'customer_id'        => $customer->id,
            'issue_date'         => now()->toDateString(),
            'due_date'           => now()->addDays(14)->toDateString(),
            'amount_before_tax'  => 100,
            'tax_amount'         => 0,
            'total_amount'       => 100,
            'amount_paid'        => 0,
            'status'             => 'unpaid',
            'currency'           => 'MYR',
            'last_emailed_at'    => now(),
        ]);

        $checklist = OnboardingChecklist::forUser($user, $tenant);
        $collect = collect($checklist['steps'])->firstWhere('key', 'collect');

        $this->assertNotNull($collect);
        $this->assertTrue($collect['done']);
    }
}
