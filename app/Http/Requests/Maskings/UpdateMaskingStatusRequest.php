<?php

namespace App\Http\Requests\Maskings;

use App\Services\MaskingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaskingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Unchecked switches are not posted, so every network is normalized to true/false.
        $posted = (array) $this->input('networks', []);
        $networks = [];

        foreach (array_keys(MaskingService::NETWORKS) as $network) {
            $networks[$network] = filter_var($posted[$network] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge([
            'promotional' => $this->boolean('promotional'),
            'networks'    => $networks,
        ]);
    }

    public function rules(): array
    {
        return [
            'maskings'    => ['required', 'array', 'min:1', 'max:500'],
            'maskings.*'  => ['integer'],
            'promotional' => ['boolean'],
            'networks'    => ['array'],
            'networks.*'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['maskings.required' => 'Select at least one masking.'];
    }
}