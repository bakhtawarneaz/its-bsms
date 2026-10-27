<?php

namespace App\Http\Requests\Compose;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'camp_name'    => ['nullable', 'string', 'max:100'],
            'sender'       => ['required', 'string', 'max:15'],
            'receivers'    => ['nullable', 'string', 'max:30000'],
            'groups'       => ['nullable', 'array', 'max:200'],
            'groups.*'     => ['integer', 'min:1'],
            'all_contacts' => ['nullable', 'boolean'],
            'file'         => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
            'message'      => ['required', 'string', 'max:2000'],
            'schedule'     => ['required', 'in:now,later'],
            'send_date'    => ['required_if:schedule,later', 'nullable', 'date_format:Y-m-d'],
            'send_time'    => ['required_if:schedule,later', 'nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Upload a CSV, TXT or XLSX file.'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! trim((string) $this->input('receivers')) && ! $this->input('groups') && ! $this->boolean('all_contacts') && ! $this->hasFile('file')) {
                    $validator->errors()->add('receivers', 'Add numbers, choose a group or upload a file.');
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