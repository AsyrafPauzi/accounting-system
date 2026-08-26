<?php

namespace App\Support;

use App\Models\TaxCode;
use Illuminate\Support\Facades\Schema;

final class TaxCodeDefaults
{
    public static function seedMissing(): void
    {
        if (! Schema::hasTable('tax_codes')) {
            return;
        }

        $defaults = [
            ['code' => 'SR-8', 'name' => 'Standard rated 8%', 'rate' => 8, 'type' => 'standard', 'output_account_code' => '2100', 'input_account_code' => '1110'],
            ['code' => 'ST-10', 'name' => 'Standard rated 10%', 'rate' => 10, 'type' => 'standard', 'output_account_code' => '2100', 'input_account_code' => '1110'],
            ['code' => 'ES', 'name' => 'Exempt supply', 'rate' => 0, 'type' => 'exempt', 'output_account_code' => null, 'input_account_code' => null],
            ['code' => 'ZRL', 'name' => 'Zero rated', 'rate' => 0, 'type' => 'zero', 'output_account_code' => null, 'input_account_code' => null],
        ];

        foreach ($defaults as $row) {
            TaxCode::query()->firstOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true]
            );
        }
    }
}
