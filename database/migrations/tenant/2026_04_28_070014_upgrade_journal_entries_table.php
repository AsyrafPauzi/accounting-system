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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('id');
            $table->enum('type', ['manual', 'system'])->default('system')->after('description');
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft')->after('type');
            $table->string('reference_type')->nullable()->change();
            $table->unsignedBigInteger('reference_id')->nullable()->change();
        });

        Schema::table('journal_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('journal_entry_id')->constrained('accounts')->nullOnDelete();
            $table->string('description')->nullable()->after('credit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'description']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['reference_number', 'type', 'status']);
            $table->string('reference_type')->nullable(false)->change();
            $table->unsignedBigInteger('reference_id')->nullable(false)->change();
        });
    }
};
