<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('products.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('id');

        return [
            'code'          => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')->ignore($productId)->whereNull('deleted_at')],
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string|max:2000',
            'unit_price'    => 'required|numeric|min:0',
            'account_code'  => 'nullable|string|max:20|exists:accounts,code',
            'tax_rate'      => 'nullable|numeric|min:0|max:100',
            'is_active'     => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'     => $this->boolean('is_active', true),
            'tax_rate'      => $this->input('tax_rate', 0),
            'display_order' => $this->input('display_order', 0),
        ]);
    }
}
