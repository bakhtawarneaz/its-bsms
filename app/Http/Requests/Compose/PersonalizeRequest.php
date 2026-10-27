<?php

namespace App\Http\Requests\Compose;

use App\Support\PersonalizeFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class PersonalizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'camp_name' => ['required', 'string', 'max:100'],
            'sender'    => ['required', 'string', 'max:15'],
            'file'      => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
            'message'   => ['required', 'string', 'max:2000'],
            'schedule'  => ['required', 'in:now,later'],
            'send_date' => ['required_if:schedule,later', 'nullable', 'date_format:Y-m-d'],
            'send_time' => ['required_if:schedule,later', 'nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Upload the contact file.',
            'file.mimes'    => 'Upload a CSV, TXT or XLSX file.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $message = (string) $this->input('message');

                // Legacy: column A holds the numbers, so $A$ is reserved.
                if (str_contains($message, '$A$')) {
                    $validator->errors()->add('message', 'Please do not use reserved Col A, instead, use a valid keyword or keywords.');
                } elseif (trim($message) !== '' && ! PersonalizeFile::hasKeyword($message)) {
                    $validator->errors()->add('message', 'Please enter your message keyword(s) for the personalised campaign.');
                }

                if ($this->input('schedule') === 'later' && ($at = $this->scheduledAt()) && $at->lte(now())) {
                    $validator->errors()->add('send_time', 'The schedule time must be in the future.');
                }
            },
        ];
    }

    public function scheduledAt(): ?Carbon
    {
        if ($this->input('schedule') !== 'later' || ! $this->input('send_date') || ! $this->input('send_time')) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', $this->input('send_date') . ' ' . $this->input('send_time'));
    }
}