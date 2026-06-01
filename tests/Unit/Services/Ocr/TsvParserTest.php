<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\TsvParser;
use PHPUnit\Framework\TestCase;

/**
 * TsvParser converts Tesseract TSV output (with bounding boxes) into the same
 * structured shape as ReceiptParser, using SPATIAL information to reconstruct
 * tabular data. The text-based parser remains as fallback when this one finds
 * nothing useful (e.g., free-form receipts with no table structure).
 *
 * The tests use synthetic TSV strings to avoid needing real OCR runs.
 */
class TsvParserTest extends TestCase
{
    private TsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TsvParser();
    }

    /** Build a minimal TSV string from a list of {text,left,top,width,height} rows. */
    private function buildTsv(array $words): string
    {
        $header = "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext";
        $lines = [$header];
        foreach ($words as $i => $w) {
            $lines[] = implode("\t", [
                5,         // level (word)
                1,         // page_num
                1,         // block_num
                1,         // par_num
                $w['line'] ?? 1,
                $i + 1,    // word_num
                $w['left'],
                $w['top'],
                $w['width'],
                $w['height'],
                95,        // conf
                $w['text'],
            ]);
        }
        return implode("\n", $lines);
    }

    public function test_returns_empty_when_tsv_empty(): void
    {
        $r = $this->parser->parse('');
        $this->assertSame([], $r['items']);
    }

    public function test_clusters_words_into_rows_by_y_coordinate(): void
    {
        // Two rows: words at y=100 and words at y=300, well-separated.
        $tsv = $this->buildTsv([
            ['text' => 'Buku',   'left' => 10,  'top' => 100, 'width' => 80, 'height' => 30],
            ['text' => 'Nota',   'left' => 100, 'top' => 100, 'width' => 80, 'height' => 30],
            ['text' => '7.00',   'left' => 500, 'top' => 100, 'width' => 80, 'height' => 30],
            ['text' => 'Pen',    'left' => 10,  'top' => 300, 'width' => 60, 'height' => 30],
            ['text' => '6.00',   'left' => 500, 'top' => 300, 'width' => 80, 'height' => 30],
        ]);

        $rows = $this->parser->debugClusterRows($tsv);

        $this->assertCount(2, $rows, 'Should detect exactly 2 rows');
    }

    public function test_extracts_items_from_table_with_qty_unit_amount(): void
    {
        // Synthetic invoice: "Description Qty Unit Amount" header + 2 data rows.
        // Columns positioned at x=10, x=400, x=550, x=700.
        $tsv = $this->buildTsv([
            // Header row at y=50
            ['text' => 'Description', 'left' => 10,  'top' => 50, 'width' => 200, 'height' => 30, 'line' => 1],
            ['text' => 'Qty',         'left' => 400, 'top' => 50, 'width' => 60,  'height' => 30, 'line' => 1],
            ['text' => 'Unit',        'left' => 550, 'top' => 50, 'width' => 60,  'height' => 30, 'line' => 1],
            ['text' => 'Amount',      'left' => 700, 'top' => 50, 'width' => 100, 'height' => 30, 'line' => 1],

            // Data row 1 at y=120
            ['text' => 'Buku',  'left' => 10,  'top' => 120, 'width' => 70, 'height' => 30, 'line' => 2],
            ['text' => 'Nota',  'left' => 90,  'top' => 120, 'width' => 70, 'height' => 30, 'line' => 2],
            ['text' => '2',     'left' => 410, 'top' => 120, 'width' => 30, 'height' => 30, 'line' => 2],
            ['text' => '3.50',  'left' => 555, 'top' => 120, 'width' => 80, 'height' => 30, 'line' => 2],
            ['text' => '7.00',  'left' => 705, 'top' => 120, 'width' => 80, 'height' => 30, 'line' => 2],

            // Data row 2 at y=190
            ['text' => 'Pen',   'left' => 10,  'top' => 190, 'width' => 60, 'height' => 30, 'line' => 3],
            ['text' => 'Biru',  'left' => 80,  'top' => 190, 'width' => 60, 'height' => 30, 'line' => 3],
            ['text' => '5',     'left' => 410, 'top' => 190, 'width' => 30, 'height' => 30, 'line' => 3],
            ['text' => '1.20',  'left' => 555, 'top' => 190, 'width' => 80, 'height' => 30, 'line' => 3],
            ['text' => '6.00',  'left' => 705, 'top' => 190, 'width' => 80, 'height' => 30, 'line' => 3],
        ]);

        $r = $this->parser->parse($tsv);

        $this->assertCount(2, $r['items']);
        $this->assertSame('Buku Nota', $r['items'][0]['description']);
        $this->assertEqualsWithDelta(2, $r['items'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(3.50, $r['items'][0]['unit_amount'], 0.001);
        $this->assertEqualsWithDelta(7.00, $r['items'][0]['amount'], 0.001);

        $this->assertSame('Pen Biru', $r['items'][1]['description']);
        $this->assertEqualsWithDelta(5, $r['items'][1]['quantity'], 0.001);
    }

    public function test_extracts_items_from_two_column_invoice_table(): void
    {
        // Description | Total layout (no qty/unit columns) — like Exabytes invoice.
        $tsv = $this->buildTsv([
            ['text' => 'Description', 'left' => 10,  'top' => 50, 'width' => 200, 'height' => 30, 'line' => 1],
            ['text' => 'Total',       'left' => 700, 'top' => 50, 'width' => 80,  'height' => 30, 'line' => 1],

            ['text' => 'Domain',       'left' => 10,  'top' => 120, 'width' => 100, 'height' => 30, 'line' => 2],
            ['text' => 'Registration', 'left' => 115, 'top' => 120, 'width' => 200, 'height' => 30, 'line' => 2],
            ['text' => 'RM44.70',      'left' => 700, 'top' => 120, 'width' => 100, 'height' => 30, 'line' => 2],
        ]);

        $r = $this->parser->parse($tsv);

        $this->assertCount(1, $r['items']);
        $this->assertSame('Domain Registration', $r['items'][0]['description']);
        $this->assertEqualsWithDelta(44.70, $r['items'][0]['amount'], 0.001);
    }

    public function test_returns_empty_items_when_no_table_header_found(): void
    {
        // Free-form text with no table — TsvParser shouldn't invent items.
        $tsv = $this->buildTsv([
            ['text' => 'KEDAI', 'left' => 100, 'top' => 50,  'width' => 100, 'height' => 30, 'line' => 1],
            ['text' => 'MAJU',  'left' => 220, 'top' => 50,  'width' => 100, 'height' => 30, 'line' => 1],
            ['text' => 'Total', 'left' => 100, 'top' => 200, 'width' => 80,  'height' => 30, 'line' => 2],
            ['text' => 'RM',    'left' => 200, 'top' => 200, 'width' => 30,  'height' => 30, 'line' => 2],
            ['text' => '50.00', 'left' => 240, 'top' => 200, 'width' => 80,  'height' => 30, 'line' => 2],
        ]);

        $r = $this->parser->parse($tsv);

        $this->assertSame([], $r['items']);
    }

    public function test_stops_extracting_items_at_subtotal_row(): void
    {
        $tsv = $this->buildTsv([
            // Header
            ['text' => 'Description', 'left' => 10,  'top' => 50, 'width' => 200, 'height' => 30, 'line' => 1],
            ['text' => 'Total',       'left' => 700, 'top' => 50, 'width' => 80,  'height' => 30, 'line' => 1],
            // Item 1
            ['text' => 'Coffee', 'left' => 10,  'top' => 120, 'width' => 100, 'height' => 30, 'line' => 2],
            ['text' => '8.50',   'left' => 700, 'top' => 120, 'width' => 80,  'height' => 30, 'line' => 2],
            // SUBTOTAL — should NOT be picked as item
            ['text' => 'Subtotal', 'left' => 400, 'top' => 200, 'width' => 100, 'height' => 30, 'line' => 3],
            ['text' => '8.50',     'left' => 700, 'top' => 200, 'width' => 80,  'height' => 30, 'line' => 3],
            // TOTAL — should NOT be picked as item
            ['text' => 'Total',    'left' => 400, 'top' => 270, 'width' => 100, 'height' => 30, 'line' => 4],
            ['text' => '9.01',     'left' => 700, 'top' => 270, 'width' => 80,  'height' => 30, 'line' => 4],
        ]);

        $r = $this->parser->parse($tsv);

        $this->assertCount(1, $r['items']);
        $this->assertSame('Coffee', $r['items'][0]['description']);
    }
}
