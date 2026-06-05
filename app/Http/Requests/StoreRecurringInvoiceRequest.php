<?php

namespace App\Http\Requests;

use App\Models\RecurringInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recurring-invoices.create');
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper((string) ($this->input('currency') ?: 'MYR'));
        $this->merge(['currency' => $currency]);
        if ($currency === $this->tenantBaseCurrencyForValidation()) {
            $this->merge(['exchange_rate' => 1]);
        }
        $this->merge([
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $base = $this->tenantBaseCurrencyForValidation();

        return [
            'name'                        => 'nullable|string|max:150',
            'customer_id'                 => 'required|exists:customers,id',
            'cadence'                     => ['required', 'string', Rule::in(RecurringInvoice::CADENCES)],
            'interval'                    => 'required|integer|min:1|max:36',
            'start_date'                  => 'required|date',
            'end_date'                    => 'nullable|date|after_or_equal:start_date',
            'next_run_date'               => 'nullable|date',
            'is_active'                   => 'boolean',
            'currency'                    => ['required', 'string', 'size:3', Rule::in(['MYR', 'IDR', 'USD', 'SGD', 'EUR', 'GBP', 'JPY'])],
            'exchange_rate'               => [
                Rule::requiredIf(fn () => strtoupper((string) $this->input('currency')) !== $base),
                'nullable',
                'numeric',
                'min:0.000001',
            ],
            'shipping_amount'             => 'nullable|numeric|min:0',
            'payment_terms_days'          => 'required|integer|min:0|max:365',
            'msic_code'                   => 'required|string|max:10',
            'items'                       => 'required|array|min:1',
            'items.*.description'         => 'required|string|max:500',
            'items.*.quantity'            => 'required|numeric|min:0.01',
            'items.*.unit_price'          => 'required|numeric|min:0',
            'items.*.tax_rate'            => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'     => 'nullable|numeric|min:0',
            'items.*.product_id'          => 'nullable|integer|exists:products,id',
            'items.*.item_classification' => 'nullable|string|max:20',
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
