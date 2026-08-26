<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ocr_jobs')) {
            return;
        }

        Schema::create('ocr_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed', 'confirmed', 'discarded'])->default('pending');
            $table->json('parsed_data')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_jobs');
    }
};
