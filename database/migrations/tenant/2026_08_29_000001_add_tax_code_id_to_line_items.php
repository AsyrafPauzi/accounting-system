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
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('tax_codes')) {
            return;
        }

        foreach ($this->tables as $table) {
            if (! $schema->hasTable($table) || $schema->hasColumn($table, 'tax_code_id')) {
                continue;
            }

            $hasTaxRate = $schema->hasColumn($table, 'tax_rate');

            $schema->table($table, function (Blueprint $blueprint) use ($hasTaxRate): void {
                $column = $blueprint->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
                if ($hasTaxRate) {
                    $column->after('tax_rate');
                }
            });
        }

        $this->backfillTaxCodes();
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        foreach ($this->tables as $table) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'tax_code_id')) {
                continue;
            }

            $schema->table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tax_code_id');
            });
        }
    }

    private function backfillTaxCodes(): void
    {
        $connection = DB::connection($this->getConnection());
        $schema = Schema::connection($this->getConnection());

        $codesByRate = $connection->table('tax_codes')
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
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'tax_code_id')) {
                continue;
            }

            $connection->table($table)
                ->whereNull('tax_code_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $mapRate, $connection): void {
                    foreach ($rows as $row) {
                        $codeId = $mapRate((float) ($row->tax_rate ?? 0));
                        if ($codeId) {
                            $connection->table($table)->where('id', $row->id)->update(['tax_code_id' => $codeId]);
                        }
                    }
                });
        }
    }
};
