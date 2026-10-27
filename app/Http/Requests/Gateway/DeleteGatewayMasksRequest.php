<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;

class DeleteGatewayMasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'masks'   => ['required', 'array', 'min:1', 'max:500'],
            'masks.*' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return ['masks.required' => 'Select at least one mask to delete.'];
    }
}