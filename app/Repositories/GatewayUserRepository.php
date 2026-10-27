<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class GatewayUserRepository
{
    /**
     * @return array{rows: array<int, object>, counts: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $sets = StoredProcedure::multiple('usp_gw_users_list', [
            'search'       => $filters['search'],
            'state'        => $filters['state'],
            'user_type'    => $filters['type'],
            'account_type' => $filters['account'],
            'page'         => $page,
            'per_page'     => $perPage,
        ]);

        $counts = array_fill_keys(['active', 'expired', 'disabled', 'blocked'], 0);
        foreach ($sets[1] ?? [] as $row) {
            $counts[$row->state] = (int) $row->total;
        }

        return ['rows' => $sets[0] ?? [], 'counts' => $counts];
    }

        /**
     * @return array{routes: array<int, object>, resellers: array<int, object>}
     */
    public function formOptions(): array
    {
        [$routes, $resellers] = array_pad(StoredProcedure::multiple('usp_gw_users_form_options'), 2, []);

        return ['routes' => $routes, 'resellers' => $resellers];
    }

    /**
     * Returns an object with "status" and "id".
     */
    public function create(array $data): object
    {
        return StoredProcedure::first('usp_gw_users_create', $data);
    }
    
        /**
     * Gateway user plus its route rows, or null when not found.
     *
     * @return array{user: object, routes: array<int, object>}|null
     */
    public function find(int $id): ?array
    {
        [$rows, $routes] = array_pad(StoredProcedure::multiple('usp_gw_users_get', ['user_id' => $id]), 2, []);

        return $rows ? ['user' => $rows[0], 'routes' => $routes] : null;
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND).
     */
    public function update(array $data): object
    {
        return StoredProcedure::first('usp_gw_users_update', $data);
    }

    /**
     * Returns an object with "status", "username" and "balance".
     */
    public function adjustBalance(int $userId, string $action, int $amount): object
    {
        return StoredProcedure::first('usp_gw_users_balance_adjust', [
            'user_id' => $userId,
            'amount'  => $amount,
            'action'  => $action,
        ]);
    }
    
        /**
     * Returns an object with "status" (OK, EXISTS, NOT_FOUND).
     */
    public function addMask(array $data): object
    {
        return StoredProcedure::first('usp_gw_users_mask_add', $data);
    }

    public function deleteMask(int $userId, string $mask): int
    {
        return (int) (StoredProcedure::first('usp_gw_users_mask_delete', ['user_id' => $userId, 'mask' => $mask])->deleted ?? 0);
    }

        public function twoFactorContacts(int $userId): array
    {
        return StoredProcedure::select('usp_gw_2fa_list', ['user_id' => $userId]);
    }

    /**
     * Returns an object with "status" (OK, EXISTS, NOT_FOUND).
     */
    public function addTwoFactor(int $userId, array $contact, int $createdBy): object
    {
        return StoredProcedure::first('usp_gw_2fa_add', [
            'user_id'    => $userId,
            'name'       => $contact['name'],
            'mobile'     => $contact['mobile'],
            'email'      => $contact['email'],
            'created_by' => $createdBy,
        ]);
    }

        public function maskRouteMasks(): array
    {
        return StoredProcedure::select('usp_gw_mask_routes_masks');
    }

    public function maskRouteUsers(string $mask): array
    {
        return StoredProcedure::select('usp_gw_mask_routes_users', ['mask' => $mask]);
    }

    /**
     * @return array{0: array, 1: array} Route rows of the user, active route catalogue.
     */
    public function maskRoutes(string $mask, int $userId): array
    {
        $sets = StoredProcedure::multiple('usp_gw_mask_routes_get', ['mask' => $mask, 'user_id' => $userId]);

        return [$sets[0] ?? [], $sets[1] ?? []];
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND).
     */
    public function updateMaskRoute(int $userId, string $mask, bool $promotional, array $row): object
    {
        return StoredProcedure::first('usp_gw_mask_routes_update', [
            'id'          => $row['id'],
            'user_id'     => $userId,
            'mask'        => $mask,
            'route'       => $row['route'],
            'operator'    => $row['operator'],
            'promotional' => $promotional ? 1 : 0,
            'rate'        => $row['rate'],
        ]);
    }
}