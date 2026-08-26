<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_number_settings')) {
            return;
        }

        Schema::create('document_number_settings', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 40)->unique();
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('pad_width')->default(4);
            $table->boolean('reset_on_fy')->default(false);
            $table->unsignedSmallInteger('last_fy_start_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_settings');
    }
};
