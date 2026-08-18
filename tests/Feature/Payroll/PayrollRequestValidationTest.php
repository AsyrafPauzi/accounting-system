<?php

namespace Tests\Feature\Payroll;

use App\Http\Requests\StorePayrollRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PayrollRequestValidationTest extends TestCase
{
    public function test_unbalanced_payload_is_rejected(): void
    {
        $payload = $this->balancedPayload(['net_pay' => 1]);

        $validator = $this->makeValidator($payload);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('net_pay', $validator->errors()->toArray());
    }

    public function test_balanced_payload_passes(): void
    {
        $validator = $this->makeValidator($this->balancedPayload());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    public function test_balanced_payload_with_statutory_amounts_passes(): void
    {
        $payload = $this->balancedPayload([
            'gross_salaries' => 10000,
            'employer_epf'   => 1300,
            'employer_socso' => 175,
            'employer_eis'   => 20,
            'employer_hrd'   => 0,
            'epf_payable'    => 2400,
            'socso_payable'  => 245,
            'eis_payable'    => 40,
            'pcb_payable'    => 0,
            'hrd_payable'    => 0,
            'net_pay'        => 8810,
        ]);

        $validator = $this->makeValidator($payload);

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    public function test_batch_of_balanced_runs_passes(): void
    {
        $payload = [
            'rows' => [
                $this->balancedPayload(['period_date' => '2026-07-31']),
                $this->balancedPayload(['period_date' => '2026-08-31']),
            ],
        ];

        $validator = Validator::make($payload, StorePayrollRequest::batchRules());
        StorePayrollRequest::addBatchBalanceChecks($validator, $payload['rows']);

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    public function test_unbalanced_batch_row_is_rejected_on_that_row(): void
    {
        $payload = [
            'rows' => [
                $this->balancedPayload(['net_pay' => 1]),
                $this->balancedPayload(),
            ],
        ];

        $validator = Validator::make($payload, StorePayrollRequest::batchRules());
        StorePayrollRequest::addBatchBalanceChecks($validator, $payload['rows']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rows.0.net_pay', $validator->errors()->toArray());
        $this->assertArrayNotHasKey('rows.1.net_pay', $validator->errors()->toArray());
    }

    public function test_empty_batch_is_rejected(): void
    {
        $validator = Validator::make(['rows' => []], StorePayrollRequest::batchRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rows', $validator->errors()->toArray());
    }

    public function test_batch_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('payroll.batch'));
        $this->assertTrue(Route::has('payroll.batch.store'));
        $this->assertTrue(Route::has('payroll.create'));
        $this->assertTrue(Route::has('payroll.store'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeValidator(array $payload): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($payload, StorePayrollRequest::payloadRules());
        StorePayrollRequest::addBalanceCheck($validator, $payload);

        return $validator;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function balancedPayload(array $overrides = []): array
    {
        return array_merge([
            'period_date'       => '2026-08-31',
            'bank_account_code' => '1100',
            'gross_salaries'    => 10000,
            'net_pay'           => 10000,
        ], $overrides);
    }
}
