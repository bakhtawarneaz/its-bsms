<?php

namespace App\Http\Requests\Maskings;

use App\Services\MaskingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMaskingRequest extends FormRequest
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
            'masks'       => ['required', 'string', 'max:2000'],
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
        return [
            'masks.required'    => 'Enter at least one masking.',
            'routes.*.required' => 'Choose a route for every network.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $masks = $this->masks();

                if (! $masks) {
                    $validator->errors()->add('masks', 'Enter at least one masking.');
                }
                
                if (! array_filter((array) $this->input('routes', []), fn ($route) => $route !== null && $route !== '')) {
                    $validator->errors()->add('routes', 'Please select routes for this mask.');
                }

                if (array_filter($masks, fn ($mask) => mb_strlen($mask) > 50)) {
                    $validator->errors()->add('masks', 'A masking can be at most 50 characters.');
                }
            },
        ];
    }

    public function payload(): array
    {
        return [
            'masks'       => $this->masks(),
            'promotional' => (bool) $this->validated('promotional'),
            'routes' => array_map(fn ($route) => $route === null ? null : (int) $route, $this->validated('routes')),
        ];
    }

    /**
     * Comma or line separated maskings, trimmed and de-duplicated.
     */
    private function masks(): array
    {
        $parts = preg_split('/[,\r\n]+/', (string) $this->input('masks'));

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($mask) => $mask !== '')));
    }
}