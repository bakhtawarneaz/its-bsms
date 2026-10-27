<?php

namespace App\Http\Requests\Reports;

use App\Support\SummaryReports;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSummaryRequest extends FormRequest
{
    public const PER_PAGE = [25, 50, 100];

    public function authorize(): bool
    {
        return true;
    }

    public function definition(): array
    {
        return SummaryReports::get((string) $this->route('report'));
    }

    /** Legacy default range: the last month up to today. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->subMonth()->toDateString(),
            'to'   => $this->input('to') ?: now()->toDateString(),
        ]);
    }

    public function rules(): array
    {
        $definition = $this->definition();

        return [
            'from'     => ['required', 'date_format:Y-m-d'],
            'to'       => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id'  => ['nullable', 'integer', 'min:1'],
            'sender'   => ['nullable', 'string', 'max:100'],
            'operator' => ['nullable', Rule::in(array_keys(SummaryReports::OPERATORS))],
            'sms_type' => ['nullable', Rule::in(array_keys($definition['sms_types']))],
            'status'   => ['nullable', Rule::in(array_map('strval', array_keys($definition['statuses'])))],
            'page'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'in:' . implode(',', static::PER_PAGE)],
        ];
    }

    public function filters(): array
    {
        return [
            'from'     => $this->validated('from'),
            'to'       => $this->validated('to'),
            'user_id'  => $this->validated('user_id') ? (int) $this->validated('user_id') : null,
            'sender'   => trim((string) $this->validated('sender')) ?: null,
            'operator' => $this->validated('operator') ?: null,
            'sms_type' => $this->validated('sms_type') ?: null,
            'status'   => $this->validated('status') ?: null,
            'page'     => (int) ($this->validated('page') ?: 1),
            'per_page' => (int) ($this->validated('per_page') ?: static::PER_PAGE[0]),
            'max_rows' => $this->definition()['max_rows'],
        ];
    }
}