<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.edit');
    }

    public function rules(): array
    {
        return [
            'customer_id'                 => 'required|exists:customers,id',
            'msic_code'                   => 'required|string',
            'issue_date'                  => 'required|date',
            'due_date'                    => 'nullable|date|after_or_equal:issue_date',
            'items'                       => 'required|array|min:1',
            'items.*.description'         => 'required|string',
            'items.*.quantity'            => 'required|numeric|min:0.01',
            'items.*.unit_price'          => 'required|numeric',
            'items.*.tax_rate'            => 'required|numeric',
            'items.*.item_classification' => 'required|string',
            'items.*.discount_amount'     => 'nullable|numeric',
            'shipping_amount'             => 'nullable|numeric',
            'customer_notes'              => 'nullable|string',
        ];
    }
}
