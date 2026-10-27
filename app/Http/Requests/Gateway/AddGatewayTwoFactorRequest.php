<?php

namespace App\Http\Requests\Gateway;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddGatewayTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts'          => ['required', 'array', 'min:1', 'max:10'],
            'contacts.*.name'   => ['nullable', 'string', 'max:100'],
            'contacts.*.mobile' => ['required', 'string', 'max:20'],
            'contacts.*.email'  => ['required', 'email', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'contacts.*.mobile.required' => 'Phone must be provided for every contact.',
            'contacts.*.email.required'  => 'Email must be provided for every contact.',
            'contacts.*.email.email'     => 'One or more emails are not valid.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('contacts', []) as $contact) {
                    if (! PhoneNumber::isValid((string) ($contact['mobile'] ?? ''))) {
                        $validator->errors()->add('contacts', 'One or more mobile numbers are not valid.');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * Normalized contacts. A blank name falls back to the email's local part (legacy behaviour).
     *
     * @return array<int, array{name: string, mobile: string, email: string}>
     */
    public function payload(): array
    {
        return array_map(function (array $contact) {
            $email = strtolower(trim($contact['email']));
            preg_match('/^([a-zA-Z0-9]+)/', $email, $matches);

            return [
                'name'   => trim((string) ($contact['name'] ?? '')) ?: ucfirst($matches[1] ?? 'User'),
                'mobile' => PhoneNumber::normalize(trim($contact['mobile'])),
                'email'  => $email,
            ];
        }, array_values($this->validated('contacts')));
    }
}