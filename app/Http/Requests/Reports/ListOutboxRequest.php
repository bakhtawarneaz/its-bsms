<?php

namespace App\Http\Requests\Reports;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListOutboxRequest extends FormRequest
{
    public const SOURCES = [
        'live'     => 'Live',
        'archive'  => 'Archive',
        'campaign' => 'Campaigns',
    ];

    /** Legacy export caps per source (campaign had none; capped for safety). */
    public const EXPORT_LIMITS = [
        'live'     => 500000,
        'archive'  => 250000,
        'campaign' => 500000,
    ];

    public const FIELDS = [
        'id'       => 'ID',
        'camp_id'  => 'Camp ID',
        'user_id'  => 'User ID',
        'sender'   => 'Sender',
        'receiver' => 'Receiver',
        'msgdata'  => 'Message',
    ];

    public const PER_PAGE = [25, 50, 100];

    public static function fieldsFor(string $source): array
    {
        return $source === 'campaign' ? static::FIELDS : array_diff_key(static::FIELDS, ['camp_id' => true]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source'   => ['nullable', 'in:' . implode(',', array_keys(static::SOURCES))],
            'field'    => ['nullable', 'in:' . implode(',', array_keys(static::FIELDS))],
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

                if ($value === '') {
                    return;
                }

                $field = $this->input('field', 'id');
                $fields = static::fieldsFor($this->input('source', 'live'));

                if (! isset($fields[$field])) {
                    $validator->errors()->add('value', 'This filter is not available here.');

                    return;
                }

                if (in_array($field, ['id', 'camp_id', 'user_id'], true) && ! ctype_digit($value)) {
                    $validator->errors()->add('value', $fields[$field] . ' must be a number.');
                }

                if ($field === 'receiver' && ! PhoneNumber::isValid($value)) {
                    $validator->errors()->add('value', 'Enter a valid mobile number.');
                }
            },
        ];
    }

    /**
     * @return array{source: string, field: ?string, value: ?string, page: int, per_page: int}
     */
    public function filters(): array
    {
        $value = trim((string) $this->validated('value'));
        $field = $value === '' ? null : ($this->validated('field') ?: 'id');

        if ($field === 'receiver') {
            $value = PhoneNumber::normalize($value);
        }

        return [
            'source'   => $this->validated('source') ?: 'live',
            'field'    => $field,
            'value'    => $field ? $value : null,
            'page'     => (int) ($this->validated('page') ?: 1),
            'per_page' => (int) ($this->validated('per_page') ?: static::PER_PAGE[0]),
        ];
    }
}