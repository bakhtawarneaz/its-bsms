<?php

namespace App\Http\Requests\Resellers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListResellersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware restricts this page to superadmin.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'state'  => ['nullable', Rule::in(['active', 'expired', 'disabled', 'blocked'])],
            'page'   => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search' => isset($data['search']) ? trim($data['search']) : null,
            'state'  => $data['state'] ?? null,
            'page'   => (int) ($data['page'] ?? 1),
        ];
    }
}