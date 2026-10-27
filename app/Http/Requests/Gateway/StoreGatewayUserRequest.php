<?php

namespace App\Http\Requests\Gateway;

use App\Services\GatewayUserService;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGatewayUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware checks the GWUserCreate flag.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->boolean('status')]);
    }

    public function rules(): array
    {
        $rules = [
            'username'     => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password'     => ['required', 'string', 'min:4', 'max:50'],
            'web_username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'web_password' => ['required', 'string', 'min:8', 'max:100'],
            'fullname'     => ['required', 'string', 'max:100'],
            'mobiles'      => ['required', 'string', 'max:200'],
            'emails'       => ['required', 'string', 'max:500'],
            'expiry'       => ['required', 'date', 'after_or_equal:today'],
            'ips'          => ['required', 'string', 'max:500'],
            'default_mask' => ['required', 'string', 'max:50'],
            'user_type'    => ['required', Rule::in(ListGatewayUsersRequest::USER_TYPES)],
            'account_type' => ['required', Rule::in(['prepaid', 'postpaid'])],
            'balance'      => ['required_if:account_type,prepaid', 'nullable', 'integer', 'min:0', 'max:2000000000'],
            'reseller_id'  => ['required', 'integer'],
            'customer_id'  => ['required', 'integer'],
            'status'       => ['boolean'],
            'routes'       => ['required', 'array'],
        ];

        foreach (array_keys(GatewayUserService::NETWORKS) as $network) {
            $rules["routes.{$network}"] = ['required', 'integer'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'username.regex'       => 'Usernames can use letters, numbers, dots, dashes and underscores only.',
            'web_username.regex'   => 'Usernames can use letters, numbers, dots, dashes and underscores only.',
            'reseller_id.required' => 'Select a reseller.',
            'customer_id.required' => 'Select a master customer.',
            'balance.required_if'  => 'Enter the initial balance for a prepaid account.',
            'expiry.after_or_equal' => 'The expiry date cannot be in the past.',
        ];
    }

    public function attributes(): array
    {
        return ['ips' => 'IP addresses', 'default_mask' => 'default mask', 'web_username' => 'web username', 'web_password' => 'web password'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invalidIps = array_filter($this->list('ips'), fn ($ip) => ! filter_var($ip, FILTER_VALIDATE_IP));

                if (! $this->list('ips')) {
                    $validator->errors()->add('ips', 'Add at least one IP address.');
                } elseif ($invalidIps) {
                    $validator->errors()->add('ips', 'Invalid IP address(es): ' . implode(', ', $invalidIps));
                }

                if (array_filter($this->list('mobiles'), fn ($m) => ! PhoneNumber::isValid($m))) {
                    $validator->errors()->add('mobiles', 'One or more mobile numbers are not valid.');
                }

                if (array_filter($this->list('emails'), fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
                    $validator->errors()->add('emails', 'One or more emails are not valid.');
                }
            },
        ];
    }

    public function payload(): array
    {
        return array_merge($this->validated(), [
            'username'     => trim($this->validated('username')),
            'web_username' => trim($this->validated('web_username')),
            'default_mask' => trim($this->validated('default_mask')),
            'ips'          => implode(',', array_unique($this->list('ips'))),
            'mobiles'      => implode(',', array_unique(array_map(PhoneNumber::normalize(...), $this->list('mobiles')))),
            'emails'       => implode(',', array_unique(array_map('strtolower', $this->list('emails')))),
            'routes'       => array_map('intval', $this->validated('routes')),
        ]);
    }

    protected function list(string $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->input($field)))));
    }
}