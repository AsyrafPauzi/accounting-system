<?php

namespace App\Http\Requests\Admin;

use App\Models\Permission;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('super-admin');
    }

    public function rules(): array
    {
        // Fetch valid permission names via the model (central connection) to avoid
        // the tenant DB being used when tenancy middleware is active.
        $validPermissions = Permission::pluck('name')->toArray();

        return [
            'name'             => ['required', 'string', 'max:100'],
            'slug'             => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(Plan::class, 'slug')],
            'price_monthly'    => ['required', 'numeric', 'min:0'],
            'price_yearly'     => ['required', 'numeric', 'min:0'],
            'users_included'   => ['required', 'integer', 'min:1'],
            'extra_user_price' => ['required', 'numeric', 'min:0'],
            'features'         => ['nullable', 'array'],
            'features.*'       => ['string', 'max:255'],
            'is_active'        => ['boolean'],
            'permissions'      => ['nullable', 'array'],
            'permissions.*'    => ['string', Rule::in($validPermissions)],
        ];
    }
}
