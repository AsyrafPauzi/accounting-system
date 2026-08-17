<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (! Schema::hasColumn('bills', 'lhdn_status')) {
                    $table->string('lhdn_status')->default('pending');
                }
                if (! Schema::hasColumn('bills', 'lhdn_uuid')) {
                    $table->string('lhdn_uuid')->nullable();
                }
                if (! Schema::hasColumn('bills', 'lhdn_long_id')) {
                    $table->string('lhdn_long_id', 80)->nullable();
                }
                if (! Schema::hasColumn('bills', 'lhdn_submitted_at')) {
                    $table->timestamp('lhdn_submitted_at')->nullable();
                }
                if (! Schema::hasColumn('bills', 'lhdn_cancelled_at')) {
                    $table->timestamp('lhdn_cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('bills', 'lhdn_reject_reason')) {
                    $table->text('lhdn_reject_reason')->nullable();
                }
                if (! Schema::hasColumn('bills', 'lhdn_qr_url')) {
                    $table->string('lhdn_qr_url', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Keep LHDN columns.
    }
};
