<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 50)->nullable();
            $table->string('name', 150);
            $table->string('nric', 20)->nullable();
            $table->string('epf_number', 20)->nullable();
            $table->string('tax_category', 10)->default('1');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('payroll_employee_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_salary', 15, 2);
            $table->decimal('employee_epf', 15, 2)->default(0);
            $table->decimal('employer_epf', 15, 2)->default(0);
            $table->decimal('employee_socso', 15, 2)->default(0);
            $table->decimal('employer_socso', 15, 2)->default(0);
            $table->decimal('employee_eis', 15, 2)->default(0);
            $table->decimal('employer_eis', 15, 2)->default(0);
            $table->decimal('pcb', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['journal_entry_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_lines');
        Schema::dropIfExists('employees');
    }
};
