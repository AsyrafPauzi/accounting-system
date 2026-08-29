<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ToyyibpayService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * SME "Choose plan" must not revoke access before payment succeeds.
 *
 * Trial rows already park Startup in `pending_plan_id` for auto-downgrade
 * when the trial ends. Checkout therefore encodes the paid target in the
 * ToyyibPay external reference instead of overwriting that fallback.
 */
class SubscriptionCheckoutKeepsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function makeTrialingUser(): array
    {
        $tenant = $this->createTenantWithDatabase();
        $solo = Plan::where('slug', 'solo')->firstOrFail();
        $startup = Plan::where('slug', 'startup')->firstOrFail();

        $adminRole = Role::where('name', 'admin')->first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $user->assignRole('admin');
        }

        $sub = Subscription::create([
            'tenant_id'              => $tenant->id,
            'plan_id'                => $solo->id,
            'pending_plan_id'        => $startup->id,
            'pending_interval'       => 'monthly',
            'status'                 => 'trialing',
            'interval'               => 'monthly',
            'gateway'                => 'system',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addDays(14)->toDateString(),
        ]);

        return [$user, $sub, $solo, $startup];
    }

    public function test_trial_checkout_keeps_trial_and_startup_fallback_until_paid(): void
    {
        [$user, $sub, $solo, $startup] = $this->makeTrialingUser();
        $growth = Plan::where('slug', 'growth')->firstOrFail();
        $trialEnds = $sub->current_period_ends_at->toDateString();

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('createBill')
            ->once()
            ->withArgs(function (array $payload) use ($sub, $growth) {
                $this->assertSame(
                    $sub->id.':'.$growth->id.':monthly',
                    $payload['billExternalReferenceNo']
                );
                $this->assertStringContainsString('Growth', $payload['billName']);

                return true;
            })
            ->andReturn('https://toyyibpay.test/pay/trial-keep');
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id'  => $growth->id,
            'interval' => 'monthly',
        ]);

        $this->assertContains($response->status(), [302, 409]);

        $sub->refresh();
        $this->assertSame('trialing', $sub->status);
        $this->assertSame((int) $solo->id, (int) $sub->plan_id);
        $this->assertSame((int) $startup->id, (int) $sub->pending_plan_id);
        $this->assertSame('monthly', $sub->pending_interval);
        $this->assertSame($trialEnds, $sub->current_period_ends_at->toDateString());
        $this->assertTrue($sub->isActive());
    }

    public function test_webhook_applies_checkout_target_from_order_ref_and_clears_trial_fallback(): void
    {
        [$user, $sub, $solo, $startup] = $this->makeTrialingUser();
        $growth = Plan::where('slug', 'growth')->firstOrFail();
        $orderId = $sub->id.':'.$growth->id.':yearly';

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')
            ->once()
            ->with('tbpy-growth', $orderId)
            ->andReturn(true);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->postJson(route('subscription.webhook'), [
            'billcode'  => 'tbpy-growth',
            'status_id' => 1,
            'order_id'  => $orderId,
        ]);

        $response->assertOk();

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame((int) $growth->id, (int) $sub->plan_id);
        $this->assertSame('yearly', $sub->interval);
        $this->assertNull($sub->pending_plan_id);
        $this->assertNull($sub->pending_interval);
        $this->assertSame('tbpy-growth', $sub->gateway_subscription_id);
        $this->assertSame(now()->addYear()->toDateString(), $sub->current_period_ends_at->toDateString());
    }

    public function test_startup_checkout_keeps_free_plan_active_until_webhook(): void
    {
        $tenant = $this->createTenantWithDatabase();
        $startup = Plan::where('slug', 'startup')->firstOrFail();
        $solo = Plan::where('slug', 'solo')->firstOrFail();

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $sub = Subscription::create([
            'tenant_id'              => $tenant->id,
            'plan_id'                => $startup->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'gateway'                => 'system',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
        ]);

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('createBill')
            ->once()
            ->withArgs(fn (array $payload) => $payload['billExternalReferenceNo'] === $sub->id.':'.$solo->id.':monthly')
            ->andReturn('https://toyyibpay.test/pay/startup');
        $this->app->instance(ToyyibpayService::class, $stub);

        $this->actingAs($user)->post(route('subscription.checkout'), [
            'plan_id'  => $solo->id,
            'interval' => 'monthly',
        ]);

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame((int) $startup->id, (int) $sub->plan_id);
        $this->assertTrue($sub->isActive());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
