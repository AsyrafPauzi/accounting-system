<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.create');
    }

    public function rules(): array
    {
        return [
            'bill_number'          => 'required|string|max:50|unique:bills,bill_number',
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'bill_date'            => 'required|date',
            'due_date'             => 'nullable|date|after_or_equal:bill_date',
            'tax_amount'           => 'nullable|numeric|min:0',
            'reference'            => 'nullable|string|max:100',
            'private_notes'        => 'nullable|string',
            'receipt_path'         => 'nullable|string',
            'ocr_status'           => 'nullable|string',
            'ocr_data'             => 'nullable|array',
            'items'                => 'required|array|min:1',
            'items.*.account_code' => 'required|string|exists:accounts,code',
            'items.*.description'  => 'nullable|string|max:255',
            'items.*.quantity'     => 'nullable|numeric|min:0',
            'items.*.unit_amount'  => 'nullable|numeric|min:0',
            'items.*.amount'       => 'required|numeric|min:0',
        ];
    }
}
