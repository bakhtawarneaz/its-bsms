<?php

namespace App\Http\Requests\Resellers;

use App\Http\Requests\Users\StoreUserRequest;

/**
 * Account rules come from StoreUserRequest (contacts, maskings, billing).
 * A reseller has no user type / admin / reporting user, and adds branding.
 */
class StoreResellerRequest extends StoreUserRequest
{
    public const DEFAULT_COLORS = [
        'first_color'  => '#47aee0',
        'second_color' => '#ffffff',
        'third_color'  => '#47aee0',
        'fourth_color' => '#ffffff',
    ];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // "https://sms.example.com/" -> "sms.example.com"
        $domain = strtolower(trim((string) $this->input('domain')));
        $domain = preg_replace('#^https?://#', '', $domain);

        $this->merge([
            'domain'     => rtrim($domain, '/'),
            'dark_theme' => $this->boolean('dark_theme'),
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['user_type'], $rules['reseller_id'], $rules['reporting_user_id']);

        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return $rules + [
            'company_name' => ['required', 'string', 'max:100'],
            'domain'       => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/'],
            'slogan'       => ['required', 'string', 'max:200'],
            'logo'         => ['required', 'file', 'mimes:png', 'max:2048'],
            'first_color'  => $hex,
            'second_color' => $hex,
            'third_color'  => $hex,
            'fourth_color' => $hex,
            'dark_theme'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'domain.regex' => 'Enter a valid domain, e.g. sms.company.com',
            'logo.required' => 'Upload the company logo.',
            'logo.mimes'   => 'The logo must be a PNG file.',
            'logo.max'     => 'The logo must be 2 MB or smaller.',
            '*.regex'      => 'Pick a valid color.',
        ];
    }
}