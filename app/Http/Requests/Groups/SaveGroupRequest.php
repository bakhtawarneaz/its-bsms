<?php

namespace App\Http\Requests\Groups;

use App\Support\SmsText;
use Illuminate\Foundation\Http\FormRequest;

class SaveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groupname'   => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['groupname' => 'group name'];
    }

    /** Legacy clean-up: typographic characters swapped and - ' " ( ) , removed from the name. */
    public function name(): string
    {
        return trim(str_replace(['-', "'", '"', '(', ')', ','], '', SmsText::clean($this->validated('groupname'))));
    }

    public function description(): ?string
    {
        return trim((string) $this->validated('description')) ?: null;
    }
}