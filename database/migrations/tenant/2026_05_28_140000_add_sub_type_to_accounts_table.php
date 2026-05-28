<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a fine-grained classification on top of `type` so we can pick out
     * bank/cash accounts for receipt and payment dropdowns.
     *
     * Values:
     *   - 'bank' : physical/virtual bank accounts (e.g. Maybank, Hong Leong, CIMB)
     *   - 'cash' : cash on hand / petty cash floats
     *   - null   : not a bank/cash account (default for everything else)
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('sub_type', 32)->nullable()->after('type');
            $table->index(['type', 'sub_type']);
        });

        DB::table('accounts')
            ->where('type', 'asset')
            ->whereNull('sub_type')
            ->where(function ($q) {
                $q->where('name', 'like', '%bank%')
                    ->orWhere('name', 'like', '%Bank%')
                    ->orWhere('name', 'like', '%BANK%');
            })
            ->update(['sub_type' => 'bank']);

        DB::table('accounts')
            ->where('type', 'asset')
            ->whereNull('sub_type')
            ->where(function ($q) {
                $q->where('name', 'like', '%cash%')
                    ->orWhere('name', 'like', '%Cash%')
                    ->orWhere('name', 'like', '%CASH%')
                    ->orWhere('name', 'like', '%petty%')
                    ->orWhere('name', 'like', '%Petty%');
            })
            ->update(['sub_type' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['type', 'sub_type']);
            $table->dropColumn('sub_type');
        });
    }
};
