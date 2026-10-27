<?php

namespace App\Http\Requests\Templates;

use Illuminate\Foundation\Http\FormRequest;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->route('template') === null;

        return [
            'name'    => [$creating ? 'required' : 'prohibited', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'template name'];
    }
}