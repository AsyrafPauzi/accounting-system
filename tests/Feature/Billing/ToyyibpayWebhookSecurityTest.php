<?php

namespace Tests\Feature\Billing;

use App\Models\Firm;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ToyyibpayService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ToyyibpayWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    public function test_subscription_webhook_rejects_unverified_paid_status(): void
    {
        $firm = Firm::create([
            'name'   => 'Security Firm',
            'slug'   => 'security-firm',
            'status' => 'active',
        ]);

        $plan = Plan::where('slug', 'practice-starter')->firstOrFail();
        $sub = Subscription::create([
            'firm_id'                => $firm->id,
            'tenant_id'              => null,
            'plan_id'                => $plan->id,
            'status'                 => 'pending',
            'interval'               => 'monthly',
            'gateway'                => 'toyyibpay',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
        ]);

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')
            ->once()
            ->with('fake-bill', (string) $sub->id)
            ->andReturn(false);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->postJson('/subscription/webhook', [
            'billcode'  => 'fake-bill',
            'status_id' => 1,
            'order_id'  => $sub->id,
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending', $sub->fresh()->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
