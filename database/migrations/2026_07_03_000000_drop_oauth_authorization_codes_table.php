<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('oauth_authorization_codes');
    }

    public function down(): void
    {
        // OAuth flow removed; table is not recreated.
    }
};
