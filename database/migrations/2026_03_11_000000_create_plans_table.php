<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price_monthly', 10, 2)->default(0);
                $table->decimal('price_yearly', 10, 2)->default(0);
                $table->json('features')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Seed a single Pro plan if it doesn't exist
        if (DB::table('plans')->where('slug', 'pro')->doesntExist()) {
            DB::table('plans')->insert([
                'name' => 'Pro',
                'slug' => 'pro',
                'price_monthly' => 79.00,
                'price_yearly' => 790.00,
                'features' => json_encode([
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Priority support',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

