<?php

namespace Tests\Feature\Auth;

use App\Models\Firm;
use App\Models\User;
use App\Notifications\Auth\VerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = $this->makeUnverifiedTenantUser();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = $this->makeUnverifiedTenantUser();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = $this->makeUnverifiedTenantUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_or_expired_verification_link_shows_bukucloud_page(): void
    {
        $user = $this->makeUnverifiedTenantUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', $version)
            ->get($verificationUrl);

        $response->assertStatus(403);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('component', 'Auth/InvalidVerificationLink');
        $response->assertJsonPath('props.isVerified', false);
    }

    public function test_verification_notification_can_be_resent_for_business_user(): void
    {
        Notification::fake();
        $user = $this->makeUnverifiedTenantUser();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_notification_can_be_resent_for_firm_owner(): void
    {
        Notification::fake();
        $user = $this->makeUnverifiedFirmOwner();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function makeUnverifiedTenantUser(): User
    {
        $user = User::factory()->create([
            'tenant_id' => 'verify-test-'.uniqid('', true),
        ]);

        $user->forceFill(['email_verified_at' => null])->save();

        return $user->fresh();
    }

    private function makeUnverifiedFirmOwner(): User
    {
        $firm = Firm::create([
            'name' => 'Verification Test Firm',
            'slug' => 'verification-test-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id' => $firm->id,
            'firm_role' => 'owner',
        ]);
        $owner->forceFill(['email_verified_at' => null])->save();
        $owner->assignRole('firm-owner');

        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner->fresh();
    }
}
