<?php

namespace App\Support;

use App\Models\DocumentNumberSetting;
use Illuminate\Support\Facades\Schema;

final class DocumentNumberDefaults
{
    public static function seedMissing(): void
    {
        if (! Schema::hasTable('document_number_settings')) {
            return;
        }

        $now = now();
        foreach (DocumentNumberSetting::docTypeCatalog() as $docType => $meta) {
            if (DocumentNumberSetting::query()->where('doc_type', $docType)->exists()) {
                continue;
            }

            DocumentNumberSetting::create([
                'doc_type'    => $docType,
                'prefix'      => $meta['prefix'],
                'next_number' => 1,
                'pad_width'   => 4,
                'reset_on_fy' => false,
            ]);
        }
    }
}
