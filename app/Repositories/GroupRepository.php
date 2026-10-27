<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class GroupRepository
{
    /** @return array{0: int, 1: array} */
    public function list(int $userId, ?string $search, int $offset, int $limit): array
    {
        return $this->paged('usp_groups_list', ['user_id' => $userId, 'search' => $search, 'offset' => $offset, 'limit' => $limit]);
    }

    public function find(int $userId, int $id): ?object
    {
        return StoredProcedure::first('usp_groups_get', ['user_id' => $userId, 'id' => $id]);
    }

    public function save(int $userId, ?int $id, string $name, ?string $description): object
    {
        return StoredProcedure::first('usp_groups_save', [
            'user_id' => $userId, 'id' => $id, 'name' => $name, 'description' => $description,
        ]);
    }

    public function delete(int $userId, int $id): object
    {
        return StoredProcedure::first('usp_groups_delete', ['user_id' => $userId, 'id' => $id]);
    }

    /** @return array{0: int, 1: array} */
    public function members(int $userId, int $groupId, ?string $search, ?string $number, int $offset, int $limit): array
    {
        return $this->paged('usp_groups_members', [
            'user_id' => $userId, 'group_id' => $groupId, 'search' => $search, 'number' => $number, 'offset' => $offset, 'limit' => $limit,
        ]);
    }


    /** @return array{0: int, 1: array} */
    private function paged(string $procedure, array $params): array
    {
        $sets = StoredProcedure::multiple($procedure, $params);

        return [(int) ($sets[0][0]->total_rows ?? 0), $sets[1] ?? []];
    }
}