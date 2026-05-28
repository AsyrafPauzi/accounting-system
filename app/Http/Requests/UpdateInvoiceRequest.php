<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.edit');
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper((string) ($this->input('currency') ?: 'MYR'));
        $this->merge(['currency' => $currency]);
        if ($currency === $this->tenantBaseCurrencyForValidation()) {
            $this->merge(['exchange_rate' => 1]);
        }
        $this->merge([
            'show_signature' => filter_var($this->input('show_signature', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $base = $this->tenantBaseCurrencyForValidation();

        return [
            'invoice_number'              => [
                'required',
                'string',
                'max:80',
                Rule::unique('invoices', 'invoice_number')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('id')),
            ],
            'customer_id'                 => 'required|exists:customers,id',
            'msic_code'                   => 'required|string',
            'issue_date'                  => 'required|date',
            'due_date'                    => 'nullable|date|after_or_equal:issue_date',
            'currency'                    => ['required', 'string', 'size:3', Rule::in(['MYR', 'USD', 'JPY'])],
            'exchange_rate'               => [
                Rule::requiredIf(fn () => strtoupper((string) $this->input('currency')) !== $base),
                'nullable',
                'numeric',
                'min:0.000001',
            ],
            'items'                       => 'required|array|min:1',
            'items.*.description'         => 'required|string',
            'items.*.quantity'            => 'required|numeric|min:0.01',
            'items.*.unit_price'          => 'required|numeric',
            'items.*.tax_rate'            => 'required|numeric',
            'items.*.item_classification' => 'required|string',
            'items.*.discount_amount'     => 'nullable|numeric',
            'shipping_amount'             => 'nullable|numeric',
            'customer_notes'              => 'nullable|string',
            'show_signature'              => 'boolean',
        ];
    }

    private function tenantBaseCurrencyForValidation(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if ($this->user()?->tenant_id) {
            $t = \App\Models\Tenant::find($this->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }
}
