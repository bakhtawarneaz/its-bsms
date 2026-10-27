<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

class ImportContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'     => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:20480'],
            'group_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a CSV or XLSX file. Save .xls files as .xlsx first.',
            'file.max'   => 'The file must be 20 MB or smaller.',
        ];
    }
}