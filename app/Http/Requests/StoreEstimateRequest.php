<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estimates.create');
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper((string) ($this->input('currency') ?: 'MYR'));
        $this->merge(['currency' => $currency]);
        if ($currency === $this->tenantBaseCurrencyForValidation()) {
            $this->merge(['exchange_rate' => 1]);
        }
    }

    public function rules(): array
    {
        $base = $this->tenantBaseCurrencyForValidation();

        return [
            'customer_id'                 => 'required|exists:customers,id',
            'estimate_number'             => ['required', 'string', 'max:80', Rule::unique('estimates', 'estimate_number')->whereNull('deleted_at')],
            'issue_date'                  => 'required|date',
            'expiry_date'                 => 'nullable|date|after_or_equal:issue_date',
            'currency'                    => ['required', 'string', 'size:3', Rule::in(['MYR', 'IDR', 'USD', 'SGD', 'EUR', 'GBP', 'JPY'])],
            'exchange_rate'               => [
                Rule::requiredIf(fn () => strtoupper((string) $this->input('currency')) !== $base),
                'nullable',
                'numeric',
                'min:0.000001',
            ],
            'items'                       => 'required|array|min:1',
            'items.*.description'         => 'required|string|max:500',
            'items.*.quantity'            => 'required|numeric|min:0.01',
            'items.*.unit_price'          => 'required|numeric',
            'items.*.tax_rate'            => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'     => 'nullable|numeric|min:0',
            'items.*.product_id'          => 'nullable|integer|exists:products,id',
            'items.*.item_classification' => 'nullable|string|max:20',
            'shipping_amount'             => 'nullable|numeric|min:0',
            'rounding_adjustment'         => 'nullable|numeric',
            'customer_notes'              => 'nullable|string|max:5000',
            'private_notes'               => 'nullable|string|max:5000',
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
