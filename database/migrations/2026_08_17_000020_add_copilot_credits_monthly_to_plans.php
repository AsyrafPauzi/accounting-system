<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'copilot_credits_monthly')) {
                $table->unsignedInteger('copilot_credits_monthly')->default(0)->after('extra_user_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'copilot_credits_monthly')) {
                $table->dropColumn('copilot_credits_monthly');
            }
        });
    }
};
