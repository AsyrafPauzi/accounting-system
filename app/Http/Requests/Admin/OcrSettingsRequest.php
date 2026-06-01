<?php

namespace App\Http\Requests\Admin;

use App\Models\OcrSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OcrSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(OcrSettings::PROVIDERS)],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['nullable', 'string', 'max:50'],
            'tesseract_languages' => ['nullable', 'string', 'max:100', 'regex:/^[a-z_+]+$/'],
            'max_image_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'clear_api_key' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tesseract_languages.regex' => 'Languages must be Tesseract codes joined by `+`, e.g. eng+msa+chi_sim.',
        ];
    }
}
