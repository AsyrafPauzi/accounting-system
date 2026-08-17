<?php

namespace Tests\Unit\Copilot;

use App\Models\CopilotCreditBalance;
use App\Models\CopilotCreditLedger;
use App\Models\CopilotCreditPurchase;
use App\Services\Copilot\CopilotCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CopilotCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Service runs against the default connection in unit tests (no tenancy).
        Schema::create('copilot_credit_balances', function ($table) {
            $table->id();
            $table->unsignedInteger('included_remaining')->default(0);
            $table->unsignedInteger('purchased_remaining')->default(0);
            $table->unsignedInteger('included_quota')->default(0);
            $table->string('period_ym', 7);
            $table->unsignedInteger('included_used_this_month')->default(0);
            $table->timestamps();
        });
        Schema::create('copilot_credit_ledger', function ($table) {
            $table->id();
            $table->string('type', 40);
            $table->integer('delta_included')->default(0);
            $table->integer('delta_purchased')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_burn_uses_included_before_purchased(): void
    {
        $svc = app(CopilotCreditService::class);
        CopilotCreditBalance::query()->create([
            'included_remaining' => 2,
            'purchased_remaining' => 5,
            'included_quota' => 70,
            'period_ym' => $svc->currentPeriodYm(),
            'included_used_this_month' => 0,
        ]);

        $svc->burnOne();
        $svc->burnOne();
        $svc->burnOne();

        $b = CopilotCreditBalance::query()->first();
        $this->assertSame(0, (int) $b->included_remaining);
        $this->assertSame(4, (int) $b->purchased_remaining);
        $this->assertSame(2, (int) $b->included_used_this_month);
        $this->assertSame(3, CopilotCreditLedger::query()->where('type', 'burn')->count());
    }

    public function test_grant_purchased_never_resets_with_month(): void
    {
        $svc = app(CopilotCreditService::class);
        CopilotCreditBalance::query()->create([
            'included_remaining' => 0,
            'purchased_remaining' => 0,
            'included_quota' => 70,
            'period_ym' => '2020-01',
            'included_used_this_month' => 10,
        ]);

        $svc->grantPurchased(100);
        $b = $svc->ensurePeriod(null);

        $this->assertSame($svc->currentPeriodYm(), $b->period_ym);
        $this->assertSame(100, (int) $b->purchased_remaining);
        $this->assertSame(0, (int) $b->included_used_this_month);
    }

    public function test_pack_catalogue_matches_design(): void
    {
        $this->assertSame(50, CopilotCreditPurchase::PACKS['starter']['credits']);
        $this->assertSame(12.0, CopilotCreditPurchase::PACKS['starter']['amount']);
        $this->assertSame(100, CopilotCreditPurchase::PACKS['standard']['credits']);
        $this->assertSame(250, CopilotCreditPurchase::PACKS['power']['credits']);
    }
}
