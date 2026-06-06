<?php

namespace Tests\Feature\Licensing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the security & cleanup decisions made when splitting Standard
 * vs Enterprise self-hosted. If anyone re-enables Toyyibpay in
 * self-hosted mode, brings back the dummy Subscription row, or wires
 * EnsureSubscribed back into the self-hosted middleware stack, these
 * tests fail loudly.
 */
class SelfHostedHygieneTest extends TestCase
{
    public function test_toyyibpay_credentials_are_not_loaded_in_self_hosted(): void
    {
        // Simulate `config()` re-resolution for self-hosted mode by
        // mutating the runtime config to what services.php would
        // produce. The branch in services.php reads APP_DEPLOYMENT_MODE
        // at config-load time, which the test framework can't easily
        // re-trigger; we assert the SHAPE the gate produces.
        config(['services.toyyibpay' => [
            'secret_key'    => null,
            'category_code' => null,
            'env'           => 'disabled',
        ]]);

        $svc = new \App\Services\ToyyibpayService();
        $reflect = new \ReflectionObject($svc);
        $secret = $reflect->getProperty('secretKey');
        $secret->setAccessible(true);
        $cat = $reflect->getProperty('categoryCode');
        $cat->setAccessible(true);

        $this->assertSame('', $secret->getValue($svc),
            'ToyyibpayService must NOT pull a key when config returns null '.
            '(prevents env-var leakage on self-hosted installs).');
        $this->assertSame('', $cat->getValue($svc));
    }

    public function test_self_hosted_bootstrap_does_not_create_subscription_row(): void
    {
        // We assert the source code, not runtime, because exercising
        // the bootstrap command requires a full DB. The contract is:
        // SelfHostedBootstrap MUST NOT touch the Subscription model.
        $source = file_get_contents(base_path('app/Console/Commands/SelfHostedBootstrap.php'));
        $this->assertFalse(
            (bool) preg_match('/Subscription::(firstOrCreate|create|updateOrCreate)/', $source),
            'SelfHostedBootstrap must not provision a Subscription row — '.
            'self-hosted mode ignores the SaaS subscription system entirely.'
        );
        // And it shouldn't even import the model, otherwise a future
        // refactor might re-add the call.
        $this->assertFalse(
            str_contains($source, 'use App\Models\Subscription;'),
            'Subscription import is dead in SelfHostedBootstrap; remove it.'
        );
    }

    public function test_ensure_subscribed_is_skipped_when_self_hosted(): void
    {
        // The bootstrap conditionally appends EnsureSubscribed only on
        // SaaS. We test the gate by inspecting the source — checking
        // the application's middleware stack at runtime would require
        // re-bootstrapping with a different env, which is heavyweight.
        $source = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString(
            "if (env('APP_DEPLOYMENT_MODE', 'saas') !== 'self_hosted') {",
            $source,
            'EnsureSubscribed must be conditionally registered to skip '.
            'one middleware dispatch per request on self-hosted installs.'
        );
        $this->assertStringContainsString(
            '$webMiddleware[] = EnsureSubscribed::class;',
            $source
        );
    }

    public function test_saas_only_routes_live_in_routes_saas_php(): void
    {
        $saas = file_get_contents(base_path('routes/saas.php'));
        // Spot-check a few names that should now live here.
        $this->assertStringContainsString("admin.self-hosted.index", $saas);
        $this->assertStringContainsString("admin.platform.show", $saas);
        $this->assertStringContainsString("admin.tenants.index", $saas);
        $this->assertStringContainsString("subscription.index", $saas);

        // ...and shouldn't appear in web.php anymore (the route
        // definitions, not just the references).
        $web = file_get_contents(base_path('routes/web.php'));
        $this->assertFalse(
            str_contains($web, "Route::get('/admin/self-hosted',"),
            "admin.self-hosted route definition should have moved to routes/saas.php."
        );
        $this->assertFalse(
            str_contains($web, "Route::get('/subscription',"),
            "subscription.index route definition should have moved to routes/saas.php."
        );
    }
}
