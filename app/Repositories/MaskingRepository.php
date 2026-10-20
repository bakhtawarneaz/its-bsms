<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class MaskingRepository
{
    /**
     * One page of maskings plus per-state counts.
     *
     * @return array{rows: array<int, object>, counts: array<string, int>}
     */
    public function list(array $filters, ?int $promotional, int $page, int $perPage): array
    {
        $sets = StoredProcedure::multiple('usp_maskings_list', [
            'search'      => $filters['search'],
            'promotional' => $promotional,
            'state'       => $filters['state'],
            'page'        => $page,
            'per_page'    => $perPage,
        ]);

        $counts = array_fill_keys(['active', 'inactive', 'unrouted'], 0);
        foreach ($sets[1] ?? [] as $row) {
            $counts[$row->state] = (int) $row->total;
        }

        return ['rows' => $sets[0] ?? [], 'counts' => $counts];
    }

    /**
     * Masking row plus its route rows, or null when not found.
     *
     * @return array{masking: object, routes: array<int, object>}|null
     */
    public function find(int $id): ?array
    {
        [$rows, $routes] = array_pad(StoredProcedure::multiple('usp_maskings_get', ['masking_id' => $id]), 2, []);

        return $rows ? ['masking' => $rows[0], 'routes' => $routes] : null;
    }

    public function routeOptions(): array
    {
        return StoredProcedure::select('usp_maskings_route_options');
    }

    /**
     * Returns an object with "status" (OK, DUPLICATE) and "id".
     */
    public function create(array $data): object
    {
        return StoredProcedure::first('usp_maskings_create', $data);
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND) and "masking".
     */
    public function saveRoutes(array $data): object
    {
        return StoredProcedure::first('usp_maskings_routes_save', $data);
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND, NO_ROUTES), "masking" and "updated".
     */
    public function setStatus(array $data): object
    {
        return StoredProcedure::first('usp_maskings_set_status', $data);
    }

        /**
     * Pending masking requests.
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function requests(int $page, int $perPage): array
    {
        [$rows, $total] = array_pad(StoredProcedure::multiple('usp_masking_requests_list', [
            'page'     => $page,
            'per_page' => $perPage,
        ]), 2, []);

        return ['rows' => $rows, 'total' => (int) ($total[0]->total ?? 0)];
    }
    
}