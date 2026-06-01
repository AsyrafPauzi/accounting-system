<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\OcrTextNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * OcrTextNormalizer fixes predictable Tesseract confusion errors:
 *   O ↔ 0,  l ↔ 1,  I ↔ 1,  S ↔ 5,  B ↔ 8
 *
 * Crucial constraint: only fire INSIDE money/date contexts. Vendor names
 * like "BOOKHIVE" must never become "B00KHIVE".
 */
class OcrTextNormalizerTest extends TestCase
{
    private OcrTextNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new OcrTextNormalizer();
    }

    public function test_fixes_O_to_0_inside_money_amount(): void
    {
        $this->assertSame('Total RM 40.50', $this->n->normalize('Total RM 4O.50'));
        $this->assertSame('Total RM 100.00', $this->n->normalize('Total RM 1OO.00'));
    }

    public function test_fixes_l_to_1_inside_money_amount(): void
    {
        $this->assertSame('Total RM 14.50', $this->n->normalize('Total RM l4.50'));
    }

    public function test_fixes_I_to_1_inside_money_amount(): void
    {
        $this->assertSame('Total RM 10.50', $this->n->normalize('Total RM I0.50'));
    }

    public function test_fixes_S_to_5_when_surrounded_by_digits(): void
    {
        $this->assertSame('Total RM 25.50', $this->n->normalize('Total RM 2S.50'));
    }

    public function test_does_not_fix_S_at_start_of_currency_token(): void
    {
        // "RMS" at the start of a token is usually a typo for nothing useful.
        // We won't touch it — keeping it conservative.
        $this->assertSame('RMS5', $this->n->normalize('RMS5'));
    }

    public function test_fixes_O_to_0_inside_dates(): void
    {
        $this->assertSame('Date: 05/01/2026', $this->n->normalize('Date: O5/O1/2026'));
        $this->assertSame('Date: 05-01-2026', $this->n->normalize('Date: O5-O1-2026'));
    }

    public function test_collapses_spaced_currency_amount(): void
    {
        $this->assertSame('Total RM44.70', $this->n->normalize('Total R M 4 4 . 7 0'));
        $this->assertSame('Total RM 44.70', $this->n->normalize('Total RM 4 4.70'));
    }

    public function test_preserves_vendor_names_and_descriptions(): void
    {
        // Critical negative case: don't touch O/I/l in plain text contexts.
        $this->assertSame('BOOKHIVE Sdn Bhd', $this->n->normalize('BOOKHIVE Sdn Bhd'));
        $this->assertSame('Buku Nota A5', $this->n->normalize('Buku Nota A5'));
        $this->assertSame('Pelanggan: Pelanggan Dummy', $this->n->normalize('Pelanggan: Pelanggan Dummy'));
        $this->assertSame('Description', $this->n->normalize('Description'));
    }

    public function test_preserves_already_correct_text(): void
    {
        $clean = "KEDAI MAJU SDN. BHD.\nTarikh: 21 Mei 2025\nTotal RM 23.76";
        $this->assertSame($clean, $this->n->normalize($clean));
    }

    public function test_idempotent(): void
    {
        $input = 'Total RM 4O.5O';
        $once = $this->n->normalize($input);
        $twice = $this->n->normalize($once);
        $this->assertSame($once, $twice);
    }
}
