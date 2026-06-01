<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\ReceiptParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests against representative Malaysian receipt OCR text.
 * These strings are anonymized but follow real-world layouts
 * (POS receipts, tax invoices, fuel station receipts).
 */
class ReceiptParserTest extends TestCase
{
    private ReceiptParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ReceiptParser();
    }

    public function test_parses_a_simple_99_speedmart_style_receipt(): void
    {
        $text = <<<TXT
        99 SPEEDMART
        NO 12, JLN PJU 1A/3
        SELANGOR

        DATE: 03/06/2026  TIME: 14:32

        MILK FRESH 1L         5.50
        BREAD WHOLEMEAL       4.20
        EGGS LARGE 10S        9.80

        SUBTOTAL             19.50
        SST 6%                1.17
        TOTAL              RM 20.67

        CASH               RM 21.00
        CHANGE             RM 0.33

        THANK YOU
        TXT;

        $result = $this->parser->parse($text);

        $this->assertSame('2026-06-03', $result['bill_date']);
        $this->assertEqualsWithDelta(20.67, $result['total_amount'], 0.001);
        $this->assertEqualsWithDelta(1.17, $result['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(19.50, $result['subtotal'], 0.001);
        $this->assertSame('MYR', $result['currency']);
        $this->assertSame('99 SPEEDMART', $result['vendor_name']);
    }

    public function test_parses_grand_total_keyword(): void
    {
        $text = "MERCANTILE TRADING SDN BHD\nDATE 15-05-2026\nGRAND TOTAL: RM 1,234.56";

        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(1234.56, $result['total_amount'], 0.001);
        $this->assertSame('2026-05-15', $result['bill_date']);
        $this->assertSame('MERCANTILE TRADING SDN BHD', $result['vendor_name']);
    }

    public function test_parses_bahasa_malaysia_keywords(): void
    {
        $text = <<<TXT
        KEDAI RUNCIT MUTIARA
        TARIKH: 01/06/2026
        JUMLAH KECIL          50.00
        CUKAI                  3.00
        JUMLAH BESAR  RM     53.00
        TXT;

        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(53.00, $result['total_amount'], 0.001);
        $this->assertEqualsWithDelta(3.00, $result['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(50.00, $result['subtotal'], 0.001);
    }

    public function test_parses_date_with_dashes(): void
    {
        $text = "STORE\nDATE: 25-12-2026\nTOTAL: RM 100.00";

        $result = $this->parser->parse($text);

        $this->assertSame('2026-12-25', $result['bill_date']);
    }

    public function test_handles_two_digit_year(): void
    {
        $text = "STORE\n01/06/26\nTOTAL RM 100.00";

        $result = $this->parser->parse($text);

        $this->assertSame('2026-06-01', $result['bill_date']);
    }

    public function test_returns_null_for_total_when_unreadable(): void
    {
        $text = "RANDOM\nNOT A RECEIPT\nGIBBERISH";

        $result = $this->parser->parse($text);

        $this->assertNull($result['total_amount']);
        $this->assertNull($result['bill_date']);
    }

    public function test_extracts_vendor_from_first_meaningful_line(): void
    {
        $text = <<<TXT
        SHELL MUTIARA DAMANSARA
        Petronas Highway Service
        Receipt No: 0001-2345
        DATE: 03/06/2026
        TOTAL: RM 80.00
        TXT;

        $result = $this->parser->parse($text);

        $this->assertSame('SHELL MUTIARA DAMANSARA', $result['vendor_name']);
    }

    public function test_skips_short_lines_when_finding_vendor(): void
    {
        $text = "TX\n--\nMR D.I.Y. (M) SDN BHD\nDATE 03/06/2026\nTOTAL: RM 50.00";

        $result = $this->parser->parse($text);

        $this->assertSame('MR D.I.Y. (M) SDN BHD', $result['vendor_name']);
    }

    public function test_handles_amount_with_thousands_separator(): void
    {
        $text = "SUPPLIER CO\nDATE: 01/06/2026\nTOTAL RM 12,345.67";

        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(12345.67, $result['total_amount'], 0.001);
    }

    public function test_currency_defaults_to_myr_when_rm_present(): void
    {
        $text = "STORE\n01/06/2026\nTOTAL RM 50.00";

        $result = $this->parser->parse($text);

        $this->assertSame('MYR', $result['currency']);
    }

    public function test_currency_detects_sgd_in_singapore_receipt(): void
    {
        $text = "SHENG SIONG SUPERMARKET\n01/06/2026\nTOTAL: SGD 25.50";

        $result = $this->parser->parse($text);

        $this->assertSame('SGD', $result['currency']);
        $this->assertEqualsWithDelta(25.50, $result['total_amount'], 0.001);
    }

    public function test_extracts_reference_or_invoice_number(): void
    {
        $text = "SHOP\nINVOICE NO: INV-2026-00123\n03/06/2026\nTOTAL: RM 100.00";

        $result = $this->parser->parse($text);

        $this->assertSame('INV-2026-00123', $result['reference']);
    }

    public function test_picks_total_over_subtotal_when_both_present(): void
    {
        $text = "STORE\n01/06/2026\nSUBTOTAL: RM 100.00\nTOTAL: RM 106.00";

        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(106.00, $result['total_amount'], 0.001);
        $this->assertEqualsWithDelta(100.00, $result['subtotal'], 0.001);
    }

    public function test_ignores_change_line_when_finding_total(): void
    {
        $text = <<<TXT
        STORE
        DATE 01/06/2026
        TOTAL: RM 47.30
        CASH RM 50.00
        CHANGE RM 2.70
        TXT;

        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(47.30, $result['total_amount'], 0.001);
    }

    public function test_returns_raw_text_in_result(): void
    {
        $text = "Some content";
        $result = $this->parser->parse($text);
        $this->assertSame($text, $result['raw_text']);
    }

    public function test_handles_empty_input(): void
    {
        $result = $this->parser->parse('');

        $this->assertNull($result['total_amount']);
        $this->assertNull($result['bill_date']);
        $this->assertNull($result['vendor_name']);
        $this->assertSame('', $result['raw_text']);
    }
}
