<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.edit');
    }

    public function rules(): array
    {
        $id = $this->route('bill');

        return [
            'bill_number'          => ['required', 'string', 'max:50', Rule::unique('bills', 'bill_number')->ignore($id)],
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'bill_date'            => 'required|date',
            'due_date'             => 'nullable|date|after_or_equal:bill_date',
            'tax_amount'           => 'nullable|numeric|min:0',
            'reference'            => 'nullable|string|max:100',
            'private_notes'        => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'nullable|integer',
            'items.*.account_code' => 'required|string|exists:accounts,code',
            'items.*.description'  => 'nullable|string',
            'items.*.quantity'     => 'nullable|numeric|min:0',
            'items.*.unit_amount'  => 'nullable|numeric|min:0',
            'items.*.amount'       => 'required|numeric|min:0',
        ];
    }
}
