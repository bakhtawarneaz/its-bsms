<?php

namespace App\Http\Requests\Users;

use App\Services\UserService;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public const USER_TYPES = ['admin', 'user', 'campreporting', 'lcreporting', 'gwuser', 'reporting'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->boolean('status')]);
    }

    public function rules(): array
    {
        return [
            'username'          => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password'          => ['required', 'string', 'min:8', 'max:100'],
            'fullname'          => ['required', 'string', 'max:50'],
            'mobiles'           => ['required', 'string', 'max:200'],
            'emails'            => ['required', 'string', 'max:500'],
            'maskings'          => ['required', 'array', 'min:1'],
            'maskings.*'        => ['integer'],
            'expiry'            => ['required', 'date', 'after_or_equal:today'],
            'status'            => ['boolean'],
            'customer_id'       => ['required', 'integer'],
            'account_type'      => ['required', Rule::in(['prepaid', 'postpaid'])],
            'balance'           => ['required_if:account_type,prepaid', 'nullable', 'integer', 'min:1', 'max:2000000000'],
            'user_type'         => ['required', Rule::in(self::USER_TYPES)],
            'reseller_id'       => [Rule::requiredIf(fn () => in_array($this->input('user_type'), UserService::TYPES_WITH_ADMIN, true)), 'nullable', 'integer'],
            'reporting_user_id' => ['required_if:user_type,reporting', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex'             => 'Usernames can use letters, numbers, dots, dashes and underscores only.',
            'maskings.required'          => 'Select at least one masking.',
            'customer_id.required'       => 'Select a master customer.',
            'balance.required_if'        => 'Enter the opening credits for a prepaid account.',
            'reseller_id.required'       => 'Select an admin for this user.',
            'reporting_user_id.required_if' => 'Select the user this account reports on.',
            'expiry.after_or_equal'      => 'The expiry date cannot be in the past.',
        ];
    }

    public function attributes(): array
    {
        return ['fullname' => 'full name', 'user_type' => 'user type', 'account_type' => 'account type'];
    }

    /**
     * Validate each comma-separated mobile number and email.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $mobiles = $this->list('mobiles');
                $emails = $this->list('emails');

                if (array_filter($mobiles, fn ($m) => ! PhoneNumber::isValid($m))) {
                    $validator->errors()->add('mobiles', 'One or more mobile numbers are not valid.');
                }

                if (array_filter($emails, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
                    $validator->errors()->add('emails', 'One or more emails are not valid.');
                }

                if (count($mobiles) > 5 || count($emails) > 5) {
                    $validator->errors()->add('mobiles', 'Add up to 5 mobile numbers and 5 emails.');
                }
            },
        ];
    }

    /**
     * Validated data with normalized contact lists.
     */
    public function payload(): array
    {
        return array_merge($this->validated(), [
            'username' => trim($this->validated('username')),
            'mobiles'  => implode(',', array_unique(array_map(PhoneNumber::normalize(...), $this->list('mobiles')))),
            'emails'   => implode(',', array_unique(array_map('strtolower', $this->list('emails')))),
        ]);
    }

    protected function list(string $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->input($field)))));
    }
}