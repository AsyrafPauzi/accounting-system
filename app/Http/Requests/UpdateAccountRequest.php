<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $account = $this->route('chart_of_account');

        return [
            'code'          => ['required', 'string', 'max:50', Rule::unique('accounts', 'code')->ignore($account)],
            'name'          => ['required', 'string', 'max:255'],
            'type'          => ['required', 'string', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id'     => ['nullable', 'exists:accounts,id'],
            'description'   => ['nullable', 'string'],
            'is_active'     => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
