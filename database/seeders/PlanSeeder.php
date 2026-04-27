<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Startup (Free)
        \App\Models\Plan::updateOrCreate(
            ['slug' => 'startup'],
            [
                'name' => 'Startup (Free)',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Full dashboard & reports',
                    'Basic invoicing',
                    '1 User included',
                ],
                'is_active' => true,
            ]
        );

        // 2. SME (Renamed from Pro)
        // Check if 'pro' exists and update it, or create 'sme'
        $proPlan = \App\Models\Plan::where('slug', 'pro')->first();
        if ($proPlan) {
            $proPlan->update([
                'name' => 'SME',
                'slug' => 'sme',
                'price_monthly' => 79.00,
                'price_yearly' => 853.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Priority support',
                    '1 User included',
                ],
            ]);
        } else {
            \App\Models\Plan::updateOrCreate(
                ['slug' => 'sme'],
                [
                    'name' => 'SME',
                    'price_monthly' => 79.00,
                    'price_yearly' => 853.00,
                    'users_included' => 1,
                    'extra_user_price' => 0.00,
                    'features' => [
                        'Full dashboard & reports',
                        'Unlimited invoices & customers',
                        'Priority support',
                        '1 User included',
                    ],
                    'is_active' => true,
                ]
            );
        }

        // 3. Corporate
        \App\Models\Plan::updateOrCreate(
            ['slug' => 'corporate'],
            [
                'name' => 'Corporate',
                'price_monthly' => 199.00,
                'price_yearly' => 2149.00,
                'users_included' => 3,
                'extra_user_price' => 15.00,
                'features' => [
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Dedicated account manager',
                    '3 Users included',
                ],
                'is_active' => true,
            ]
        );
    }
}
