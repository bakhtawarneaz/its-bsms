<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;
use Illuminate\Support\Facades\DB;

class ContactRepository
{
    public function list(?string $field, ?string $value, int $offset, int $limit): array
    {
        return StoredProcedure::select('usp_contacts_list', [
            'field' => $field, 'value' => $value, 'offset' => $offset, 'limit' => $limit,
        ]);
    }

    public function add(int $userId, array $payload): object
    {
        return StoredProcedure::first('usp_contacts_add', [
            'user_id'  => $userId,
            'fullname' => $payload['fullname'],
            'number'   => $payload['number'],
            'email'    => $payload['email'],
            'option1'  => $payload['option1'],
            'option2'  => $payload['option2'],
            'option3'  => $payload['option3'],
            'group_id' => $payload['group_id'],
        ]);
    }

    public function update(int $id, array $payload): object
    {
        return StoredProcedure::first('usp_contacts_update', [
            'id'       => $id,
            'number'   => $payload['number'],
            'fullname' => $payload['fullname'],
            'email'    => $payload['email'],
            'option1'  => $payload['option1'],
            'option2'  => $payload['option2'],
            'option3'  => $payload['option3'],
        ]);
    }

    public function delete(int $id): object
    {
        return StoredProcedure::first('usp_contacts_delete', ['id' => $id]);
    }

    /** @param array<int, int> $groupIds */
    public function setGroups(int $userId, int $contactId, array $groupIds): object
    {
        return StoredProcedure::first('usp_contacts_set_groups', [
            'user_id' => $userId, 'contact_id' => $contactId, 'group_ids' => implode(',', $groupIds),
        ]);
    }

    public function groups(int $userId): array
    {
        return StoredProcedure::select('usp_groups_options', ['user_id' => $userId]);
    }

    public function export(?int $userId, ?int $groupId, ?string $field, ?string $value, int $maxRows): \Generator
    {
        return DB::cursor(
            'EXEC dbo.usp_contacts_export @user_id = ?, @group_id = ?, @field = ?, @value = ?, @max_rows = ?',
            [$userId, $groupId, $field, $value, $maxRows],
        );
    }

        public function import(int $userId, ?int $groupId, string $xml): object
    {
        return StoredProcedure::first('usp_contacts_import', [
            'user_id' => $userId, 'group_id' => $groupId, 'contacts' => $xml,
        ]);
    }
    
}