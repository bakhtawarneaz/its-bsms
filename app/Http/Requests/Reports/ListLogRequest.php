<?php

namespace App\Http\Requests\Reports;

use App\Support\LogReports;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListLogRequest extends FormRequest
{
    public const PER_PAGE = [25, 50, 100];

    public function authorize(): bool
    {
        return true;
    }

    public function definition(): array
    {
        return LogReports::get((string) $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'field'    => ['nullable', 'in:' . implode(',', array_keys($this->definition()['fields']))],
            'value'    => ['nullable', 'string', 'max:500'],
            'page'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'in:' . implode(',', static::PER_PAGE)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $value = trim((string) $this->input('value'));
                $definition = $this->definition();
                [$label, $type] = $definition['fields'][$this->input('field', 'id')] ?? ['', 'text'];

                if ($value === '') {
                    return;
                }

                $error = match (true) {
                    $type === 'number' && ! ctype_digit($value)                                        => "{$label} must be a number.",
                    $type === 'phone' && ! PhoneNumber::isValid($value)                                => 'Enter a valid mobile number.',
                    $type === 'status' && ! isset($definition['statuses'][strtolower($value)])         => 'Unknown status.',
                    default                                                                            => null,
                };

                if ($error) {
                    $validator->errors()->add('value', $error);
                }
            },
        ];
    }

    /**
     * @return array{field: ?string, value: ?string, page: int, per_page: int}
     */
    public function filters(): array
    {
        $definition = $this->definition();
        $value = trim((string) $this->validated('value'));
        $field = $value === '' ? null : ($this->validated('field') ?: 'id');
        $type = $field ? $definition['fields'][$field][1] : null;

        $value = match ($type) {
            'phone'  => PhoneNumber::normalize($value),
            'status' => (string) $definition['statuses'][strtolower($value)],
            default  => $value,
        };

        return [
            'field'    => $field,
            'value'    => $field ? $value : null,
            'page'     => (int) ($this->validated('page') ?: 1),
            'per_page' => (int) ($this->validated('per_page') ?: static::PER_PAGE[0]),
        ];
    }
}