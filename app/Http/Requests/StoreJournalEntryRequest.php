<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('journal.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:50',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.debit' => 'required|numeric|min:0',
            'items.*.credit' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $totalDebit = array_sum(array_column($items, 'debit'));
            $totalCredit = array_sum(array_column($items, 'credit'));

            if (abs($totalDebit - $totalCredit) > 0.0001) {
                $validator->errors()->add('items', 'Total Debit must equal Total Credit.');
            }

            if ($totalDebit == 0 && $totalCredit == 0) {
                $validator->errors()->add('items', 'Journal entry cannot be empty (zero values).');
            }
        });
    }
}
