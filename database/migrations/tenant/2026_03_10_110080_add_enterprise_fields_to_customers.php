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
            if (!Schema::hasColumn('customers', 'credit_hold')) {
                $table->boolean('credit_hold')->default(false)->after('credit_limit');
            }
            if (!Schema::hasColumn('customers', 'risk_rating')) {
                $table->string('risk_rating')->nullable()->after('credit_hold'); // low, medium, high
            }
            if (!Schema::hasColumn('customers', 'segment')) {
                $table->string('segment')->nullable()->after('risk_rating'); // SME, Enterprise, Govt
            }
            if (!Schema::hasColumn('customers', 'region')) {
                $table->string('region')->nullable()->after('segment');
            }
            if (!Schema::hasColumn('customers', 'account_manager_id')) {
                // In tenant databases, store the ID only; users live in the central DB.
                $table->unsignedBigInteger('account_manager_id')->nullable()->after('region');
            }
            if (!Schema::hasColumn('customers', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('account_manager_id');
            }
            if (!Schema::hasColumn('customers', 'invoice_delivery_method')) {
                $table->string('invoice_delivery_method')->default('email')->after('internal_notes'); // email, none
            }
            if (!Schema::hasColumn('customers', 'send_statement')) {
                $table->boolean('send_statement')->default(false)->after('invoice_delivery_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
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

