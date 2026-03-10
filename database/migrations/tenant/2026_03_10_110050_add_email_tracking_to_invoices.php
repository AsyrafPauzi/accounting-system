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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'last_emailed_at')) {
                $table->timestamp('last_emailed_at')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('invoices', 'last_emailed_to')) {
                $table->string('last_emailed_to')->nullable()->after('last_emailed_at');
            }
            if (!Schema::hasColumn('invoices', 'last_emailed_status')) {
                $table->string('last_emailed_status')->nullable()->after('last_emailed_to');
            }
            if (!Schema::hasColumn('invoices', 'last_emailed_error')) {
                $table->text('last_emailed_error')->nullable()->after('last_emailed_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'last_emailed_at',
                'last_emailed_to',
                'last_emailed_status',
                'last_emailed_error',
            ]);
        });
    }
};

