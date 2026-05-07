<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class TestingAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            'admin@hirix.ai' => 'corporate',
            'corporate@accounter.com' => 'corporate',
            'sme@accounter.com' => 'sme'
        ];

        foreach ($accounts as $email => $planSlug) {
            $user = User::where('email', $email)->first();
            $plan = Plan::where('slug', $planSlug)->first();

            if (!$plan) {
                $this->command->error("Plan $planSlug not found for $email.");
                continue;
            }

            if ($user) {
                if (!$user->tenant_id) {
                    $this->command->warn("User $email has no tenant_id. Skipping.");
                    continue;
                }

                Subscription::updateOrCreate(
                    ['tenant_id' => $user->tenant_id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'interval' => 'lifetime',
                        'current_period_start' => now(),
                        'current_period_ends_at' => null, // No expiry
                        'gateway' => 'system'
                    ]
                );

                $this->command->info("Updated $email to " . ucfirst($planSlug) . " Lifetime Access.");
            } else {
                $this->command->warn("User not found: $email");
            }
        }
    }
}
