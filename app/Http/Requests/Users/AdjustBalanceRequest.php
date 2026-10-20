<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware restricts this to superadmin.
    }

    protected function prepareForValidation(): void
    {
        // Accept "10,000" style input.
        $this->merge(['amount' => str_replace([',', ' '], '', (string) $this->input('amount'))]);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['add', 'deduct'])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in'       => 'Choose whether to add or deduct credits.',
            'amount.required' => 'Enter the number of credits.',
            'amount.integer'  => 'Credits must be a whole number.',
            'amount.min'      => 'Enter at least 1 credit.',
        ];
    }
}