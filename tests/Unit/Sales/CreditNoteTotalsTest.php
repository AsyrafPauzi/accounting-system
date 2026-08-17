<?php

namespace Tests\Unit\Sales;

use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use Tests\TestCase;

class CreditNoteTotalsTest extends TestCase
{
    public function test_splits_sst_from_net_on_credit_lines(): void
    {
        $totals = CreditNoteService::computeLineTotals([
            [
                'quantity'        => 1,
                'unit_price'      => 100,
                'tax_rate'        => 8,
                'discount_amount' => 0,
            ],
        ]);

        $this->assertEquals(100.0, $totals['net']);
        $this->assertEquals(8.0, $totals['tax']);
        $this->assertEquals(108.0, $totals['total']);
    }

    public function test_duplicate_invoice_payload_drops_payments_and_lhdn(): void
    {
        $payload = CreditNoteService::duplicateSafeInvoiceFields((object) [
            'customer_id'        => 9,
            'msic_code'          => '00000',
            'currency'           => 'MYR',
            'exchange_rate'      => 1,
            'shipping_amount'    => 10,
            'customer_notes'     => 'Thanks',
            'show_signature'     => true,
            'lhdn_uuid'          => 'should-not-copy',
            'lhdn_status'        => 'valid',
            'amount_paid'        => 50,
        ]);

        $this->assertSame(9, $payload['customer_id']);
        $this->assertSame(10.0, $payload['shipping_amount']);
        $this->assertArrayNotHasKey('lhdn_uuid', $payload);
        $this->assertArrayNotHasKey('amount_paid', $payload);
    }

    public function test_unapplied_credit_subtracts_refunds(): void
    {
        $this->assertEquals(30.0, CreditNoteService::unappliedAmount(100, 50, 20));
        $this->assertEquals(0.0, CreditNoteService::unappliedAmount(50, 50, 0));
    }

    public function test_late_fee_is_percent_of_open_balance(): void
    {
        $this->assertEquals(1.5, InvoiceService::lateFeeAmount(100, 1.5));
        $this->assertEquals(0.0, InvoiceService::lateFeeAmount(0, 1.5));
    }
}
