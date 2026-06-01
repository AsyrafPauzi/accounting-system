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

    public function test_parses_bahasa_malaysia_month_name_dates(): void
    {
        $cases = [
            '21 Mei 2025' => '2025-05-21',
            '1 Januari 2026' => '2026-01-01',
            '15 Februari 2025' => '2025-02-15',
            '3 Mac 2026' => '2026-03-03',
            '7 April 2025' => '2025-04-07',
            '22 Jun 2025' => '2025-06-22',
            '30 Julai 2025' => '2025-07-30',
            '12 Ogos 2025' => '2025-08-12',
            '9 September 2025' => '2025-09-09',
            '4 Oktober 2025' => '2025-10-04',
            '11 November 2025' => '2025-11-11',
            '25 Disember 2025' => '2025-12-25',
        ];
        foreach ($cases as $input => $expected) {
            $text = "STORE\nTarikh: $input\nTOTAL RM 50.00";
            $result = $this->parser->parse($text);
            $this->assertSame($expected, $result['bill_date'], "Failed parsing date: $input");
        }
    }

    public function test_parses_english_month_name_dates(): void
    {
        $text = "STORE\nDate: 21 May 2025\nTOTAL RM 50.00";
        $result = $this->parser->parse($text);
        $this->assertSame('2025-05-21', $result['bill_date']);
    }

    public function test_reference_skips_bahasa_keyword_alone(): void
    {
        // "RUJUKAN" alone is the Malay word for "reference" — not a value to capture.
        $text = "STORE\nNO. RESIT : RCP250521-001\nNO. RUJUKAN : 123456789012\nTOTAL RM 23.76";
        $result = $this->parser->parse($text);

        // Should pick the receipt number, not the word RUJUKAN.
        $this->assertNotSame('RUJUKAN', $result['reference']);
        // RCP250521-001 has dashes and is a likely real reference token.
        $this->assertSame('RCP250521-001', $result['reference']);
    }

    public function test_extracts_simple_line_items(): void
    {
        $text = <<<TXT
        KEDAI MAJU SDN BHD
        Tarikh: 21 Mei 2025
        Buku Nota A5         2    3.50    7.00
        Pen Biru             5    1.20    6.00
        Fail Plastik A4      2    2.50    5.00
        Sticky Note          1    4.00    4.00
        Jumlah (RM)         22.00
        SST 8%               1.76
        JUMLAH BAYARAN (RM) 23.76
        TXT;

        $result = $this->parser->parse($text);

        $this->assertCount(4, $result['items']);
        $this->assertSame('Buku Nota A5', $result['items'][0]['description']);
        $this->assertEqualsWithDelta(7.00, $result['items'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(2, $result['items'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(3.50, $result['items'][0]['unit_amount'], 0.001);
        $this->assertSame('Sticky Note', $result['items'][3]['description']);
        $this->assertEqualsWithDelta(4.00, $result['items'][3]['amount'], 0.001);
    }

    public function test_extracts_simple_items_without_qty(): void
    {
        $text = <<<TXT
        SHOP
        DATE 01/06/2026
        Coffee                  8.50
        Sandwich               12.00
        Total           RM    20.50
        TXT;

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['items']);
        $this->assertSame('Coffee', $result['items'][0]['description']);
        $this->assertEqualsWithDelta(8.50, $result['items'][0]['amount'], 0.001);
        $this->assertSame('Sandwich', $result['items'][1]['description']);
        $this->assertEqualsWithDelta(12.00, $result['items'][1]['amount'], 0.001);
    }

    public function test_does_not_pick_total_lines_as_items(): void
    {
        $text = <<<TXT
        STORE
        DATE 01/06/2026
        Coffee                  8.50
        SUBTOTAL               8.50
        SST 6%                 0.51
        GRAND TOTAL    RM      9.01
        TXT;

        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Coffee', $result['items'][0]['description']);
    }

    public function test_strips_leading_item_numbers(): void
    {
        $text = "STORE\n01/06/2026\n1. Buku Nota A5  2  3.50  7.00\n2. Pen  5  1.20  6.00\nTOTAL RM 13.00";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['items']);
        $this->assertSame('Buku Nota A5', $result['items'][0]['description']);
        $this->assertSame('Pen', $result['items'][1]['description']);
    }

    public function test_strips_bare_space_item_numbers(): void
    {
        // Real Tesseract output frequently drops the "." after the bil. number.
        $text = "STORE\n01/06/2026\n1 Buku Nota A5  2  3.50  7.00\n2 Pen Biru  5  1.20  6.00\nTOTAL RM 13.00";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result['items']);
        $this->assertSame('Buku Nota A5', $result['items'][0]['description']);
        $this->assertSame('Pen Biru', $result['items'][1]['description']);
    }

    public function test_skips_paid_stamp_when_picking_vendor(): void
    {
        // Many SaaS invoice PDFs lead with a "PAID" / "UNPAID" status stamp.
        // Vendor should be the next meaningful business name.
        $text = "PAID\nExabytes Network Sdn. Bhd.\n1-18-8, Suntech\nInvoice #INV-001\n28/05/2026\nTotal RM 44.70";
        $result = $this->parser->parse($text);

        $this->assertSame('Exabytes Network Sdn. Bhd.', $result['vendor_name']);
    }

    public function test_extracts_reference_with_hash_separator(): void
    {
        // Invoice numbers are commonly written with `#`: "Invoice #INV-001".
        $text = "Acme Corp\nInvoice #MY-PAID-81269351\n28/05/2026\nTotal RM 44.70";
        $result = $this->parser->parse($text);

        $this->assertSame('MY-PAID-81269351', $result['reference']);
    }

    public function test_detects_currency_even_when_glued_to_amount(): void
    {
        // Many generated PDFs render "RM44.70" without a space.
        $text = "Acme Corp\n28/05/2026\nTotal RM44.70";
        $result = $this->parser->parse($text);

        $this->assertSame('MYR', $result['currency']);
    }

    public function test_extracts_line_item_with_glued_currency(): void
    {
        $text = "Acme Corp\n28/05/2026\nDescription\tTotal\nDomain Registration - bukucloud.com - 1 Year/s (28/05/2026 - 27/05/2027)\tRM44.70\nSub Total RM44.70\nTotal RM44.70";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['items']);
        $this->assertSame(
            'Domain Registration - bukucloud.com - 1 Year/s (28/05/2026 - 27/05/2027)',
            $result['items'][0]['description']
        );
        $this->assertEqualsWithDelta(44.70, $result['items'][0]['amount'], 0.001);
    }

    public function test_stops_scanning_items_after_totals_section(): void
    {
        // The transaction row at the bottom looks structurally like an item
        // but it's metadata. Stopping at "Sub Total" / "Total" handles it.
        $text = "Acme Corp\n28/05/2026\nDomain Registration RM44.70\nSub Total RM44.70\n8.00% Service Tax RM0.00\nCredit RM0.00\nTotal RM44.70\n28/05/2026 Touch n Go T134275688226 RM44.70\nBalance RM0.00";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Domain Registration', $result['items'][0]['description']);
    }

    public function test_recognises_bm_service_tax_variants(): void
    {
        // BM receipts use "Cukai Perkhidmatan" or "Caj Perkhidmatan" for service tax.
        $cases = [
            "STORE\n01/06/2026\nSubtotal RM 100.00\nCukai Perkhidmatan 6% RM 6.00\nTotal RM 106.00" => 6.00,
            "STORE\n01/06/2026\nSubtotal RM 100.00\nCaj Perkhidmatan RM 8.00\nTotal RM 108.00" => 8.00,
            "STORE\n01/06/2026\nSubtotal RM 100.00\nService Tax 6% RM 6.00\nTotal RM 106.00" => 6.00,
        ];
        foreach ($cases as $text => $expected) {
            $r = $this->parser->parse($text);
            $this->assertEqualsWithDelta($expected, $r['tax_amount'], 0.01, "Failed for: $text");
        }
    }

    public function test_phone_number_not_picked_as_reference(): void
    {
        // Phone numbers like "Tel: 03-1234 5678" must not become reference IDs.
        $text = "ACME SDN BHD\nTel: 03-1234 5678\nDate: 01/06/2026\nTotal RM 50.00";
        $r = $this->parser->parse($text);

        // Reference may be null OR something else, but NOT a phone-number-shaped value.
        if ($r['reference']) {
            $this->assertDoesNotMatchRegularExpression('/^\d{3,4}[-\s]?\d{3,4}[-\s]?\d{4}$/', $r['reference']);
        } else {
            $this->assertNull($r['reference']);
        }
    }

    public function test_compact_dates(): void
    {
        // OCR sometimes drops spaces around month names: "21Mei2025".
        $text = "STORE\nTarikh:21Mei2025\nTotal RM 50.00";
        $r = $this->parser->parse($text);
        $this->assertSame('2025-05-21', $r['bill_date']);
    }

    public function test_handles_sdn_bhd_variants(): void
    {
        // Both styles should pass through findVendor cleanly.
        $cases = ['Acme Sdn Bhd', 'Acme Sdn. Bhd.', 'Acme Bhd', 'Acme Pte Ltd'];
        foreach ($cases as $name) {
            $r = $this->parser->parse("$name\n01/06/2026\nTotal RM 50.00");
            $this->assertSame($name, $r['vendor_name'], "Failed to extract vendor: $name");
        }
    }

    public function test_total_prefers_jumlah_bayaran_over_jumlah(): void
    {
        // Malaysian receipts commonly show subtotal as "Jumlah" and final total as
        // "JUMLAH BAYARAN". Total should be the latter; subtotal the former.
        $text = "KEDAI MAJU\n21 Mei 2025\nJumlah (RM)  22.00\nSST 8%  1.76\nJUMLAH BAYARAN (RM)  23.76";
        $result = $this->parser->parse($text);

        $this->assertEqualsWithDelta(23.76, $result['total_amount'], 0.001);
        $this->assertEqualsWithDelta(22.00, $result['subtotal'], 0.001);
        $this->assertEqualsWithDelta(1.76, $result['tax_amount'], 0.001);
    }
}
