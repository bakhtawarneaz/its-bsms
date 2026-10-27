<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGatewayUsersRequest extends FormRequest
{
    public const USER_TYPES = ['SMPP', 'HTTP', 'DB'];

    public function authorize(): bool
    {
        return true; // Route middleware restricts this page to superadmin.
    }

    public function rules(): array
    {
        return [
            'search'  => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', Rule::in(['active', 'expired', 'disabled', 'blocked'])],
            'type'    => ['nullable', Rule::in(self::USER_TYPES)],
            'account' => ['nullable', Rule::in(['prepaid', 'postpaid'])],
            'page'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search'  => isset($data['search']) ? trim($data['search']) : null,
            'state'   => $data['state'] ?? null,
            'type'    => $data['type'] ?? null,
            'account' => $data['account'] ?? null,
            'page'    => (int) ($data['page'] ?? 1),
        ];
    }
}