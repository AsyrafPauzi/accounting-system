<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission checked in controller/routes
        return true;
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
