<?php

namespace Tests\Feature\Bills;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BillRequestValidationTest extends TestCase
{
    public function test_bill_item_description_over_database_limit_is_rejected(): void
    {
        $payload = [
            'items' => [
                [
                    'description' => str_repeat('a', 256),
                ],
            ],
        ];

        $storeValidator = Validator::make($payload, $this->descriptionRules(new StoreBillRequest()));
        $updateValidator = Validator::make($payload, $this->descriptionRules(new UpdateBillRequest()));

        $this->assertTrue($storeValidator->fails());
        $this->assertTrue($updateValidator->fails());
        $this->assertArrayHasKey('items.0.description', $storeValidator->errors()->toArray());
        $this->assertArrayHasKey('items.0.description', $updateValidator->errors()->toArray());
    }

    private function descriptionRules(StoreBillRequest|UpdateBillRequest $request): array
    {
        $rules = $request->rules();

        return [
            'items' => $rules['items'],
            'items.*.description' => $rules['items.*.description'],
        ];
    }
}
