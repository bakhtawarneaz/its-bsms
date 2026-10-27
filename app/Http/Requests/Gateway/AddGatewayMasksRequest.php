<?php

namespace App\Http\Requests\Gateway;

use App\Services\GatewayUserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddGatewayMasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'masks'  => ['required', 'string', 'max:2000'],
            'routes' => ['required', 'array'],
        ];

        foreach (array_keys(GatewayUserService::NETWORKS) as $network) {
            $rules["routes.{$network}"] = ['required', 'integer'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['masks.required' => 'Enter at least one mask.'];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->masks()) {
                    $validator->errors()->add('masks', 'Enter at least one mask.');
                }

                if (array_filter($this->masks(), fn ($mask) => mb_strlen($mask) > 50)) {
                    $validator->errors()->add('masks', 'A mask can be at most 50 characters.');
                }
            },
        ];
    }

    public function payload(): array
    {
        return [
            'masks'  => $this->masks(),
            'routes' => array_map('intval', $this->validated('routes')),
        ];
    }

    /** Comma or line separated masks, trimmed and de-duplicated. */
    private function masks(): array
    {
        $parts = preg_split('/[,\r\n]+/', (string) $this->input('masks'));

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($mask) => $mask !== '')));
    }
}