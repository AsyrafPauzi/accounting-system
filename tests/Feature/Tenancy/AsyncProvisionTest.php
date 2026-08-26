<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenants;
use Tests\TestCase;

class AsyncProvisionTest extends TestCase
{
    use CreatesTestTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    public function test_tenant_create_stays_pending_when_provision_job_is_queued(): void
    {
        Queue::fake();

        $tenantId = 'pending-'.Str::lower(Str::random(8));
        $this->deleteTenantDatabaseFile($tenantId);

        $tenant = Tenant::create([
            'id' => $tenantId,
            'provision_status' => 'pending',
        ]);

        $tenant->refresh();

        $this->assertSame('pending', $tenant->provision_status);
        $this->assertNull($tenant->provisioned_at);
        Queue::assertPushed(ProvisionTenantJob::class, fn (ProvisionTenantJob $job) => $job->tenant->id === $tenantId);

        $prefix = (string) config('tenancy.database.prefix', 'tenant');
        $suffix = (string) config('tenancy.database.suffix', '');
        $path = database_path($prefix.$tenantId.$suffix);
        $this->assertFalse(is_file($path));
    }

    public function test_provision_job_marks_tenant_ready_and_seeds_defaults(): void
    {
        $tenant = $this->createTenantWithDatabase('ready-'.Str::lower(Str::random(8)));

        $tenant->refresh();

        $this->assertSame('ready', $tenant->provision_status);
        $this->assertNotNull($tenant->provisioned_at);
        $this->assertNull($tenant->provision_error);

        tenancy()->initialize($tenant);

        try {
            $this->assertDatabaseHas('tax_codes', ['code' => 'SR-8']);
            $this->assertDatabaseHas('document_number_settings', ['doc_type' => 'invoice']);
            $this->assertDatabaseHas('accounting_periods', ['status' => 'open']);
        } finally {
            tenancy()->end();
        }
    }

    public function test_registration_redirects_to_provisioning_before_dashboard(): void
    {
        Queue::fake();

        $response = $this->post('/register', [
            'name' => 'Async User',
            'email' => 'async@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'password_confirmation' => UserFactory::DEFAULT_PASSWORD,
            'accept_privacy' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('provisioning', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'async@example.com')->first();
        $tenant = Tenant::find($user->tenant_id);

        $this->assertSame('pending', $tenant->provision_status);
        Queue::assertPushed(ProvisionTenantJob::class);
    }

    public function test_unprovisioned_user_is_redirected_from_dashboard_to_provisioning(): void
    {
        Queue::fake();

        $tenantId = 'blocked-'.Str::lower(Str::random(8));
        $this->deleteTenantDatabaseFile($tenantId);

        $tenant = Tenant::create([
            'id' => $tenantId,
            'provision_status' => 'pending',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('provisioning'));
    }

    public function test_provisioning_status_endpoint_reports_ready_with_redirect(): void
    {
        $tenant = $this->createTenantWithDatabase('status-'.Str::lower(Str::random(8)));
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->getJson(route('provisioning.status'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'ready',
            'error' => null,
            'redirect' => route('dashboard', absolute: false),
        ]);
    }

    public function test_failed_provision_can_be_retried(): void
    {
        Queue::fake();

        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate([
            'id' => 'failed-'.Str::lower(Str::random(8)),
            'provision_status' => 'failed',
            'provision_error' => 'Simulated failure',
        ]));

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->post(route('provisioning.retry'));

        $response->assertRedirect(route('provisioning'));

        $tenant->refresh();
        $this->assertSame('pending', $tenant->provision_status);
        $this->assertNull($tenant->provision_error);
        Queue::assertPushed(ProvisionTenantJob::class);
    }
}
