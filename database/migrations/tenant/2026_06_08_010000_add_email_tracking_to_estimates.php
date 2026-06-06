<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the invoice email-tracking columns onto estimates so the
 * EstimateController@email flow can record send / failure status the
 * same way the invoice flow already does. Columns are nullable
 * because the vast majority of historical estimates will never have
 * been emailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('estimates', 'last_emailed_at')) {
                $table->timestamp('last_emailed_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('estimates', 'last_emailed_to')) {
                $table->string('last_emailed_to')->nullable()->after('last_emailed_at');
            }
            if (! Schema::hasColumn('estimates', 'last_emailed_status')) {
                $table->string('last_emailed_status')->nullable()->after('last_emailed_to');
            }
            if (! Schema::hasColumn('estimates', 'last_emailed_error')) {
                $table->text('last_emailed_error')->nullable()->after('last_emailed_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $cols = ['last_emailed_at', 'last_emailed_to', 'last_emailed_status', 'last_emailed_error'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('estimates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
