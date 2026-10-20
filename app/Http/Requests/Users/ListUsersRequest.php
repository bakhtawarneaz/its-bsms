<?php

namespace App\Http\Requests\Users;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public const STATES = ['active', 'expired', 'disabled', 'blocked'];

    public function authorize(): bool
    {
        return true; // Route middleware already restricts this page to superadmin.
    }

    public function rules(): array
    {
        return [
            'search'  => ['nullable', 'string', 'max:100'],
            'type'    => ['nullable', Rule::in(array_column(UserType::cases(), 'value'))],
            'state'   => ['nullable', Rule::in(self::STATES)],
            'account' => ['nullable', Rule::in(['prepaid', 'postpaid'])],
            'page'    => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Normalized filters with defaults, ready for the service. */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search'  => isset($data['search']) ? trim($data['search']) : null,
            'type'    => $data['type'] ?? null,
            'state'   => $data['state'] ?? null,
            'account' => $data['account'] ?? null,
            'page'    => (int) ($data['page'] ?? 1),
        ];
    }
}