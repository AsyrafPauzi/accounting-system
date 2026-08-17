<?php

namespace Tests\Unit\Ocr;

use App\Services\Ai\IlmuClient;
use App\Services\Ocr\OcrResult;
use App\Services\Ocr\Providers\IlmuProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IlmuProviderTest extends TestCase
{
    public function test_maps_ilmu_json_to_ocr_result(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/sample.jpg', 'not-a-real-image');

        Http::fake([
            IlmuClient::CHAT_URL => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'vendor_name' => 'Kedai Runcit Ali',
                        'bill_date' => '2026-08-01',
                        'currency' => 'MYR',
                        'subtotal' => 100,
                        'tax_amount' => 8,
                        'total_amount' => 108,
                        'reference' => 'RCP-1',
                        'items' => [
                            ['description' => 'Gula', 'quantity' => 2, 'unit_amount' => 50, 'amount' => 100],
                        ],
                    ])],
                ]],
            ], 200),
        ]);

        $provider = new IlmuProvider(new IlmuClient('sk-test'));
        $result = $provider->extract('receipts/sample.jpg');

        $this->assertSame(OcrResult::STATUS_SUCCESS, $result->status);
        $this->assertSame('ilmu', $result->provider);
        $this->assertSame('Kedai Runcit Ali', $result->vendorName);
        $this->assertSame('2026-08-01', $result->billDate);
        $this->assertEquals(108.0, $result->totalAmount);
        $this->assertSame('Gula', $result->items[0]['description']);
        $this->assertEquals(2.0, $result->items[0]['quantity']);
    }

    public function test_strips_markdown_fences_from_json(): void
    {
        $provider = new IlmuProvider;
        $parsed = $provider->decodeJsonObject("```json\n{\"vendor_name\":\"X\"}\n```");

        $this->assertSame('X', $parsed['vendor_name']);
    }
}
