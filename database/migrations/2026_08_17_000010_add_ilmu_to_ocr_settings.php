<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocr_settings', function (Blueprint $table) {
            $table->text('ilmu_api_key')->nullable()->after('gemini_model');
            $table->string('ilmu_model', 50)->default('ilmu-v3.1')->after('ilmu_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ocr_settings', function (Blueprint $table) {
            $table->dropColumn(['ilmu_api_key', 'ilmu_model']);
        });
    }
};
