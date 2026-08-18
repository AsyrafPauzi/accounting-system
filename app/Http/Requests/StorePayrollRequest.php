<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API callers are already authenticated by api.key. Web callers
        // still need journal.create (the in-app Run Payroll form).
        if ($this->user() === null) {
            return true;
        }

        return $this->user()->can('journal.create');
    }

    /**
     * Shared field rules without DB lookups — used by unit tests and
     * merged into rules() with exists:accounts,code.
     *
     * @return array<string, string>
     */
    public static function payloadRules(): array
    {
        return [
            'period_date'       => 'required|date',
            'description'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:50',
            'bank_account_code' => 'required|string',

            'gross_salaries' => 'required|numeric|min:0.01',
            'employer_epf'   => 'nullable|numeric|min:0',
            'employer_socso' => 'nullable|numeric|min:0',
            'employer_eis'   => 'nullable|numeric|min:0',
            'employer_hrd'   => 'nullable|numeric|min:0',

            'epf_payable'   => 'nullable|numeric|min:0',
            'socso_payable' => 'nullable|numeric|min:0',
            'eis_payable'   => 'nullable|numeric|min:0',
            'pcb_payable'   => 'nullable|numeric|min:0',
            'hrd_payable'   => 'nullable|numeric|min:0',

            'net_pay' => 'required|numeric|min:0',
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = self::payloadRules();
        $rules['bank_account_code'] = 'required|string|exists:accounts,code';

        return $rules;
    }

    public static function addBalanceCheck(Validator $validator, array $input, string $errorKey = 'net_pay'): void
    {
        $validator->after(function (Validator $validator) use ($input, $errorKey) {
            $debits = array_sum([
                (float) ($input['gross_salaries'] ?? 0),
                (float) ($input['employer_epf'] ?? 0),
                (float) ($input['employer_socso'] ?? 0),
                (float) ($input['employer_eis'] ?? 0),
                (float) ($input['employer_hrd'] ?? 0),
            ]);

            $credits = array_sum([
                (float) ($input['epf_payable'] ?? 0),
                (float) ($input['socso_payable'] ?? 0),
                (float) ($input['eis_payable'] ?? 0),
                (float) ($input['pcb_payable'] ?? 0),
                (float) ($input['hrd_payable'] ?? 0),
                (float) ($input['net_pay'] ?? 0),
            ]);

            if (abs($debits - $credits) > 0.01) {
                $validator->errors()->add(
                    $errorKey,
                    'Debits (RM ' . number_format($debits, 2) . ') must equal Credits (RM ' . number_format($credits, 2) .
                    '). Adjust Net Pay or check the statutory amounts.'
                );
            }
        });
    }

    public function withValidator($validator): void
    {
        self::addBalanceCheck($validator, $this->all());
    }

    /**
     * @return array<string, string>
     */
    public static function batchRules(bool $withAccountExists = false): array
    {
        $rules = [
            'rows' => 'required|array|min:1|max:50',
        ];

        foreach (self::payloadRules() as $key => $rule) {
            $rules["rows.*.{$key}"] = $rule;
        }

        if ($withAccountExists) {
            $rules['rows.*.bank_account_code'] = 'required|string|exists:accounts,code';
        }

        return $rules;
    }

    /**
     * @param  list<mixed>  $rows
     */
    public static function addBatchBalanceChecks(Validator $validator, array $rows): void
    {
        foreach ($rows as $index => $row) {
            self::addBalanceCheck(
                $validator,
                is_array($row) ? $row : [],
                "rows.{$index}.net_pay"
            );
        }
    }
}
