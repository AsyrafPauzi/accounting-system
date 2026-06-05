<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => ['required', 'string', 'in:admin,accountant,sales,viewer'],
            // Tick when the admin acknowledges the extra-seat charge. Server
            // re-checks against the live seat count, so this is purely
            // intent-confirmation, not authorisation.
            'authorize_extra_seat_charge' => ['nullable', 'boolean'],
        ];
    }
}
