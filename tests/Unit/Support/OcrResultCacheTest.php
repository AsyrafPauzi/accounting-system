<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\OcrResultCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production uses CACHE_STORE=database. Stancl wraps Cache::has/get/put
 * with tags, and the database/file drivers do not support tagging — which
 * is what made GET /bills/ocr-status return 500 after a successful upload.
 *
 * phpunit.xml uses CACHE_STORE=array (tags work), so this test forces an
 * untaggable store to lock the production failure mode.
 */
class OcrResultCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'file']);
    }

    public function test_put_and_get_work_inside_tenancy_with_untaggable_store(): void
    {
        $this->inTenant(function () {
            $path = 'receipts/abc.jpg';
            $payload = ['status' => 'success', 'data' => ['vendor_name' => 'Kedai']];

            OcrResultCache::put($path, $payload);

            $this->assertSame($payload, OcrResultCache::get($path));
            $this->assertNull(OcrResultCache::get('receipts/missing.jpg'));
        });
    }

    public function test_tenants_cannot_read_each_others_ocr_results(): void
    {
        $path = 'receipts/shared-name.jpg';
        $payload = ['status' => 'success', 'data' => ['vendor_name' => 'A']];

        $this->inTenant(function () use ($path, $payload) {
            OcrResultCache::put($path, $payload);
        }, 'ocr-cache-a');

        $this->inTenant(function () use ($path) {
            $this->assertNull(OcrResultCache::get($path));
        }, 'ocr-cache-b');
    }

    private function inTenant(callable $callback, string $tenantId = 'ocr-cache-1'): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => $tenantId]));
        tenancy()->initialize($tenant);

        try {
            $callback();
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
