<?php

namespace App\Http\Requests\Users;

use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

/**
 * Same contact/masking/access rules as create; username and billing are not editable here
 * and the password is optional (blank keeps the current one).
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['username'], $rules['account_type'], $rules['balance']);

        $rules['password'] = ['nullable', 'string', 'min:8', 'max:100'];
        $rules['expiry'] = ['required', 'date'];
        // Legacy accounts may carry types that the create form does not offer.
        $rules['user_type'] = ['required', Rule::in([...self::USER_TYPES, 'reseller', 'campapprover'])];

        return $rules;
    }

    public function payload(): array
    {
        return array_merge($this->validated(), [
            'mobiles' => implode(',', array_unique(array_map(PhoneNumber::normalize(...), $this->list('mobiles')))),
            'emails'  => implode(',', array_unique(array_map('strtolower', $this->list('emails')))),
            'calling_api' => $this->boolean('dlr_forward') ? $this->callingApi() : null,
        ]);
    }
}