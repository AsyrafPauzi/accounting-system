<?php

namespace Tests\Feature\Practice;

use App\Models\Firm;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ToyyibpayService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Pins the Practice billing flow:
 *
 *   - The picker lists every active practice tier including the
 *     contact-sales `practice-self-hosted` tier, sorted with paid
 *     tiers first and contact-sales last.
 *   - Paid → paid upgrade no longer dead-ends in "Talk to sales": the
 *     controller stashes the target plan in `pending_plan_id` /
 *     `pending_interval` and dispatches a Toyyibpay bill against the
 *     existing subscription. The current plan stays active while the
 *     bill is in flight.
 *   - The shared `/subscription/webhook` swaps `pending_*` into the
 *     live plan_id / interval atomically once Toyyibpay reports
 *     status_id=1 (paid), regardless of whether the payment was an
 *     SME first-purchase or a Practice upgrade.
 *   - `practice-self-hosted` is sold via sales — checkout refuses
 *     contact-sales plans even if the front-end button is bypassed.
 */
class PracticeBillingUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function makeFirmOwnerOnPlan(string $planSlug): array
    {
        $firm = Firm::create([
            'name' => 'Test Firm',
            'slug' => 'test-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');

        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $sub = Subscription::create([
            'firm_id'                => $firm->id,
            'tenant_id'              => null,
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'gateway'                => $planSlug === 'practice-free' ? 'system' : 'toyyibpay',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
        ]);

        $firm->forceFill([
            'owner_user_id'        => $owner->id,
            'firm_subscription_id' => $sub->id,
        ])->save();

        return [$owner, $firm, $sub, $plan];
    }

    public function test_picker_includes_self_hosted_card_with_contact_sales_flag(): void
    {
        [$owner] = $this->makeFirmOwnerOnPlan('practice-starter');

        $response = $this->actingAs($owner)->get('/practice/plan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Practice/Plan')
            ->where('plans', function ($plans) {
                $slugs = collect($plans)->pluck('slug')->all();
                $this->assertContains('practice-self-hosted', $slugs, 'Self-hosted card must appear in picker');
                // Self-hosted should be last (contact-sales sorted after paid).
                $this->assertSame('practice-self-hosted', end($slugs), 'Self-hosted must be the final card');

                $selfHosted = collect($plans)->firstWhere('slug', 'practice-self-hosted');
                $this->assertTrue((bool) $selfHosted['is_contact_sales']);
                return true;
            })
        );
    }

    public function test_paid_to_paid_upgrade_initiates_toyyibpay_and_stashes_pending_target(): void
    {
        [$owner, , $sub] = $this->makeFirmOwnerOnPlan('practice-starter');
        $growth = Plan::where('slug', 'practice-growth')->firstOrFail();

        // Stub Toyyibpay so we don't hit the real gateway.
        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('createBill')
            ->once()
            ->withArgs(function (array $payload) use ($sub) {
                // The bill must reference the EXISTING subscription so the
                // shared webhook can pick it up and swap pending → active.
                $this->assertSame((string) $sub->id, $payload['billExternalReferenceNo']);
                $this->assertStringContainsString('Practice Growth', $payload['billName']);
                return true;
            })
            ->andReturn('https://toyyibpay.test/redirect/abc');
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->actingAs($owner)->post('/practice/plan/checkout', [
            'plan_id'  => $growth->id,
            'interval' => 'monthly',
        ]);

        // Inertia::location returns a 409 with X-Inertia-Location for SPA
        // navigations; tolerate either that or a 302 if the browser is
        // doing a hard redirect.
        $this->assertContains($response->status(), [302, 409]);

        $sub->refresh();
        $this->assertSame((int) $growth->id, (int) $sub->pending_plan_id);
        $this->assertSame('monthly', $sub->pending_interval);
        // Crucially: the current plan stays active. Losing access during
        // checkout would be hostile UX, and the webhook handles the swap.
        $this->assertSame('active', $sub->status);
        $this->assertSame('toyyibpay', $sub->gateway);
        $starterId = Plan::where('slug', 'practice-starter')->value('id');
        $this->assertSame((int) $starterId, (int) $sub->plan_id);
    }

    public function test_webhook_swaps_pending_plan_and_interval_to_live_on_success(): void
    {
        [, , $sub] = $this->makeFirmOwnerOnPlan('practice-starter');
        $firm = Plan::where('slug', 'practice-firm')->firstOrFail();

        // Simulate the controller having already initiated an upgrade.
        $sub->update([
            'pending_plan_id'  => $firm->id,
            'pending_interval' => 'yearly',
            'gateway'          => 'toyyibpay',
        ]);

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')
            ->once()
            ->with('tbpy-xyz', (string) $sub->id)
            ->andReturn(true);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->postJson('/subscription/webhook', [
            'billcode'  => 'tbpy-xyz',
            'status_id' => 1,
            'order_id'  => $sub->id,
        ]);

        $response->assertStatus(200);

        $sub->refresh();
        $this->assertSame((int) $firm->id, (int) $sub->plan_id);
        $this->assertSame('yearly', $sub->interval);
        $this->assertNull($sub->pending_plan_id);
        $this->assertNull($sub->pending_interval);
        $this->assertSame('active', $sub->status);
        $this->assertSame('tbpy-xyz', $sub->gateway_subscription_id);
        // Yearly → period extends a year out.
        $this->assertNotNull($sub->current_period_ends_at);
        $expectedEnd = now()->addYear()->toDateString();
        $this->assertSame($expectedEnd, $sub->current_period_ends_at->toDateString());
    }

    public function test_webhook_keeps_existing_plan_when_no_pending_upgrade_was_set(): void
    {
        [, , $sub] = $this->makeFirmOwnerOnPlan('practice-starter');
        $sub->update(['status' => 'pending']); // first-time paid checkout

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')
            ->once()
            ->with('tbpy-first', (string) $sub->id)
            ->andReturn(true);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->postJson('/subscription/webhook', [
            'billcode'  => 'tbpy-first',
            'status_id' => 1,
            'order_id'  => $sub->id,
        ]);

        $response->assertStatus(200);

        $sub->refresh();
        $starterId = Plan::where('slug', 'practice-starter')->value('id');
        $this->assertSame((int) $starterId, (int) $sub->plan_id, 'No pending → plan stays put');
        $this->assertSame('active', $sub->status);
        $this->assertSame('tbpy-first', $sub->gateway_subscription_id);
    }

    public function test_self_hosted_checkout_is_refused(): void
    {
        [$owner] = $this->makeFirmOwnerOnPlan('practice-starter');
        $selfHosted = Plan::where('slug', 'practice-self-hosted')->firstOrFail();

        // Toyyibpay must not be invoked for a contact-sales plan.
        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldNotReceive('createBill');
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->actingAs($owner)->post('/practice/plan/checkout', [
            'plan_id'  => $selfHosted->id,
            'interval' => 'monthly',
        ]);

        $response->assertRedirect(route('practice.plan'));
        $response->assertSessionHas('error');
    }

    public function test_upgrade_rolls_back_pending_fields_when_gateway_returns_no_url(): void
    {
        [$owner, , $sub] = $this->makeFirmOwnerOnPlan('practice-starter');
        $growth = Plan::where('slug', 'practice-growth')->firstOrFail();

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('createBill')->once()->andReturn(null);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->actingAs($owner)->post('/practice/plan/checkout', [
            'plan_id'  => $growth->id,
            'interval' => 'monthly',
        ]);

        $response->assertSessionHas('error');

        $sub->refresh();
        $this->assertNull($sub->pending_plan_id, 'Phantom pending state must be cleaned up on failure');
        $this->assertNull($sub->pending_interval);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
