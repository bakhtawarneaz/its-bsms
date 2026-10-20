<?php

namespace App\Http\Requests\Maskings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMaskingsRequest extends FormRequest
{
    public const STATES = ['active', 'inactive', 'unrouted'];

    public function authorize(): bool
    {
        return true; // Route middleware restricts this page to superadmin.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type'   => ['nullable', Rule::in(['promotional', 'transactional'])],
            'state'  => ['nullable', Rule::in(self::STATES)],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Normalized filters with defaults, ready for the service. */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search' => isset($data['search']) ? trim($data['search']) : null,
            'type'   => $data['type'] ?? null,
            'state'  => $data['state'] ?? null,
            'page'   => (int) ($data['page'] ?? 1),
        ];
    }
}