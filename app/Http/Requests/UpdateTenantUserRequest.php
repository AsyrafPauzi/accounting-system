<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.edit');
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:admin,accountant,sales,viewer'],
        ];
    }
}
