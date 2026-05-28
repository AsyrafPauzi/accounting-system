<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accounts.create');
    }

    public function rules(): array
    {
        return [
            'code'          => ['required', 'string', 'max:50', 'unique:accounts,code'],
            'name'          => ['required', 'string', 'max:255'],
            'type'          => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'sub_type'      => ['nullable', 'string', Rule::in(['bank', 'cash'])],
            'parent_id'     => ['nullable', 'exists:accounts,id'],
            'description'   => ['nullable', 'string'],
            'is_active'     => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $subType = $this->input('sub_type');
        if ($subType === '' || $subType === null) {
            $this->merge(['sub_type' => null]);
        }
        if ($this->input('type') !== 'asset') {
            $this->merge(['sub_type' => null]);
        }
    }
}
