<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_settings', function (Blueprint $table) {
            $table->id();

            // 'disabled' | 'tesseract' | 'gemini'
            // Default 'tesseract' — works out of the box because the Dockerfile
            // installs the tesseract binary + eng/msa language packs. If Tesseract
            // is unavailable at runtime, the resolver gracefully falls back to
            // NullProvider and logs a warning, so receipt uploads keep working.
            $table->string('provider', 20)->default('tesseract');

            // Encrypted via Crypt::encryptString — never store plaintext.
            $table->text('gemini_api_key')->nullable();

            $table->string('gemini_model', 50)->default('gemini-1.5-flash');

            // '+' joined Tesseract language codes. 'eng+msa' covers English and Bahasa Malaysia.
            $table->string('tesseract_languages', 100)->default('eng+msa');

            $table->unsignedInteger('max_image_mb')->default(10);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_settings');
    }
};
