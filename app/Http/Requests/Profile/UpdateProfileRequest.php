<?php

namespace App\Http\Requests\Profile;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    private const MAX_ENTRIES = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullname'          => ['required', 'string', 'max:50'],
            'mobiles'           => ['required', 'string', 'max:200'],
            'emails'            => ['required', 'string', 'max:500'],
            'low_balance_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    public function attributes(): array
    {
        return ['fullname' => 'business name', 'low_balance_limit' => 'low balance alert'];
    }

    /**
     * Validate each comma-separated mobile number and email.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $invalidMobiles = array_filter($this->rawList('mobiles'), fn ($m) => ! PhoneNumber::isValid($m));
                $invalidEmails = array_filter($this->rawList('emails'), fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));

                if ($invalidMobiles) {
                    $validator->errors()->add('mobiles', 'Invalid mobile number(s): ' . implode(', ', $invalidMobiles));
                }

                if ($invalidEmails) {
                    $validator->errors()->add('emails', 'Invalid email(s): ' . implode(', ', $invalidEmails));
                }

                if (count($this->mobiles()) > self::MAX_ENTRIES) {
                    $validator->errors()->add('mobiles', 'Add up to ' . self::MAX_ENTRIES . ' mobile numbers.');
                }

                if (count($this->emails()) > self::MAX_ENTRIES) {
                    $validator->errors()->add('emails', 'Add up to ' . self::MAX_ENTRIES . ' emails.');
                }
            },
        ];
    }

    /**
     * Normalized, unique mobile numbers.
     */
    public function mobiles(): array
    {
        return array_values(array_unique(array_filter(
            array_map(PhoneNumber::normalize(...), $this->rawList('mobiles'))
        )));
    }

    /**
     * Lower-cased, unique email addresses.
     */
    public function emails(): array
    {
        return array_values(array_unique(array_map('strtolower', $this->rawList('emails'))));
    }

    private function rawList(string $field): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->input($field)))));
    }
}