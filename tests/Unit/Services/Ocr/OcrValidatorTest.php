<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrResult;
use App\Services\Ocr\OcrValidator;
use PHPUnit\Framework\TestCase;

/**
 * OcrValidator runs sanity checks on a parsed receipt:
 *   1. subtotal + tax  ≈ total          (within RM 0.01)
 *   2. sum(items.amount) ≈ subtotal     (within RM 0.05 to allow rounding)
 *
 * If a check fires, confidence drops and a human-readable warning is attached.
 * If everything balances, confidence is bumped UP — we have higher trust in
 * the extraction. Missing fields skip the relevant check (don't claim error
 * on incomplete data).
 */
class OcrValidatorTest extends TestCase
{
    private OcrValidator $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = new OcrValidator();
    }

    private function buildResult(array $fields): OcrResult
    {
        return OcrResult::success('tesseract', $fields);
    }

    public function test_passes_when_subtotal_plus_tax_equals_total(): void
    {
        $result = $this->buildResult([
            'subtotal' => 22.00,
            'tax_amount' => 1.76,
            'total_amount' => 23.76,
            'items' => [],
        ]);

        $validated = $this->v->validate($result);

        $this->assertEmpty($validated->warnings);
        $this->assertGreaterThanOrEqual(0.9, $validated->confidence ?? 0);
    }

    public function test_warns_when_totals_do_not_balance(): void
    {
        $result = $this->buildResult([
            'subtotal' => 22.00,
            'tax_amount' => 1.76,
            'total_amount' => 99.99,
            'items' => [],
        ]);

        $validated = $this->v->validate($result);

        $this->assertNotEmpty($validated->warnings);
        $this->assertLessThan(0.7, $validated->confidence ?? 1.0);
    }

    public function test_passes_when_items_sum_matches_subtotal(): void
    {
        $result = $this->buildResult([
            'subtotal' => 22.00,
            'tax_amount' => 1.76,
            'total_amount' => 23.76,
            'items' => [
                ['description' => 'A', 'amount' => 7.00],
                ['description' => 'B', 'amount' => 6.00],
                ['description' => 'C', 'amount' => 5.00],
                ['description' => 'D', 'amount' => 4.00],
            ],
        ]);

        $validated = $this->v->validate($result);
        $this->assertEmpty($validated->warnings);
    }

    public function test_warns_when_items_sum_does_not_match_subtotal(): void
    {
        $result = $this->buildResult([
            'subtotal' => 22.00,
            'total_amount' => 22.00,
            'items' => [
                ['description' => 'A', 'amount' => 7.00],
                ['description' => 'B', 'amount' => 6.00],
            ],
        ]);

        $validated = $this->v->validate($result);

        $this->assertNotEmpty($validated->warnings);
        // Warning text should mention items / line items.
        $this->assertStringContainsStringIgnoringCase('item', implode(' ', $validated->warnings));
    }

    public function test_skips_validation_when_subtotal_missing(): void
    {
        // Common case: simple receipts that only show a total, no subtotal.
        $result = $this->buildResult([
            'subtotal' => null,
            'tax_amount' => null,
            'total_amount' => 50.00,
            'items' => [],
        ]);

        $validated = $this->v->validate($result);

        $this->assertEmpty($validated->warnings, 'Should not warn when fields are simply missing');
    }

    public function test_does_not_warn_about_items_when_no_subtotal(): void
    {
        $result = $this->buildResult([
            'subtotal' => null,
            'total_amount' => 13.00,
            'items' => [
                ['description' => 'Coffee', 'amount' => 8.50],
            ],
        ]);

        $validated = $this->v->validate($result);
        $this->assertEmpty($validated->warnings);
    }

    public function test_tolerates_one_cent_rounding_in_totals(): void
    {
        // 22.005 + 1.755 = 23.76, but rounded printed values can differ by 1 cent.
        $result = $this->buildResult([
            'subtotal' => 22.00,
            'tax_amount' => 1.77, // off by 1 cent
            'total_amount' => 23.76,
            'items' => [],
        ]);

        $validated = $this->v->validate($result);
        $this->assertEmpty($validated->warnings);
    }

    public function test_passes_through_failed_results_unchanged(): void
    {
        $failed = OcrResult::failed('tesseract', 'OCR engine error');
        $validated = $this->v->validate($failed);

        $this->assertSame(OcrResult::STATUS_FAILED, $validated->status);
        $this->assertEmpty($validated->warnings);
    }
}
