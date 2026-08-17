<?php

namespace Tests\Unit\Sales;

use App\Support\DocumentNumber;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    public function test_demo_and_overdue_tags_still_advance_the_plain_prefix(): void
    {
        $next = DocumentNumber::nextFromList([
            'INV-DEMO-0008',
            'INV-OVERDUE-001',
            'INV-1',
        ], 'INV');

        $this->assertSame('INV-0009', $next);
    }

    public function test_empty_list_starts_at_padded_one(): void
    {
        $this->assertSame('BILL-0001', DocumentNumber::nextFromList([], 'BILL'));
    }

    public function test_keeps_width_of_the_highest_number(): void
    {
        $this->assertSame('CN-0100', DocumentNumber::nextFromList(['CN-0099'], 'CN'));
    }
}
