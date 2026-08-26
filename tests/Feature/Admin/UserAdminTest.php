<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $secondSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['tenant_id' => null]);
        $this->superAdmin->assignRole('super-admin');

        $this->secondSuperAdmin = User::factory()->create(['tenant_id' => null]);
        $this->secondSuperAdmin->assignRole('super-admin');
    }

    public function test_super_admin_can_list_users(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $regular = User::factory()->create();
        $regular->assignRole('admin');

        $this->actingAs($regular)
            ->withoutMiddleware(\App\Http\Middleware\EnsureSubscribed::class)
            ->get(route('admin.users.index'))
            ->assertStatus(403);
    }

    public function test_super_admin_can_demote_another_super_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.role', $this->secondSuperAdmin->id), ['role' => 'admin'])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($this->secondSuperAdmin->fresh()->hasRole('super-admin'));
        $this->assertTrue($this->secondSuperAdmin->fresh()->hasRole('admin'));
    }

    public function test_cannot_demote_last_super_admin(): void
    {
        // First demote secondSuperAdmin so only superAdmin remains
        $this->secondSuperAdmin->syncRoles(['admin']);

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.role', $this->superAdmin->id), ['role' => 'admin'])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Still a super-admin
        $this->assertTrue($this->superAdmin->fresh()->hasRole('super-admin'));
    }

    public function test_super_admin_can_suspend_another_user(): void
    {
        $target = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $target->assignRole('admin');

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.toggle-active', $target->id))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_cannot_suspend_own_account(): void
    {
        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.toggle-active', $this->superAdmin->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($this->superAdmin->fresh()->is_active ?? true);
    }

    public function test_cannot_suspend_last_active_super_admin(): void
    {
        // Suspend secondSuperAdmin first so only superAdmin remains active
        $this->secondSuperAdmin->update(['is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->patch(route('admin.users.toggle-active', $this->superAdmin->id))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_super_admin_can_send_password_reset(): void
    {
        $target = User::factory()->create(['tenant_id' => null]);
        $target->assignRole('admin');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.users.password-reset', $target->id))
            ->assertRedirect(route('admin.users.index'));
    }
}
