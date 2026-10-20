<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class UserRepository
{
    /**
     * Dropdown data for the user form.
     */
    public function formOptions(): array
    {
        [$maskings, $admins, $reportUsers, $customers] = array_pad(
            StoredProcedure::multiple('usp_users_form_options'),
            4,
            []
        );

        $toOptions = fn (array $rows, string $value = 'value', string $label = 'label') => array_map(
            fn ($row) => ['value' => (int) $row->{$value}, 'label' => trim((string) $row->{$label})],
            $rows
        );

        return [
            'maskings'    => $toOptions($maskings),
            'admins'      => $toOptions($admins),
            'reportUsers' => $toOptions($reportUsers),
            'customers'   => $toOptions($customers, 'Id', 'Name'),
        ];
    }

    /**
     * One page of users plus per-state counts.
     *
     * @return array{rows: array<int, object>, counts: array<string, int>}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $sets = StoredProcedure::multiple('usp_users_list', [
            'search'       => $filters['search'],
            'user_type'    => $filters['type'],
            'state'        => $filters['state'],
            'account_type' => $filters['account'],
            'page'         => $page,
            'per_page'     => $perPage,
        ]);

        $rows = array_map(fn ($row) => (object) (array) $row, $sets[0] ?? []);

        $counts = array_fill_keys(['active', 'expired', 'disabled', 'blocked'], 0);
        foreach ($sets[1] ?? [] as $row) {
            $counts[$row->state] = (int) $row->total;
        }

        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * User row plus assigned masking ids, or null when not found.
     *
     * @return array{user: object, maskings: array<int, int>}|null
     */
    public function find(int $id): ?array
    {
        [$rows, $maskings] = array_pad(StoredProcedure::multiple('usp_users_get', ['user_id' => $id]), 2, []);

        if (! $rows) {
            return null;
        }

        return [
            'user'     => $rows[0],
            'maskings' => array_map(fn ($row) => (int) $row->masking_id, $maskings),
        ];
    }

    /**
     * Returns an object with "status" (OK, USERNAME_TAKEN, INSUFFICIENT_BALANCE) and "id".
     */
    public function create(array $data): object
    {
        return StoredProcedure::first('usp_users_create', $data);
    }

    /**
     * Returns an object with "status" (OK, NOT_FOUND, HAS_USERS).
     */
    public function update(array $data): object
    {
        return StoredProcedure::first('usp_users_update', $data);
    }

    public function addMasking(int $userId, int $maskingId, int $resellerId): void
    {
        StoredProcedure::first('usp_user_masking_add', [
            'user_id'     => $userId,
            'masking_id'  => $maskingId,
            'reseller_id' => $resellerId,
        ]);
    }

    public function removeMasking(int $userId, int $maskingId): void
    {
        StoredProcedure::first('usp_user_masking_remove', [
            'user_id'    => $userId,
            'masking_id' => $maskingId,
        ]);
    }

    /**
     * Returns an object with "affected" and "username".
     */
    public function setStatus(int $id, bool $active): object
    {
        return StoredProcedure::first('usp_users_set_status', ['user_id' => $id, 'status' => $active ? 1 : 0]);
    }

    /**
     * Returns an object with "affected" and "username".
     */
    public function unblock(int $id): object
    {
        return StoredProcedure::first('usp_users_unblock', ['user_id' => $id]);
    }

    /**
     * Returns an object with "status", "username" and "detail".
     */
    public function delete(int $id): object
    {
        return StoredProcedure::first('usp_users_delete', ['user_id' => $id]);
    }

        /**
     * Returns an object with "status", "username" and "balance".
     */
    public function adjustBalance(int $editorId, int $userId, string $action, int $amount): object
    {
        return StoredProcedure::first('usp_users_balance_adjust', [
            'editor_id' => $editorId,
            'user_id'   => $userId,
            'amount'    => $amount,
            'action'    => $action,
        ]);
    }

    public function balanceHistory(int $userId, int $limit = 10): array
    {
        return StoredProcedure::select('usp_users_balance_history', ['user_id' => $userId, 'limit' => $limit]);
    }
}