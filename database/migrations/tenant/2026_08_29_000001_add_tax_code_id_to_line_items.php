<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'invoice_items',
        'bill_items',
        'credit_note_items',
        'debit_note_items',
        'supplier_credit_note_items',
        'supplier_debit_note_items',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tax_codes')) {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tax_code_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $column = $blueprint->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
                if (Schema::hasColumn($table, 'tax_rate')) {
                    $column->after('tax_rate');
                }
            });
        }

        $this->backfillTaxCodes();
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tax_code_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tax_code_id');
            });
        }
    }

    private function backfillTaxCodes(): void
    {
        $codesByRate = DB::table('tax_codes')
            ->where('is_active', true)
            ->get()
            ->groupBy(fn ($row) => (string) (float) $row->rate)
            ->map(fn ($group) => $group->first());

        $mapRate = function (?float $rate) use ($codesByRate): ?int {
            $r = (float) ($rate ?? 0);
            if ($r >= 9.5) {
                return isset($codesByRate['10']) ? (int) $codesByRate['10']->id : null;
            }
            if ($r >= 7.5) {
                return isset($codesByRate['8']) ? (int) $codesByRate['8']->id : null;
            }

            return isset($codesByRate['0']) ? (int) $codesByRate['0']->id : null;
        };

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tax_code_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('tax_code_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $mapRate): void {
                    foreach ($rows as $row) {
                        $codeId = $mapRate((float) ($row->tax_rate ?? 0));
                        if ($codeId) {
                            DB::table($table)->where('id', $row->id)->update(['tax_code_id' => $codeId]);
                        }
                    }
                });
        }
    }
};
