<?php

namespace App\Http\Requests\Compose;

use Illuminate\Foundation\Http\FormRequest;

class QuickSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender'    => ['required', 'string', 'max:20'],
            'receivers' => ['required', 'string', 'max:30000'],
            'message'   => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['receivers.required' => 'Enter at least one mobile number.'];
    }
}