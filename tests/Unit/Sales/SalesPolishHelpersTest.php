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

        \Illuminate\Support\Facades\Http::fake([
            'www.billplz-sandbox.com/api/v3/bills/*' => \Illuminate\Support\Facades\Http::response(['id' => 'b1', 'paid' => false], 200),
        ]);

        $this->assertFalse($service->callbackIsPaid([
            'id'          => 'b1',
            'paid'        => 'true',
            'x_signature' => 'nope',
        ]));
    }

    public function test_billplz_callback_accepts_when_signature_fails_but_api_confirms_paid(): void
    {
        $service = new BillplzService('secret', 'col', 'https://www.billplz-sandbox.com/api', 'wrong-xsig');

        \Illuminate\Support\Facades\Http::fake([
            'www.billplz-sandbox.com/api/v3/bills/6aad9a6a7ff348be' => \Illuminate\Support\Facades\Http::response([
                'id'    => '6aad9a6a7ff348be',
                'paid'  => true,
                'state' => 'paid',
            ], 200),
        ]);

        $this->assertTrue($service->callbackIsPaid([
            'id'          => '6aad9a6a7ff348be',
            'paid'        => 'true',
            'reference_1' => 'inv-1-tenant',
            'x_signature' => 'definitely-wrong',
        ]));
    }

    public function test_billplz_callback_uses_api_when_xsignature_key_missing(): void
    {
        $service = new BillplzService('secret', 'col', 'https://www.billplz-sandbox.com/api', null);

        \Illuminate\Support\Facades\Http::fake([
            'www.billplz-sandbox.com/api/v3/bills/bill-no-xsig' => \Illuminate\Support\Facades\Http::response([
                'id'   => 'bill-no-xsig',
                'paid' => true,
            ], 200),
        ]);

        $this->assertTrue($service->callbackIsPaid([
            'id'   => 'bill-no-xsig',
            'paid' => 'true',
        ]));
    }
}
