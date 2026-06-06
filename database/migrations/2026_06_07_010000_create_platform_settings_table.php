<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiny key/value store for platform-level mutable settings the SaaS
 * super-admin (or a self-hosted instance's super-admin) wants to
 * change at runtime without editing config files.
 *
 * v1 keys:
 *   - latest_release_version  → broadcast to every self-hosted install via heartbeat
 *   - update_notes            → human-readable changelog summary shown in the banner
 *   - latest_release_url      → optional link to docs / docker-compose update guide
 *
 * Why a table and not config? Config is read-only at runtime in
 * production (especially with `php artisan config:cache`). We need
 * the broadcaster to land instantly without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
