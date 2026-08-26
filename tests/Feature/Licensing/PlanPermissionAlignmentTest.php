<?php

namespace Tests\Feature\Licensing;

use App\Models\Permission;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that every bullet on the SaaS pricing page is
 * backed by a real Spatie permission, that each tier's permission
 * set matches the bullets we sell, and that the layered Startup ⊂
 * Solo ⊂ Growth ⊂ Corporate ⊆ Enterprise inheritance holds.
 *
 * This test is the source of truth for "is the plan in sync with the
 * system?". If a bullet gets added to the pricing page without a
 * permission grant, this test fails. If a permission gets removed
 * from a plan that still advertises the bullet, this test fails.
 */
class PlanPermissionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function permsFor(string $slug): array
    {
        return Plan::where('slug', $slug)->firstOrFail()
            ->permissions->pluck('name')->sort()->values()->all();
    }

    // ----- Layered inheritance: each tier strictly contains the previous -----

    public function test_solo_includes_everything_in_startup(): void
    {
        $startup = $this->permsFor('startup');
        $solo = $this->permsFor('solo');
        $missing = array_diff($startup, $solo);
        $this->assertEmpty(
            $missing,
            'Solo must include every Startup permission. Missing: '.implode(', ', $missing)
        );
    }

    public function test_growth_includes_everything_in_solo(): void
    {
        $solo = $this->permsFor('solo');
        $growth = $this->permsFor('growth');
        $missing = array_diff($solo, $growth);
        $this->assertEmpty(
            $missing,
            'Growth must include every Solo permission. Missing: '.implode(', ', $missing)
        );
    }

    public function test_corporate_includes_everything_in_growth(): void
    {
        $growth = $this->permsFor('growth');
        $corporate = $this->permsFor('corporate');
        $missing = array_diff($growth, $corporate);
        $this->assertEmpty(
            $missing,
            'Corporate must include every Growth permission. Missing: '.implode(', ', $missing)
        );
    }

    public function test_enterprise_includes_everything_in_corporate(): void
    {
        $corporate = $this->permsFor('corporate');
        $enterprise = $this->permsFor('enterprise');
        $missing = array_diff($corporate, $enterprise);
        $this->assertEmpty(
            $missing,
            'Enterprise must include every Corporate permission. Missing: '.implode(', ', $missing)
        );
    }

    // ----- Tier-specific bullets are backed by real grants -----

    public function test_startup_grants_match_bullets(): void
    {
        $perms = $this->permsFor('startup');

        // "Basic invoicing" (issue + edit + post + void + record-payment for day-1 collect).
        $this->assertContains('invoices.view', $perms);
        $this->assertContains('invoices.create', $perms);
        // "Up to 5 active customers" (cap enforced by PlanCap)
        $this->assertContains('customers.create', $perms);
        // "Single bank account" — they must be able to view their CoA
        $this->assertContains('accounts.view', $perms);
        // Startup can record manual payments on invoices (Wave 2 day-1 collect).
        $this->assertContains('invoices.record-payment', $perms);
        $this->assertNotContains('ocr.use', $perms);
        $this->assertNotContains('copilot.use', $perms);
    }

    public function test_startup_does_not_grant_solo_or_growth_features(): void
    {
        $perms = $this->permsFor('startup');

        // Startup does NOT include the Solo+ bullets
        $this->assertNotContains('credit-notes.view', $perms, 'Credit notes is a Solo+ bullet');
        $this->assertNotContains('credit-notes.create', $perms);
        $this->assertNotContains('recurring-invoices.view', $perms, 'Recurring invoices is a Solo+ bullet');
        $this->assertNotContains('estimates.view', $perms, 'Estimates is a Solo+ bullet');
        $this->assertNotContains('ocr.use', $perms, 'OCR is a Solo+ bullet');
        $this->assertNotContains('copilot.use', $perms, 'Accountant copilot is a Solo+ feature');
        $this->assertNotContains('invoices.email', $perms, 'Email invoices is a Solo+ bullet');
        $this->assertNotContains('bills.view', $perms, 'Bills is a Solo+ bullet');

        // ...nor any Growth+ bullets
        $this->assertNotContains('products.view', $perms, 'Products catalogue is a Growth+ bullet');
        $this->assertNotContains('customer-statements.view', $perms);
        $this->assertNotContains('reports.balance-sheet', $perms, 'Balance sheet is a Growth+ bullet');
    }

    public function test_solo_grants_match_bullets(): void
    {
        $perms = $this->permsFor('solo');

        // "Email invoices & estimates" — both halves of the bullet
        // must be backed by real permissions, not just the invoice
        // half. estimates.email gates the EstimateController@email
        // route added alongside this bullet.
        $this->assertContains('invoices.email', $perms);
        $this->assertContains('estimates.view', $perms);
        $this->assertContains('estimates.create', $perms);
        $this->assertContains('estimates.email', $perms);
        // "Recurring invoices & credit notes"
        $this->assertContains('recurring-invoices.view', $perms);
        $this->assertContains('credit-notes.view', $perms);
        // "OCR receipt capture"
        $this->assertContains('ocr.use', $perms);
        $this->assertContains('copilot.use', $perms);
        // "P&L and sales reports" — direct grants for the two reports
        // currently in the system. Bank reconciliation lives on the
        // roadmap; if anyone re-adds the bullet without shipping the
        // feature, the seeder comment + this test should catch it.
        $this->assertContains('reports.view', $perms);
        $this->assertContains('reports.profit-loss', $perms);
        $this->assertContains('reports.sales', $perms);

        // Solo does NOT grant Growth-tier bullets
        $this->assertNotContains('products.view', $perms, 'Products catalogue moved to Growth');
        $this->assertNotContains('customer-statements.view', $perms);
        $this->assertNotContains('reports.sales-tax', $perms);
        $this->assertNotContains('reports.aged-reports', $perms);
        $this->assertNotContains('reports.balance-sheet', $perms);
        $this->assertNotContains('reports.cashflow', $perms);
    }

    public function test_growth_grants_match_bullets(): void
    {
        $perms = $this->permsFor('growth');

        // "Customer statements"
        $this->assertContains('customer-statements.view', $perms);
        // "Products & services catalogue"
        $this->assertContains('products.view', $perms);
        $this->assertContains('products.create', $perms);
        // "Balance sheet & cash flow reports" — replaced the old
        // "Multi-currency invoicing" bullet (June 2026), since multi
        // currency was never plan-gated and balance sheet / cash flow
        // ARE Growth-exclusive over Solo.
        $this->assertContains('reports.balance-sheet', $perms);
        $this->assertContains('reports.cashflow', $perms);
        // "Sales tax & ageing reports"
        $this->assertContains('reports.sales-tax', $perms);
        $this->assertContains('reports.aged-reports', $perms);

        // Growth does NOT grant Corporate-tier bullets
        $this->assertNotContains('payroll.run', $perms, 'Payroll is a Corporate bullet');
        $this->assertNotContains('myinvois.submit', $perms, 'MyInvois is a Corporate bullet');
        $this->assertNotContains('audit-logs.view', $perms, 'Audit log is a Corporate bullet');
    }

    /**
     * Regression: 'currencies.multi' was a dangling permission grant
     * — registered + granted to Growth+, but never enforced. It was
     * removed in June 2026 along with the "Multi-currency invoicing"
     * bullet. Lock the registry so it doesn't get accidentally
     * reintroduced without also wiring the matching middleware.
     */
    public function test_currencies_multi_permission_no_longer_exists(): void
    {
        $this->assertFalse(
            \App\Models\Permission::query()->where('name', 'currencies.multi')->exists(),
            'currencies.multi should not be in the registry. If you want to gate multi-currency, add the permission AND wire `plan.permission:currencies.multi` middleware on the invoice/estimate stores.'
        );
    }

    public function test_corporate_grants_match_bullets(): void
    {
        $perms = $this->permsFor('corporate');

        // "Audit log & compliance pack"
        $this->assertContains('audit-logs.view', $perms);
        // "Payroll module"
        $this->assertContains('payroll.run', $perms);
        // "LHDN MyInvois e-Invoicing — coming soon" (gate exists, controller doesn't)
        $this->assertContains('myinvois.submit', $perms);

        // Corporate does NOT grant Enterprise-only SSO
        $this->assertNotContains('sso.configure', $perms, 'SSO is Enterprise-only');
    }

    public function test_enterprise_grants_match_bullets(): void
    {
        $perms = $this->permsFor('enterprise');

        // "Single sign-on (SSO) — coming soon" (gate exists, controller doesn't)
        $this->assertContains('sso.configure', $perms);

        // Enterprise has the same operational surface as Corporate plus SSO,
        // so MyInvois and payroll are present too.
        $this->assertContains('myinvois.submit', $perms);
        $this->assertContains('payroll.run', $perms);
    }

    // ----- Permission registry is complete (no orphan grants) -----

    public function test_every_plan_permission_exists_in_the_registry(): void
    {
        $allRegistered = Permission::pluck('name')->all();

        foreach (['startup', 'solo', 'growth', 'corporate', 'enterprise'] as $slug) {
            foreach ($this->permsFor($slug) as $perm) {
                $this->assertContains(
                    $perm,
                    $allRegistered,
                    "$slug grants '$perm' but it was never registered in PlanSeeder::ensureAllPermissionsExist()."
                );
            }
        }
    }

    // ----- Coming-soon features still get a permission, even without a controller -----

    public function test_coming_soon_permissions_are_registered(): void
    {
        $names = Permission::pluck('name')->all();
        $this->assertContains('myinvois.submit', $names);
        $this->assertContains('sso.configure', $names);
    }

    // ----- Marketing copy on the seeded plans matches the decisions made -----

    public function test_corporate_plan_does_not_advertise_approval_workflows(): void
    {
        $plan = Plan::where('slug', 'corporate')->firstOrFail();
        foreach ($plan->features as $bullet) {
            $this->assertStringNotContainsStringIgnoringCase(
                'approval workflow',
                $bullet,
                "Corporate plan still advertises 'approval workflows' but the feature was removed."
            );
        }
    }

    public function test_enterprise_plan_marks_sso_as_coming_soon(): void
    {
        $plan = Plan::where('slug', 'enterprise')->firstOrFail();
        $matched = collect($plan->features)->first(fn ($b) => stripos($b, 'sso') !== false || stripos($b, 'single sign-on') !== false);
        $this->assertNotNull($matched, 'Enterprise plan must mention SSO somewhere.');
        $this->assertStringContainsStringIgnoringCase('coming soon', $matched);
    }

    public function test_corporate_plan_mentions_myinvois(): void
    {
        $plan = Plan::where('slug', 'corporate')->firstOrFail();
        $matched = collect($plan->features)->first(fn ($b) => stripos($b, 'myinvois') !== false);
        $this->assertNotNull($matched, 'Corporate plan must mention MyInvois.');
        $this->assertStringNotContainsStringIgnoringCase('coming soon', $matched);
    }

    public function test_enterprise_plan_marks_branding_self_hosted_only(): void
    {
        $plan = Plan::where('slug', 'enterprise')->firstOrFail();
        $matched = collect($plan->features)->first(fn ($b) => stripos($b, 'branding') !== false);
        $this->assertNotNull($matched, 'Enterprise plan must mention white-label branding.');
        $this->assertStringContainsStringIgnoringCase('self-hosted', $matched);
    }
}
