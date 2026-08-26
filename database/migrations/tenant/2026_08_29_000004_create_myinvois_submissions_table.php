<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myinvois_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->json('request_json');
            $table->json('response_json')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('lhdn_uuid')->nullable();
            $table->string('status');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myinvois_submissions');
    }
};
