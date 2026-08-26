<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_codes')) {
            return;
        }

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->enum('type', ['standard', 'zero', 'exempt', 'out_of_scope'])->default('standard');
            $table->string('output_account_code', 10)->nullable();
            $table->string('input_account_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
