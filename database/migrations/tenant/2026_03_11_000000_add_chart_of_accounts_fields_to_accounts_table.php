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
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('accounts')->nullOnDelete();
            $table->text('description')->nullable()->after('parent_id');
            $table->boolean('is_active')->default(true)->after('description');
            $table->unsignedInteger('display_order')->nullable()->after('is_active');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'description', 'is_active', 'display_order']);
        });
    }
};
