<?php

namespace Tests\Unit\Logging;

use App\Logging\TenantContextProcessor;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class TenantContextProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_tenant_and_user_ids_when_available(): void
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

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        tenancy()->initialize($tenant);
        $this->actingAs($user);

        $processor = new TenantContextProcessor();
        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: [],
        ));

        $this->assertSame($tenant->id, $record->extra['tenant_id']);
        $this->assertSame($user->id, $record->extra['user_id']);
    }

    public function test_leaves_extra_unchanged_without_tenant_or_user(): void
    {
        $processor = new TenantContextProcessor();
        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: [],
        ));

        $this->assertSame([], $record->extra);
    }
}
