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
        $emails = [
            'admin@hirix.ai',
            'corporate@accounter.com',
            'sme@accounter.com'
        ];

        // Find the Corporate plan - this is the "full access" plan
        $plan = Plan::where('slug', 'corporate')->first();

        if (!$plan) {
            $this->command->error('Corporate plan not found! Please run PlanSeeder first.');
            return;
        }

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Apply the Corporate plan with a Lifetime duration
                Subscription::updateOrCreate(
                    ['tenant_id' => $user->tenant_id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'interval' => 'lifetime', // Triggers "Lifetime Access" in UI
                        'current_period_start' => now(),
                        'current_period_ends_at' => null, // Permanent access
                        'gateway' => 'system'
                    ]
                );
                $this->command->info("Activated Lifetime Corporate for: $email");
            } else {
                $this->command->warn("User not found: $email");
            }
        }
    }
}
