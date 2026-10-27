<?php

namespace App\Http\Requests\Templates;

use App\Support\SmsText;
use Illuminate\Foundation\Http\FormRequest;

class StoreOtpTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'sender'  => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['user_id.required' => 'Select a user account.'];
    }

    /** @return array{user_id: int, sender: string, message: string, is_unicode: bool} */
    public function payload(): array
    {
        $message = SmsText::clean($this->validated('message'));

        return [
            'user_id'    => (int) $this->validated('user_id'),
            'sender'     => SmsText::clean(trim($this->validated('sender'))),
            'message'    => $message,
            'is_unicode' => SmsText::isUnicode($message),
        ];
    }
}