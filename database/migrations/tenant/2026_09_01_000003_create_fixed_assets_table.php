<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            return;
        }

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->date('purchase_date');
            $table->decimal('cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->date('last_depreciated_month')->nullable();
            $table->enum('status', ['active', 'disposed'])->default('active');
            $table->date('disposed_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->string('asset_account_code', 20)->default('1500');
            $table->string('accum_dep_account_code', 20)->default('1510');
            $table->string('dep_expense_account_code', 20)->default('5810');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
