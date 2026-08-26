<?php

namespace Tests\Feature\Assets;

use App\Models\AccountingPeriod;
use App\Models\FixedAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\FixedAssetService;
use App\Support\AccountingPeriodResolver;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FixedAssetService $assets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($this->tenant);
        AccountingPeriodResolver::ensurePeriodsExist();
        $this->assets = app(FixedAssetService::class);
    }

    public function test_straight_line_depreciation_over_twelve_months(): void
    {
        $asset = $this->assets->register([
            'asset_number'       => 'FA-TEST-001',
            'name'               => 'Office laptop',
            'purchase_date'      => '2026-01-15',
            'cost'               => 1200,
            'salvage_value'      => 0,
            'useful_life_months' => 12,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $monthEnd = Carbon::parse("2026-{$i}-01")->endOfMonth()->toDateString();
            $this->assets->depreciateMonth($asset->fresh(), $monthEnd);
        }

        $asset->refresh();
        $this->assertSame(1200.0, (float) $asset->accumulated_depreciation);
        $this->assertSame(0.0, $asset->netBookValue());
        $this->assertSame(12, DB::table('journal_entries')->where('reference_type', 'Fixed Asset Depreciation')->count());
    }

    public function test_disposal_posts_balanced_journal_with_gain(): void
    {
        $asset = $this->assets->register([
            'asset_number'       => 'FA-DISP-001',
            'name'               => 'Old desk',
            'purchase_date'      => '2026-01-01',
            'cost'               => 600,
            'salvage_value'      => 0,
            'useful_life_months' => 12,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $this->assets->depreciateMonth($asset->fresh(), Carbon::parse("2026-{$i}-01")->endOfMonth()->toDateString());
        }

        $this->assets->dispose($asset->fresh(), 400.0, '2026-07-15');

        $this->assertSame('disposed', $asset->fresh()->status);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'Fixed Asset Disposal',
            'reference_id'   => $asset->id,
            'status'         => 'posted',
        ]);

        $gainCredit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Fixed Asset Disposal')
            ->where('journal_entries.reference_id', $asset->id)
            ->where('journal_items.account_code', '4200')
            ->value('journal_items.credit');

        $this->assertSame(100.0, $gainCredit);
    }

    public function test_depreciation_in_closed_period_is_rejected(): void
    {
        $monthEnd = now()->endOfMonth()->toDateString();
        AccountingPeriod::query()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthEnd)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $asset = $this->assets->register([
            'name'               => 'Locked asset',
            'purchase_date'      => now()->toDateString(),
            'cost'               => 500,
            'useful_life_months' => 10,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('closed');
        $this->assets->depreciateMonth($asset, $monthEnd);
    }
}
