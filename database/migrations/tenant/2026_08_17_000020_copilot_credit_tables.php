<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('included_remaining')->default(0);
            $table->unsignedInteger('purchased_remaining')->default(0);
            $table->unsignedInteger('included_quota')->default(0);
            $table->string('period_ym', 7)->index(); // Asia/KL calendar month, e.g. 2026-08
            $table->unsignedInteger('included_used_this_month')->default(0);
            $table->timestamps();
        });

        Schema::create('copilot_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->integer('delta_included')->default(0);
            $table->integer('delta_purchased')->default(0);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_credit_ledger');
        Schema::dropIfExists('copilot_credit_balances');
    }
};
