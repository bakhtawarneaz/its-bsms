<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;
use Illuminate\Support\Facades\DB;

class CampaignRepository
{
    /** @return array{0: array<string, int>, 1: array} counts per state, page rows */
    public function list(?string $field, ?string $value, ?string $state, int $offset, int $limit, ?int $scopeId = null): array
    {
        $sets = StoredProcedure::multiple('usp_campaigns_list', [
            'field' => $field, 'value' => $value, 'state' => $state, 'offset' => $offset, 'limit' => $limit, 'scope_id' => $scopeId,
        ]);

        $counts = [];

        foreach ($sets[0] ?? [] as $row) {
            $counts[$row->state] = (int) $row->total;
        }

        return [$counts, $sets[1] ?? []];
    }

    public function action(int $id, string $action, ?string $at = null, ?int $scopeId = null): object
    {
        return StoredProcedure::first('usp_campaigns_action', ['id' => $id, 'action' => $action, 'at' => $at, 'scope_id' => $scopeId]);
    }

    public function logs(int $id): \Generator
    {
        return DB::cursor('EXEC dbo.usp_campaigns_logs @id = ?', [$id]);
    }

    public function revert(int $id, int $approverId): object
    {
        return StoredProcedure::first('usp_campaigns_revert', ['id' => $id, 'approver_id' => $approverId]);
    }
}