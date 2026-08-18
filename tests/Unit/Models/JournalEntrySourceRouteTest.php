<?php

namespace Tests\Unit\Models;

use App\Models\JournalEntry;
use Tests\TestCase;

class JournalEntrySourceRouteTest extends TestCase
{
    public function test_source_label_uses_reference_number_for_invoice_payment(): void
    {
        $entry = new JournalEntry([
            'reference_type' => 'Invoice Payment',
            'reference_number' => 'INV-1001',
        ]);

        $this->assertSame('Invoice INV-1001', $entry->getSourceLabel());
    }

    public function test_source_route_always_falls_back_to_general_ledger_show(): void
    {
        $entry = new JournalEntry([
            'reference_type' => 'Invoice',
            'reference_id' => 999999,
        ]);
        $entry->id = 42;

        $route = $entry->getSourceRoute();

        $this->assertStringContainsString('/general-ledger/42', $route);
    }

    public function test_manual_draft_entry_links_to_journal_edit_when_available(): void
    {
        $entry = new JournalEntry([
            'type' => 'manual',
            'status' => 'draft',
        ]);
        $entry->id = 7;

        $route = $entry->getSourceRoute();

        $this->assertStringContainsString('/journal/manual/7/edit', $route);
    }
}
