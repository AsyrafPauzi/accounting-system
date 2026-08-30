<?php

namespace Tests\Unit\Support;

use App\Support\OcrProgress;
use PHPUnit\Framework\TestCase;

class OcrProgressTest extends TestCase
{
    public function test_upload_maps_into_first_quarter(): void
    {
        $p = OcrProgress::forUploadPercent(100);
        $this->assertSame('upload', $p['phase']);
        $this->assertSame(25, $p['progress']);
    }

    public function test_pending_starts_queued_then_processing_and_caps_at_90(): void
    {
        $early = OcrProgress::forPending(500);
        $this->assertSame('queued', $early['phase']);
        $this->assertGreaterThanOrEqual(25, $early['progress']);
        $this->assertLessThanOrEqual(35, $early['progress']);

        $mid = OcrProgress::forPending(15_000);
        $this->assertSame('processing', $mid['phase']);
        $this->assertGreaterThan(35, $mid['progress']);
        $this->assertLessThanOrEqual(90, $mid['progress']);

        $late = OcrProgress::forPending(600_000);
        $this->assertSame(90, $late['progress']);
    }

    public function test_completed_and_failed(): void
    {
        $this->assertSame(100, OcrProgress::completed()['progress']);
        $this->assertSame('failed', OcrProgress::failed()['phase']);
    }
}
