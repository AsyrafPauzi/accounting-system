<?php

namespace App\Services\Copilot;

use App\Models\CopilotCreditBalance;
use App\Models\CopilotCreditLedger;
use App\Models\CopilotCreditPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Deployment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CopilotCreditService
{
    public function meteringEnabled(): bool
    {
        if (Deployment::isSelfHosted()) {
            return false;
        }

        return Schema::hasTable('copilot_credit_balances');
    }

    public function currentPeriodYm(?Carbon $now = null): string
    {
        return ($now ?? now('Asia/Kuala_Lumpur'))->format('Y-m');
    }

    public function resetsOn(?Carbon $now = null): string
    {
        return ($now ?? now('Asia/Kuala_Lumpur'))->copy()->startOfMonth()->addMonth()->toDateString();
    }

    public function quotaForTenant(?Tenant $tenant): int
    {
        if (! $tenant) {
            return 0;
        }

        $plan = $tenant->activeSubscription()?->plan;
        if (! $plan) {
            return 0;
        }

        if (! $plan->hasPermission('copilot.use')) {
            return 0;
        }

        return (int) ($plan->copilot_credits_monthly ?? 0);
    }

    /**
     * @return array{remaining: int, included: int, purchased: int, quota: int, used_this_month: int, resets_on: string, metering: bool}
     */
    public function snapshot(?Tenant $tenant = null): array
    {
        if (! $this->meteringEnabled()) {
            return [
                'remaining' => null,
                'included' => null,
                'purchased' => null,
                'quota' => null,
                'used_this_month' => null,
                'resets_on' => null,
                'metering' => false,
            ];
        }

        $tenant = $tenant ?? (function_exists('tenant') ? tenant() : null);
        $balance = $this->ensurePeriod($tenant);

        return [
            'remaining' => $balance->remaining(),
            'included' => (int) $balance->included_remaining,
            'purchased' => (int) $balance->purchased_remaining,
            'quota' => (int) $balance->included_quota,
            'used_this_month' => (int) $balance->included_used_this_month,
            'resets_on' => $this->resetsOn(),
            'metering' => true,
            'packs' => collect(CopilotCreditPurchase::PACKS)->map(fn ($p, $slug) => [
                'slug' => $slug,
                'credits' => $p['credits'],
                'amount' => $p['amount'],
                'label' => $p['label'],
            ])->values()->all(),
        ];
    }

    public function ensurePeriod(?Tenant $tenant = null): CopilotCreditBalance
    {
        $period = $this->currentPeriodYm();
        $quota = $this->quotaForTenant($tenant ?? (function_exists('tenant') ? tenant() : null));

        $balance = CopilotCreditBalance::query()->first();
        if (! $balance) {
            $balance = CopilotCreditBalance::query()->create([
                'included_remaining' => $quota,
                'purchased_remaining' => 0,
                'included_quota' => $quota,
                'period_ym' => $period,
                'included_used_this_month' => 0,
            ]);

            if ($quota > 0) {
                CopilotCreditLedger::query()->create([
                    'type' => CopilotCreditLedger::TYPE_GRANT_INCLUDED,
                    'delta_included' => $quota,
                    'delta_purchased' => 0,
                    'meta' => ['period_ym' => $period],
                ]);
            }

            return $balance;
        }

        if ($balance->period_ym === $period) {
            // Plan upgrade mid-month: raise quota and top up the delta once.
            if ($quota > (int) $balance->included_quota) {
                $delta = $quota - (int) $balance->included_quota;
                $balance->included_quota = $quota;
                $balance->included_remaining = (int) $balance->included_remaining + $delta;
                $balance->save();
                CopilotCreditLedger::query()->create([
                    'type' => CopilotCreditLedger::TYPE_GRANT_INCLUDED,
                    'delta_included' => $delta,
                    'delta_purchased' => 0,
                    'meta' => ['period_ym' => $period, 'reason' => 'plan_quota_increase'],
                ]);
            } elseif ($quota !== (int) $balance->included_quota) {
                $balance->included_quota = $quota;
                $balance->save();
            }

            return $balance->fresh();
        }

        // New calendar month: reset included; keep purchased forever.
        $balance->update([
            'included_remaining' => $quota,
            'included_quota' => $quota,
            'period_ym' => $period,
            'included_used_this_month' => 0,
        ]);

        if ($quota > 0) {
            CopilotCreditLedger::query()->create([
                'type' => CopilotCreditLedger::TYPE_GRANT_INCLUDED,
                'delta_included' => $quota,
                'delta_purchased' => 0,
                'meta' => ['period_ym' => $period],
            ]);
        }

        return $balance->fresh();
    }

    /**
     * @throws RuntimeException when no credits left
     */
    public function burnOne(?User $user = null): CopilotCreditBalance
    {
        if (! $this->meteringEnabled()) {
            return new CopilotCreditBalance([
                'included_remaining' => 0,
                'purchased_remaining' => 0,
                'included_quota' => 0,
                'period_ym' => $this->currentPeriodYm(),
            ]);
        }

        return DB::transaction(function () use ($user) {
            $tenant = function_exists('tenant') ? tenant() : null;
            $balance = $this->ensurePeriod($tenant);
            $balance = CopilotCreditBalance::query()->lockForUpdate()->findOrFail($balance->id);

            if ($balance->remaining() < 1) {
                throw new RuntimeException('No copilot credits remaining. Buy more from Plan & Usage.');
            }

            $deltaIncluded = 0;
            $deltaPurchased = 0;
            if ((int) $balance->included_remaining > 0) {
                $balance->included_remaining = (int) $balance->included_remaining - 1;
                $balance->included_used_this_month = (int) $balance->included_used_this_month + 1;
                $deltaIncluded = -1;
            } else {
                $balance->purchased_remaining = (int) $balance->purchased_remaining - 1;
                $deltaPurchased = -1;
            }
            $balance->save();

            CopilotCreditLedger::query()->create([
                'type' => CopilotCreditLedger::TYPE_BURN,
                'delta_included' => $deltaIncluded,
                'delta_purchased' => $deltaPurchased,
                'user_id' => $user?->id,
                'meta' => ['period_ym' => $balance->period_ym],
            ]);

            return $balance->fresh();
        });
    }

    public function refundOne(?User $user = null, string $reason = 'pre_provider_failure'): void
    {
        if (! $this->meteringEnabled()) {
            return;
        }

        DB::transaction(function () use ($user, $reason) {
            $balance = CopilotCreditBalance::query()->lockForUpdate()->first();
            if (! $balance) {
                return;
            }

            // Prefer restoring included if we still have room under quota usage tracking.
            if ((int) $balance->included_used_this_month > 0
                && (int) $balance->included_remaining < (int) $balance->included_quota) {
                $balance->included_remaining = (int) $balance->included_remaining + 1;
                $balance->included_used_this_month = max(0, (int) $balance->included_used_this_month - 1);
                $balance->save();
                CopilotCreditLedger::query()->create([
                    'type' => CopilotCreditLedger::TYPE_REFUND,
                    'delta_included' => 1,
                    'delta_purchased' => 0,
                    'user_id' => $user?->id,
                    'meta' => ['reason' => $reason],
                ]);

                return;
            }

            $balance->purchased_remaining = (int) $balance->purchased_remaining + 1;
            $balance->save();
            CopilotCreditLedger::query()->create([
                'type' => CopilotCreditLedger::TYPE_REFUND,
                'delta_included' => 0,
                'delta_purchased' => 1,
                'user_id' => $user?->id,
                'meta' => ['reason' => $reason],
            ]);
        });
    }

    public function grantPurchased(int $credits, ?User $user = null, ?int $purchaseId = null): CopilotCreditBalance
    {
        if ($credits < 1) {
            throw new RuntimeException('Credits must be positive.');
        }

        return DB::transaction(function () use ($credits, $user, $purchaseId) {
            $tenant = function_exists('tenant') ? tenant() : null;
            $balance = $this->ensurePeriod($tenant);
            $balance = CopilotCreditBalance::query()->lockForUpdate()->findOrFail($balance->id);
            $balance->purchased_remaining = (int) $balance->purchased_remaining + $credits;
            $balance->save();

            CopilotCreditLedger::query()->create([
                'type' => CopilotCreditLedger::TYPE_PURCHASE,
                'delta_included' => 0,
                'delta_purchased' => $credits,
                'user_id' => $user?->id,
                'reference_type' => $purchaseId ? CopilotCreditPurchase::class : null,
                'reference_id' => $purchaseId,
            ]);

            return $balance->fresh();
        });
    }
}
