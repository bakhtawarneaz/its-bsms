<?php

namespace App\Repositories;

use App\Support\Database\StoredProcedure;

class PersonalizedRepository
{
    public function campaigns(?int $scopeId): array
    {
        return StoredProcedure::select('usp_personalized_campaigns', ['scope_id' => $scopeId]);
    }

    public function messages(?int $scopeId, bool $pending, ?int $campId, ?string $field, ?string $value, int $offset, int $limit): array
    {
        return StoredProcedure::select('usp_personalized_messages', [
            'scope_id' => $scopeId,
            'pending'  => $pending ? 1 : 0,
            'camp_id'  => $campId,
            'field'    => $field,
            'value'    => $value,
            'offset'   => $offset,
            'limit'    => $limit,
        ]);
    }

    /** @param array<int, int> $ids */
    public function action(?int $scopeId, string $action, ?int $campId, array $ids): object
    {
        return StoredProcedure::first('usp_personalized_action', [
            'scope_id' => $scopeId,
            'action'   => $action,
            'camp_id'  => $campId,
            'ids'      => $ids ? implode(',', $ids) : null,
        ]);
    }
}