<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'identification_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('identification_type', 16)->nullable()->after('brn');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'identification_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('identification_type');
            });
        }
    }
};
