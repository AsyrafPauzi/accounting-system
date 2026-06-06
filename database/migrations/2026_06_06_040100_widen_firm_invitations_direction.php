<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch — `firm_invitations.direction` was 16 chars in the parent
 * migration, which truncates the longer of our two enum values
 * (`firm_invites_client` is 19). Widen to 32 so we have headroom for
 * future direction values and so existing rows don't silently break.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firm_invitations', function (Blueprint $table) {
            $table->string('direction', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('firm_invitations', function (Blueprint $table) {
            $table->string('direction', 16)->change();
        });
    }
};
