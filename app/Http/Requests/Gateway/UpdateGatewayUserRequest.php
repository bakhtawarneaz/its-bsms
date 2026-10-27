<?php

namespace App\Http\Requests\Gateway;

use App\Support\PhoneNumber;

/**
 * Editable gateway fields only. IP, mobile and email checks come from StoreGatewayUserRequest::after().
 */
class UpdateGatewayUserRequest extends StoreGatewayUserRequest
{
    public function rules(): array
    {
        return [
            'fullname'     => ['required', 'string', 'max:100'],
            'mobiles'      => ['required', 'string', 'max:200'],
            'emails'       => ['required', 'string', 'max:500'],
            'expiry'       => ['required', 'date'],
            'ips'          => ['required', 'string', 'max:500'],
            'password'     => ['nullable', 'string', 'min:4', 'max:50'],
            'web_password' => ['nullable', 'string', 'min:8', 'max:100'],
            'status'       => ['boolean'],
        ];
    }

    public function payload(): array
    {
        return array_merge($this->validated(), [
            'ips'     => implode(',', array_unique($this->list('ips'))),
            'mobiles' => implode(',', array_unique(array_map(PhoneNumber::normalize(...), $this->list('mobiles')))),
            'emails'  => implode(',', array_unique(array_map('strtolower', $this->list('emails')))),
        ]);
    }
}