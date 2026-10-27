<?php

namespace App\Http\Requests\Gateway;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGatewayMaskRoutesRequest extends FormRequest
{
    public const NETWORK_KEYS = ['jazz', 'warid', 'ufone', 'zong', 'telenor', 'scom'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mask'           => ['required', 'string', 'max:200', 'regex:/^(?:[0-9a-fA-F]{2})+$/'],
            'user_id'        => ['required', 'integer', 'min:1'],
            'promotional'    => ['nullable', 'boolean'],
            'routes'         => ['required', 'array:' . implode(',', self::NETWORK_KEYS), 'min:1'],
            'routes.*.id'    => ['required', 'integer', 'min:1'],
            'routes.*.route' => ['required', 'string', 'max:150', 'regex:/^\d+,.+$/'],
            'routes.*.rate'  => ['nullable', 'numeric', 'min:1', 'max:9999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'routes.required'      => 'Please select routes for this mask.',
            'routes.*.route.regex' => 'One or more routes are not valid.',
            'routes.*.rate.min'    => 'Rate must be at least 1.00.',
            'mask.regex'           => 'Mask is not valid.',
        ];
    }

    /**
     * Route rows split into route id and operator; an empty rate falls back to 1.00 (legacy).
     *
     * @return array<string, array{id: int, route: int, operator: string, rate: float}>
     */
    public function routes(): array
    {
        $routes = [];

        foreach ($this->validated('routes') as $key => $row) {
            [$route, $operator] = explode(',', $row['route'], 2);

            $routes[$key] = [
                'id'       => (int) $row['id'],
                'route'    => (int) $route,
                'operator' => trim($operator),
                'rate'     => ($row['rate'] ?? '') === '' ? 1.00 : round((float) $row['rate'], 2),
            ];
        }

        return $routes;
    }

        /**
     * The exact mask; it is posted hex-encoded so leading/trailing spaces survive TrimStrings.
     */
    public function mask(): string
    {
        return hex2bin($this->validated('mask'));
    }
}