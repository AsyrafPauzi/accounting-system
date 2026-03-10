<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('credit_hold')->default(false)->after('credit_limit');
            $table->string('risk_rating')->nullable()->after('credit_hold'); // low, medium, high
            $table->string('segment')->nullable()->after('risk_rating'); // SME, Enterprise, Govt
            $table->string('region')->nullable()->after('segment');
            $table->foreignId('account_manager_id')->nullable()->after('region')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->after('account_manager_id');
            $table->string('invoice_delivery_method')->default('email')->after('internal_notes'); // email, none
            $table->boolean('send_statement')->default(false)->after('invoice_delivery_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['account_manager_id']);
            $table->dropColumn([
                'credit_hold',
                'risk_rating',
                'segment',
                'region',
                'account_manager_id',
                'parent_id',
                'invoice_delivery_method',
                'send_statement',
            ]);
        });
    }
};
