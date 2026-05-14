<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickStoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email'],
            'tin'            => ['nullable', 'string', 'max:50'],
            'brn'            => ['nullable', 'string', 'max:50'],
            'code'           => ['nullable', 'string', 'unique:customers,code'],
            'billing_street' => ['nullable', 'string', 'max:500'],
            'billing_city'   => ['nullable', 'string', 'max:100'],
            'billing_state'  => ['nullable', 'string', 'max:100'],
            'billing_zip'    => ['nullable', 'string', 'max:20'],
        ];
    }
}
