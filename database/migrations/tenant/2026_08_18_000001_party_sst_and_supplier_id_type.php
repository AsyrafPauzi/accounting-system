<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'sst_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('sst_number', 50)->nullable()->after('identification_type');
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'identification_type')) {
                    $table->string('identification_type', 16)->nullable()->after('brn');
                }
                if (! Schema::hasColumn('suppliers', 'sst_number')) {
                    $table->string('sst_number', 50)->nullable()->after('identification_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'sst_number')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('sst_number');
            });
        }
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('suppliers', 'sst_number')) {
                    $table->dropColumn('sst_number');
                }
                if (Schema::hasColumn('suppliers', 'identification_type')) {
                    $table->dropColumn('identification_type');
                }
            });
        }
    }
};
