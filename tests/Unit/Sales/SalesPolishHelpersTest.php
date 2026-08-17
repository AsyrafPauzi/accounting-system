<?php

namespace Tests\Unit\Sales;

use App\Models\ArDeposit;
use App\Services\BillplzService;
use Tests\TestCase;

class SalesPolishHelpersTest extends TestCase
{
    public function test_deposit_open_amount_excludes_refund_and_forfeit(): void
    {
        $deposit = new ArDeposit([
            'amount'            => 100,
            'applied_amount'    => 40,
            'refunded_amount'   => 10,
            'forfeited_amount'  => 15,
        ]);

        $this->assertEquals(35.0, $deposit->openAmount());
    }

    public function test_billplz_callback_accepts_signed_paid_payload(): void
    {
        $service = new BillplzService('secret', 'col', 'https://www.billplz-sandbox.com/api', 'xsig');
        $payload = [
            'id'          => '8X0Iyzaw',
            'paid'        => 'true',
            'reference_1' => 'inv-1-tenant',
        ];
        ksort($payload);
        $parts = [];
        foreach ($payload as $key => $value) {
            $parts[] = $key.$value;
        }
        $payload['x_signature'] = hash_hmac('sha256', implode('|', $parts), 'xsig');

        $this->assertTrue($service->callbackIsPaid($payload));
    }

    public function test_environment_defaults_to_preprod(): void
    {
        $this->assertSame('preprod', app(\App\Services\MyInvoisService::class)->environment());
    }

    public function test_bulk_service_sanitizes_ids(): void
    {
        $ids = app(\App\Services\DocumentBulkService::class)->sanitizeIds(['1', '0', '-3', '1', '2', 'x']);
        $this->assertSame([1, 2], $ids);
    }

    public function test_billplz_callback_rejects_bad_signature(): void
    {
        $service = new BillplzService('secret', 'col', 'https://www.billplz-sandbox.com/api', 'xsig');

        $this->assertFalse($service->callbackIsPaid([
            'paid'        => 'true',
            'x_signature' => 'nope',
        ]));
    }
}
