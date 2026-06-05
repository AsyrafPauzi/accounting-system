<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('journal.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'date'                => ['required', 'date'],
            'bank_account_id'     => ['required', 'exists:accounts,id'],
            'category_account_id' => ['required', 'exists:accounts,id', 'different:bank_account_id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'description'         => ['nullable', 'string', 'max:500'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_account_id.different' => 'Source and destination accounts must be different.',
        ];
    }
}
