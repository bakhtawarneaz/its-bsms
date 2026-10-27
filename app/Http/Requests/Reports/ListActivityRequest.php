<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ListActivityRequest extends FormRequest
{
    public const PER_PAGE = [25, 50, 100];

    public function authorize(): bool
    {
        return true;
    }

    /** Legacy default range: yesterday up to today. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->subDay()->toDateString(),
            'to'   => $this->input('to') ?: now()->toDateString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'from'     => ['required', 'date_format:Y-m-d'],
            'to'       => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id'  => ['nullable', 'integer', 'min:1'],
            'ip'       => ['nullable', 'string', 'max:45'],
            'search'   => ['nullable', 'string', 'max:200'],
            'page'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'in:' . implode(',', static::PER_PAGE)],
        ];
    }

    public function filters(): array
    {
        return [
            'from'     => $this->validated('from'),
            'to'       => $this->validated('to'),
            'from_at'  => Carbon::parse($this->validated('from'))->startOfDay(),
            'to_at'    => Carbon::parse($this->validated('to'))->endOfDay(),
            'user_id'  => $this->validated('user_id') ? (int) $this->validated('user_id') : null,
            'ip'       => trim((string) $this->validated('ip')) ?: null,
            'search'   => trim((string) $this->validated('search')) ?: null,
            'page'     => (int) ($this->validated('page') ?: 1),
            'per_page' => (int) ($this->validated('per_page') ?: static::PER_PAGE[0]),
        ];
    }
}