<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('plan_id')->constrained('plans');
                $table->string('status')->default('active'); // active, trialing, past_due, canceled, expired
                $table->string('interval')->default('monthly'); // monthly, yearly
                $table->date('current_period_start')->nullable();
                $table->date('current_period_ends_at')->nullable();
                $table->string('gateway')->nullable(); // mock, stripe, etc.
                $table->string('gateway_subscription_id')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

