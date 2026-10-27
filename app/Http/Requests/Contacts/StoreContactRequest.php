<?php

namespace App\Http\Requests\Contacts;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'fullname' => ['required', 'string', 'max:150'],
            'mobile'   => [$creating ? 'required' : 'nullable', 'string', 'max:20'],
            'email'    => ['nullable', 'email', 'max:150'],
            'option1'  => ['nullable', 'string', 'max:250'],
            'option2'  => ['nullable', 'string', 'max:250'],
            'option3'  => ['nullable', 'string', 'max:250'],
            'group_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['fullname' => 'name', 'option1' => 'extra field 1', 'option2' => 'extra field 2', 'option3' => 'extra field 3'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $mobile = trim((string) $this->input('mobile'));

                if ($mobile !== '' && ! PhoneNumber::isValid($mobile)) {
                    $validator->errors()->add('mobile', 'Invalid Mobile Number.');
                }
            },
        ];
    }

    /** Empty optional fields are sent as NULL so an edit keeps their old value (legacy). */
    public function payload(): array
    {
        $optional = fn (string $key) => trim((string) $this->validated($key)) ?: null;
        $mobile = $optional('mobile');

        return [
            'fullname' => trim($this->validated('fullname')),
            'number'   => $mobile ? PhoneNumber::normalize($mobile) : null,
            'email'    => $optional('email'),
            'option1'  => $optional('option1'),
            'option2'  => $optional('option2'),
            'option3'  => $optional('option3'),
            'group_id' => $this->validated('group_id') ? (int) $this->validated('group_id') : null,
        ];
    }
}