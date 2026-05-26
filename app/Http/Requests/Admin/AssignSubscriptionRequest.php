<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('super-admin');
    }

    public function rules(): array
    {
        return [
            // Use a closure so the Plan model's CentralConnection is used instead of
            // whatever the active DB connection is (which may be a tenant DB).
            'plan_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (! Plan::where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected plan is not available or is inactive.');
                    }
                },
            ],
            'duration' => ['required', Rule::in(['1_month', '1_year', 'lifetime', 'custom'])],
            'ends_at'  => ['required_if:duration,custom', 'nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.required_if' => 'A custom end date is required when using custom duration.',
            'ends_at.after'       => 'The end date must be in the future.',
        ];
    }
}
