<?php

namespace App\Http\Requests\Resellers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResellerBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['dark_theme' => $this->boolean('dark_theme')]);
    }

    public function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'company_name' => ['required', 'string', 'max:100'],
            'slogan'       => ['required', 'string', 'max:200'],
            'logo'         => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'first_color'  => $hex,
            'second_color' => $hex,
            'third_color'  => $hex,
            'fourth_color' => $hex,
            'dark_theme'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.mimes' => 'The logo must be a PNG file.',
            'logo.max'   => 'The logo must be 2 MB or smaller.',
            '*.regex'    => 'Pick a valid color.',
        ];
    }
}