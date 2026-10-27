<?php

namespace App\Http\Requests\Reports;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ExportOutboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source'   => ['nullable', 'in:' . implode(',', array_keys(ListOutboxRequest::SOURCES))],
            'from'     => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to'       => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'sender'   => ['nullable', 'string', 'max:100'],
            'receiver' => ['nullable', 'string', 'max:20'],
            'sms_type' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $receiver = trim((string) $this->input('receiver'));

                if ($receiver !== '' && ! PhoneNumber::isValid($receiver)) {
                    $validator->errors()->add('receiver', 'Enter a valid mobile number.');
                }
            },
        ];
    }

    /**
     * @return array{source: string, from: Carbon, to: Carbon, sender: ?string, receiver: ?string, sms_type: ?string, max_rows: int}
     */
    public function filters(): array
    {
        $source = $this->validated('source') ?: 'live';
        $receiver = trim((string) $this->validated('receiver'));

        return [
            'source'   => $source,
            'from'     => Carbon::parse($this->validated('from'))->startOfDay(),
            'to'       => Carbon::parse($this->validated('to'))->endOfDay(),
            'sender'   => trim((string) $this->validated('sender')) ?: null,
            'receiver' => $receiver !== '' ? PhoneNumber::normalize($receiver) : null,
            'sms_type' => $this->validated('sms_type') ?: null,
            'max_rows' => ListOutboxRequest::EXPORT_LIMITS[$source],
        ];
    }
}