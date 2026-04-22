<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('invoices.*')) {
            return $this->user()->can('invoices.record-payment');
        }

        if ($this->routeIs('bills.*')) {
            return $this->user()->can('bills.record-payment');
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_date'      => ['required', 'date'],
            'bank_account_code' => ['required', 'string', 'exists:accounts,code'],
        ];
    }
}
