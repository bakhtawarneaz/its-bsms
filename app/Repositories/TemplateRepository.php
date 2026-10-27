<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class TemplateRepository
{
    /** @return array{0: int, 1: array} */
    public function list(int $userId, ?string $search, int $offset, int $limit): array
    {
        $sets = StoredProcedure::multiple('usp_templates_list', [
            'user_id' => $userId, 'search' => $search, 'offset' => $offset, 'limit' => $limit,
        ]);

        return [(int) ($sets[0][0]->total_rows ?? 0), $sets[1] ?? []];
    }

    public function save(int $userId, ?int $id, ?string $name, string $message): object
    {
        return StoredProcedure::first('usp_templates_save', [
            'user_id' => $userId, 'id' => $id, 'name' => $name, 'message' => $message,
        ]);
    }

    public function delete(int $userId, int $id): object
    {
        return StoredProcedure::first('usp_templates_delete', ['user_id' => $userId, 'id' => $id]);
    }

    /** @return array{0: int, 1: array} */
    public function otpList(?int $userId, int $offset, int $limit): array
    {
        $sets = StoredProcedure::multiple('usp_otp_templates_list', [
            'user_id' => $userId, 'offset' => $offset, 'limit' => $limit,
        ]);

        return [(int) ($sets[0][0]->total_rows ?? 0), $sets[1] ?? []];
    }

    public function addOtp(array $payload): object
    {
        return StoredProcedure::first('usp_otp_templates_add', [
            'user_id'    => $payload['user_id'],
            'sender'     => $payload['sender'],
            'message'    => $payload['message'],
            'is_unicode' => $payload['is_unicode'] ? 1 : 0,
        ]);
    }

    public function users(): array
    {
        return StoredProcedure::select('usp_reports_summary_users');
    }
}