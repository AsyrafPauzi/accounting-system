<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedSmallInteger('fiscal_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('fiscal_year');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 20);
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['budget_id', 'account_code', 'month']);
            $table->index(['budget_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
