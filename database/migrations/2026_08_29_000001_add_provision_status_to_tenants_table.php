<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'provision_status')) {
                $table->enum('provision_status', ['pending', 'provisioning', 'ready', 'failed'])
                    ->default('pending')
                    ->after('id');
            }
            if (! Schema::hasColumn('tenants', 'provision_error')) {
                $table->text('provision_error')->nullable()->after('provision_status');
            }
            if (! Schema::hasColumn('tenants', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable()->after('provision_error');
            }
        });

        // Existing tenants were provisioned synchronously before this migration.
        DB::table('tenants')->update([
            'provision_status' => 'ready',
            'provisioned_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'provisioned_at')) {
                $table->dropColumn('provisioned_at');
            }
            if (Schema::hasColumn('tenants', 'provision_error')) {
                $table->dropColumn('provision_error');
            }
            if (Schema::hasColumn('tenants', 'provision_status')) {
                $table->dropColumn('provision_status');
            }
        });
    }
};
