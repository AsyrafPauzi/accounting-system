<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_renewals')) {
            return;
        }

        Schema::create('subscription_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('interval', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 40)->default('billplz');
            $table->string('gateway_bill_id', 80)->nullable()->unique();
            $table->string('payment_url', 500)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewals');
    }
};
