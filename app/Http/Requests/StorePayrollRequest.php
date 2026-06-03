<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('journal.create');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_date'       => 'required|date',
            'description'       => 'nullable|string|max:255',
            'reference_number'  => 'nullable|string|max:50',
            'bank_account_code' => 'required|string|exists:accounts,code',

            // Expenses (debit). Gross is the only one that's required.
            'gross_salaries' => 'required|numeric|min:0.01',
            'employer_epf'   => 'nullable|numeric|min:0',
            'employer_socso' => 'nullable|numeric|min:0',
            'employer_eis'   => 'nullable|numeric|min:0',
            'employer_hrd'   => 'nullable|numeric|min:0',

            // Liabilities (credit)
            'epf_payable'   => 'nullable|numeric|min:0',
            'socso_payable' => 'nullable|numeric|min:0',
            'eis_payable'   => 'nullable|numeric|min:0',
            'pcb_payable'   => 'nullable|numeric|min:0',
            'hrd_payable'   => 'nullable|numeric|min:0',

            // Cash out — what actually left the bank
            'net_pay' => 'required|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $debits = array_sum([
                (float) $this->input('gross_salaries', 0),
                (float) $this->input('employer_epf', 0),
                (float) $this->input('employer_socso', 0),
                (float) $this->input('employer_eis', 0),
                (float) $this->input('employer_hrd', 0),
            ]);

            $credits = array_sum([
                (float) $this->input('epf_payable', 0),
                (float) $this->input('socso_payable', 0),
                (float) $this->input('eis_payable', 0),
                (float) $this->input('pcb_payable', 0),
                (float) $this->input('hrd_payable', 0),
                (float) $this->input('net_pay', 0),
            ]);

            if (abs($debits - $credits) > 0.01) {
                $validator->errors()->add(
                    'net_pay',
                    'Debits (RM ' . number_format($debits, 2) . ') must equal Credits (RM ' . number_format($credits, 2) .
                    '). Adjust Net Pay or check the statutory amounts.'
                );
            }
        });
    }
}
