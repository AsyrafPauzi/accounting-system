<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('supplier');

        return [
            'name'            => 'required|string|max:255',
            'code'            => ['required', 'string', 'max:50', Rule::unique('suppliers')->ignore($id)],
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email',
            'tin'             => 'nullable|string|max:50',
            'brn'             => 'nullable|string|max:50',
            'payment_terms'   => 'required|integer|min:0|max:365',
            'currency'        => 'nullable|string|size:3',
            'billing_street'  => 'nullable|string|max:255',
            'billing_city'    => 'nullable|string|max:100',
            'billing_state'   => 'nullable|string|max:100',
            'billing_zip'     => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|max:100',
            'website'         => 'nullable|string|max:255',
            'region'          => 'nullable|string|max:50',
            'segment'         => 'nullable|string|max:50',
            'is_active'       => 'boolean',
            'internal_notes'  => 'nullable|string',
        ];
    }
}
