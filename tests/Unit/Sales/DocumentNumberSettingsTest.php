<?php

namespace Tests\Unit\Sales;

use App\Models\DocumentNumberSetting;
use App\Support\DocumentNumber;
use App\Support\DocumentNumberDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTestTenants;
use Tests\TestCase;

class DocumentNumberSettingsTest extends TestCase
{
    use CreatesTestTenants;
    use RefreshDatabase;

    public function test_uses_tenant_prefix_and_next_number_from_settings(): void
    {
        $tenant = $this->createTenantWithDatabase();
        tenancy()->initialize($tenant);

        DocumentNumberDefaults::seedMissing();
        DocumentNumberSetting::query()->where('doc_type', 'invoice')->update([
            'prefix'      => 'ABC',
            'next_number' => 42,
            'pad_width'   => 4,
        ]);

        $number = DocumentNumber::next('invoices', 'invoice_number', 'INV');

        $this->assertSame('ABC-0042', $number);
        $this->assertSame(43, (int) DocumentNumberSetting::query()->where('doc_type', 'invoice')->value('next_number'));
    }
}
