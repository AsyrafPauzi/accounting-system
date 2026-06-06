<?php

namespace Tests\Feature\Licensing;

use App\Services\Licensing\LicenseService;
use App\Support\Deployment;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies the license-driven feature gates that distinguish
 * `self-hosted-standard` from `self-hosted-enterprise`.
 *
 * The split ships purely on the `features[]` claim; `plan_tier` is
 * a label. These tests pin that contract so a refactor can't
 * silently break the gating.
 */
class TierFeatureGatesTest extends TestCase
{
    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate an in-memory keypair just for this test. We never
        // touch the real env keys so a developer running the suite
        // doesn't need their license-issuing key configured.
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);
        $this->privateKeyPem = $privateKey;
        $this->publicKeyPem  = $details['key'];

        // Pretend we're a customer install for the duration of the test.
        config([
            'deployment.mode'               => 'self_hosted',
            'deployment.license_public_key' => $this->publicKeyPem,
        ]);
        Cache::flush();
    }

    private function installLicense(array $claims): void
    {
        // Mirror what the publisher's `LicenseIssue` command does: sign
        // the payload, install the result as the customer's APP_LICENSE_KEY.
        $key = LicenseService::issue($claims, $this->privateKeyPem);
        config(['deployment.license_key' => $key]);
        app(LicenseService::class)->flush();
    }

    public function test_standard_license_disables_practice_console_and_multi_tenant(): void
    {
        $this->installLicense([
            'customer_id'   => 'acme',
            'customer_name' => 'Acme Sdn Bhd',
            'plan_tier'     => 'self-hosted-standard',
            'max_users'     => 0,
            'max_tenants'   => 1,
            'features'      => [], // <-- the whole point: no flags
        ]);

        $this->assertTrue(Deployment::isSelfHosted());
        $this->assertFalse(Deployment::practiceConsoleEnabled(),
            'Standard tier must NOT unlock the Practice console.');
        $this->assertFalse(Deployment::multiTenantEnabled(),
            'Standard tier must NOT unlock multi-tenant creation.');
        $this->assertSame(1, Deployment::licenseMaxTenants(),
            'Standard tier should be capped at 1 tenant.');
    }

    public function test_enterprise_license_enables_practice_and_multi_tenant(): void
    {
        $this->installLicense([
            'customer_id'   => 'acme-firm',
            'customer_name' => 'Acme Accountants',
            'plan_tier'     => 'self-hosted-enterprise',
            'max_users'     => 50,
            'max_tenants'   => 0, // unlimited by convention
            'features'      => ['practice.console', 'tenants.create'],
        ]);

        $this->assertTrue(Deployment::practiceConsoleEnabled(),
            'Enterprise tier must unlock the Practice console.');
        $this->assertTrue(Deployment::multiTenantEnabled(),
            'Enterprise tier must unlock multi-tenant creation.');
        $this->assertNull(Deployment::licenseMaxTenants(),
            'max_tenants=0 should be reported as unlimited (null).');
        $this->assertContains('practice.console', Deployment::licenseFeatures());
        $this->assertContains('tenants.create',  Deployment::licenseFeatures());
    }

    public function test_enterprise_with_finite_cap_is_returned_as_int(): void
    {
        $this->installLicense([
            'customer_id'   => 'acme-firm',
            'customer_name' => 'Acme Accountants',
            'plan_tier'     => 'self-hosted-enterprise',
            'max_users'     => 25,
            'max_tenants'   => 25,
            'features'      => ['practice.console', 'tenants.create'],
        ]);

        $this->assertSame(25, Deployment::licenseMaxTenants());
    }

    public function test_saas_mode_ignores_license(): void
    {
        // Even with a license configured, SaaS path should always
        // return true for these gates — they're SaaS-or-licensed.
        config(['deployment.mode' => 'saas']);
        $this->installLicense([
            'customer_id'   => 'whatever',
            'customer_name' => 'Whatever',
            'plan_tier'     => 'self-hosted-standard',
            'max_users'     => 1,
            'max_tenants'   => 1,
            'features'      => [],
        ]);

        $this->assertTrue(Deployment::practiceConsoleEnabled());
        $this->assertTrue(Deployment::multiTenantEnabled());
        $this->assertNull(Deployment::licenseMaxTenants(),
            'SaaS mode should not surface a license-driven cap.');
    }

    public function test_self_hosted_plan_settings_renders_license_page_with_expiry_math(): void
    {
        // Issue a license that expires 45 days from now so we can
        // assert `days_left` is positive and the renewal-due block
        // would render. We test the controller method directly
        // (no DB roundtrip) because the wider test suite's SQLite
        // migrations are flaky on this branch.
        $this->installLicense([
            'customer_id'   => 'acme',
            'customer_name' => 'Acme Sdn Bhd',
            'plan_tier'     => 'self-hosted-standard',
            'max_users'     => 5,
            'max_tenants'   => 1,
            'features'      => [],
            'expires_at'    => now()->addDays(45)->toIso8601String(),
        ]);

        $req = \Illuminate\Http\Request::create('/settings/plan', 'GET');
        $req->headers->set('X-Inertia', 'true');
        $req->headers->set('X-Inertia-Version', '1');
        // A bare User object is enough — the self-hosted branch
        // doesn't query the DB for the user; it just reads tenant_id
        // off the model.
        $user = new \App\Models\User(['tenant_id' => null]);
        $req->setUserResolver(fn () => $user);

        $ctrl = app(\App\Http\Controllers\SubscriptionController::class);
        $resp = $ctrl->planSettings($req)->toResponse($req);

        $this->assertSame(200, $resp->getStatusCode());
        $payload = json_decode($resp->getContent(), true) ?? [];
        $this->assertSame('Settings/PlanSelfHosted', $payload['component'] ?? null);

        $license = $payload['props']['license'] ?? [];
        $this->assertSame('valid',                    $license['status']      ?? null);
        $this->assertSame('Acme Sdn Bhd',             $license['customer_name'] ?? null);
        $this->assertSame('self-hosted-standard',     $license['plan_tier']   ?? null);
        $this->assertSame(5,                          $license['max_users']   ?? null);
        $this->assertSame(1,                          $license['max_tenants'] ?? null);
        $this->assertFalse($license['is_expired']  ?? true);
        $this->assertFalse($license['is_perpetual']?? true);
        $this->assertGreaterThanOrEqual(44, $license['days_left'] ?? -1,
            'Expiry math should report ~45 days remaining.');
        $this->assertLessThanOrEqual(45, $license['days_left'] ?? -1);
    }

    public function test_self_hosted_plan_settings_marks_expired_licenses(): void
    {
        // License that expired 7 days ago — UI should render in the
        // "overdue" tone and `days_left` should be negative.
        $this->installLicense([
            'customer_id'   => 'acme',
            'customer_name' => 'Acme Sdn Bhd',
            'plan_tier'     => 'self-hosted-standard',
            'max_users'     => 5,
            'max_tenants'   => 1,
            'features'      => [],
            'expires_at'    => now()->subDays(7)->toIso8601String(),
        ]);

        $req = \Illuminate\Http\Request::create('/settings/plan', 'GET');
        $req->headers->set('X-Inertia', 'true');
        $req->headers->set('X-Inertia-Version', '1');
        $user = new \App\Models\User(['tenant_id' => null]);
        $req->setUserResolver(fn () => $user);

        $ctrl = app(\App\Http\Controllers\SubscriptionController::class);
        $resp = $ctrl->planSettings($req)->toResponse($req);

        $payload = json_decode($resp->getContent(), true) ?? [];
        $license = $payload['props']['license'] ?? [];

        // Expired licenses still render the page (so the operator can
        // see the renewal CTA). The status is 'expired', not 'valid'.
        $this->assertSame('expired', $license['status'] ?? null);
        $this->assertTrue($license['is_expired']    ?? false);
        $this->assertFalse($license['is_perpetual'] ?? true);
        $this->assertLessThan(0, $license['days_left'] ?? 0,
            'Expired license should report a negative days_left.');
    }
}
