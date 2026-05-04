<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update SME Account
        $smeUser = User::where('email', 'sme@accounter.com')->first();
        $smePlan = Plan::where('slug', 'sme')->first();

        if ($smeUser && $smePlan && $smeUser->tenant_id) {
            Subscription::updateOrCreate(
                ['tenant_id' => $smeUser->tenant_id],
                [
                    'plan_id' => $smePlan->id,
                    'status' => 'active',
                    'interval' => 'monthly',
                    'current_period_start' => now()->toDateString(),
                    'current_period_ends_at' => now()->addMonth()->toDateString(),
                    'gateway' => 'system',
                ]
            );
        }

        // 2. Update Corporate Account
        $corpUser = User::where('email', 'corporate@accounter.com')->first();
        $corpPlan = Plan::where('slug', 'corporate')->first();

        if ($corpUser && $corpPlan && $corpUser->tenant_id) {
            Subscription::updateOrCreate(
                ['tenant_id' => $corpUser->tenant_id],
                [
                    'plan_id' => $corpPlan->id,
                    'status' => 'active',
                    'interval' => 'monthly',
                    'current_period_start' => now()->toDateString(),
                    'current_period_ends_at' => now()->addMonth()->toDateString(),
                    'gateway' => 'system',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for this data patch
    }
};
