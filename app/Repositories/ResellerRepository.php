<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class ResellerRepository
{
    /**
     * @return array{rows: array<int, object>, counts: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $sets = StoredProcedure::multiple('usp_resellers_list', [
            'search'   => $filters['search'],
            'state'    => $filters['state'],
            'page'     => $page,
            'per_page' => $perPage,
        ]);

        $counts = array_fill_keys(['active', 'expired', 'disabled', 'blocked'], 0);
        foreach ($sets[1] ?? [] as $row) {
            $counts[$row->state] = (int) $row->total;
        }

        return ['rows' => $sets[0] ?? [], 'counts' => $counts];
    }

    /**
     * Returns an object with "status" and "id".
     */
    public function create(array $data): object
    {
        return StoredProcedure::first('usp_resellers_create', $data);
    }

        /**
     * Reseller account plus its template row (null when missing), or null when not a reseller.
     *
     * @return array{reseller: object, template: object|null}|null
     */
    public function find(int $id): ?array
    {
        [$rows, $template] = array_pad(StoredProcedure::multiple('usp_resellers_get', ['user_id' => $id]), 2, []);

        return $rows ? ['reseller' => $rows[0], 'template' => $template[0] ?? null] : null;
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND, NO_DOMAIN) and "username".
     */
    public function updateBranding(array $data): object
    {
        return StoredProcedure::first('usp_resellers_branding_update', $data);
    }
}