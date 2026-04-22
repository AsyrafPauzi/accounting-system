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
            // Drop the foreign key constraint that references the central 'users' table.
            // The column 'created_by' will remain to track the user ID logically.
            if (Schema::hasColumn('invoices', 'created_by')) {
                // Check if the foreign key exists before dropping it to prevent migration errors.
                $conn = Schema::getConnection();
                $db = $conn->getDatabaseName();
                
                // Direct index check for MySQL
                $foreignKeys = $conn->select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = '$db' 
                    AND TABLE_NAME = 'invoices' 
                    AND CONSTRAINT_NAME = 'invoices_created_by_foreign'
                ");

                if (!empty($foreignKeys)) {
                    $table->dropForeign(['created_by']);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for down migration as the central users architecture remains.
    }
};
