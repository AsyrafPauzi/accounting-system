<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add LHDN MyInvois submission tracking columns to invoices.
     *
     * lhdn_status        — already exists (pending/submitted/valid/invalid/cancelled)
     * lhdn_uuid          — already exists (returned by LHDN on submission)
     * lhdn_submission_uid— the overall submission UID (batch reference from LHDN)
     * lhdn_long_id       — human-readable document long ID for QR code printing
     * lhdn_submitted_at  — when the invoice was submitted to LHDN
     * lhdn_error_message — last error message returned from LHDN API
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'lhdn_submission_uid')) {
                $table->string('lhdn_submission_uid')->nullable()->after('lhdn_uuid');
            }
            if (!Schema::hasColumn('invoices', 'lhdn_long_id')) {
                $table->string('lhdn_long_id')->nullable()->after('lhdn_submission_uid');
            }
            if (!Schema::hasColumn('invoices', 'lhdn_submitted_at')) {
                $table->timestamp('lhdn_submitted_at')->nullable()->after('lhdn_long_id');
            }
            if (!Schema::hasColumn('invoices', 'lhdn_error_message')) {
                $table->text('lhdn_error_message')->nullable()->after('lhdn_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'lhdn_submission_uid',
                'lhdn_long_id',
                'lhdn_submitted_at',
                'lhdn_error_message',
            ]);
        });
    }
};
