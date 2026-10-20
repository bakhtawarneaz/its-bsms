<?php

namespace App\Http\Requests\Maskings;

use App\Services\MaskingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMaskingRoutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['promotional' => $this->boolean('promotional')]);
    }

    public function rules(): array
    {
        $rules = [
            'promotional' => ['boolean'],
            'routes'      => ['required', 'array'],
        ];

        foreach (array_keys(MaskingService::NETWORKS) as $network) {
            $rules["routes.{$network}"] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['routes.*.required' => 'Choose a route for every network.'];
    }

    public function payload(): array
    {
        return [
            'promotional' => (bool) $this->validated('promotional'),
            'routes' => array_map(fn ($route) => $route === null ? null : (int) $route, $this->validated('routes')),
        ];
    }
    
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! array_filter((array) $this->input('routes', []), fn ($route) => $route !== null && $route !== '')) {
                    $validator->errors()->add('routes', 'Please select routes for this mask.');
                }
            },
        ];
    }
}