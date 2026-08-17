<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'purchase_kind')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('purchase_kind', 16)->default('credit')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'purchase_kind')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('purchase_kind');
            });
        }
    }
};
