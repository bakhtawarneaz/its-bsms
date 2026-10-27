<?php

namespace App\Http\Requests\Reports;

use App\Support\LogReports;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ExportLogRequest extends FormRequest
{
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
            'from'     => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to'       => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'sender'   => ['nullable', 'string', 'max:100'],
            'receiver' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, sender: ?string, receiver: ?string, max_rows: int}
     */
    public function filters(): array
    {
        return [
            'from'     => Carbon::parse($this->validated('from'))->startOfDay(),
            'to'       => Carbon::parse($this->validated('to'))->endOfDay(),
            'sender'   => $this->party('sender'),
            'receiver' => $this->party('receiver'),
            'max_rows' => $this->definition()['max_rows'],
        ];
    }

    /** Mobile numbers are normalized to 92…; masks and short codes are used as typed. */
    private function party(string $key): ?string
    {
        $value = trim((string) $this->validated($key));

        if ($value === '') {
            return null;
        }

        return PhoneNumber::isValid($value) ? PhoneNumber::normalize($value) : $value;
    }
}