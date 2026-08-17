<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('copilot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->constrained('copilot_threads')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content')->nullable();
            $table->json('tool_traces')->nullable();
            $table->timestamps();
        });

        Schema::create('copilot_pending_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_thread_id')->constrained('copilot_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->index();
            $table->string('tool_name', 80);
            $table->string('risk', 20);
            $table->json('payload');
            $table->string('summary')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_pending_actions');
        Schema::dropIfExists('copilot_messages');
        Schema::dropIfExists('copilot_threads');
    }
};
