<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.create');
    }

    public function rules(): array
    {
        return [
            'name'                   => 'required|string|max:255',
            'code'                   => 'required|unique:customers,code',
            'email'                  => 'required|email',
            'tin'                    => 'required|string',
            'brn'                    => 'required|string',
            'billing_street'         => 'required|string',
            'billing_city'           => 'required|string',
            'billing_state'          => 'required|string',
            'billing_zip'            => 'required|string',
            'credit_limit'           => 'required|numeric',
            'payment_terms'          => 'required|integer|min:0|max:365',
            'industry'               => 'nullable|string|max:255',
            'website'                => 'nullable|string',
            'contact_person'         => 'nullable|string',
            'phone'                  => 'nullable|string',
            'shipping_street'        => 'nullable|string',
            'shipping_city'          => 'nullable|string',
            'shipping_state'         => 'nullable|string',
            'shipping_zip'           => 'nullable|string',
            'internal_notes'         => 'nullable|string',
            'credit_hold'            => 'nullable|boolean',
            'risk_rating'            => 'nullable|string|in:low,medium,high',
            'segment'                => 'nullable|string|max:50',
            'region'                 => 'nullable|string|max:50',
            'account_manager_id'     => [
                'nullable',
                Rule::exists(\App\Models\User::class, 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'invoice_delivery_method'=> 'nullable|string|in:email,none',
            'send_statement'         => 'nullable|boolean',
            'contacts'               => 'nullable|array',
            'contacts.*.name'        => 'nullable|string|max:255',
            'contacts.*.email'       => 'nullable|email',
            'contacts.*.phone'       => 'nullable|string|max:50',
            'contacts.*.type'        => 'nullable|string|in:billing,finance,operations',
            'contacts.*.is_primary'  => 'nullable|boolean',
        ];
    }
}
